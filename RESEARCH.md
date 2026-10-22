# PHP2Shell × Checkmarx Container Security — Research Notes

A detailed record of investigating whether (and how) Checkmarx One **Container
Security** detects the two "PHP2Shell" PHP-core RCEs in container images, what
works, what doesn't, and *why*. Written so the findings are reproducible and the
dead ends don't have to be walked again.

---

## 1. The vulnerabilities

| CVE | Type | Severity |
|-----|------|----------|
| CVE-2026-109618 | Unauthenticated RCE — out-of-memory corruption (longjmp leaves request memory partially initialized; cleanup trusts it) | Critical, CVSS v4.0 9.2, CWE-416 |
| CVE-2026-109619 | Unauthenticated RCE — stale global state pointing into freed request memory | Critical, CVSS v4.0 9.2, CWE-416 |

- **Released:** 21 Oct 2026.
- **Fixed in:** PHP **8.5.12 / 8.4.27 / 8.3.36 / 8.2.35**. Everything at or below
  the last-vulnerable release on each branch is affected.
- **Scope:** all SAPIs that serve web requests (mod_php, PHP-FPM, php-cgi,
  FrankenPHP, LiteSpeed); the PHP team rates **mod_php highest risk**.
- **Exploitation:** a demonstrated unauthenticated PoC exists; **no confirmed
  in-the-wild exploitation** as of this writing.

Sources: [PHP advisory](https://www.php.net/archive/2026.php) ·
[Checkmarx Zero — PHP2Noise](https://checkmarx.com/zero-post/php2noise-two-php-critical-rces-patched-as-users-scramble-for-fix-amid-misinformation/).

---

## 2. Headline findings

1. **Container Security detects these CVEs only on distro-packaged PHP** — PHP
   installed as an OS package (`apt install php8.x`). It flags the vulnerable
   versions and clears the patched ones.
2. **It does NOT detect them on the official source-built `php:` images.** Those
   compile PHP from source, so there is no `php` entry in the image's OS package
   database. The engine scores the image only on its base-OS packages (curl,
   kernel, libs) and never fingerprints the PHP runtime. Confirming tell: its
   image-remediation rated the still-vulnerable `php:8.3.35-alpine` as
   **"0 vulnerabilities, MOST SECURE."**
3. **Matching is per distro release.** The engine matches an OS package against
   *that specific distro release's* CVE feed. A PHP branch is only detectable on
   the distro that actually ships (and tracks) it. See §4.
4. **The exact upstream patch numbers from the advisory are mostly not
   detectable.** No distro ships "php 8.3.35" or "php 8.5.11"; they ship their own
   patch levels. Detection works on the distro's version, not the upstream label.

**Field takeaway:** "SCA misses it, Container Security catches it" is true — for
distro-packaged PHP on a release that tracks that PHP version. It is *not* true
for the official source-built images, and detection is bounded by distro feed
coverage.

---

## 3. How the engine actually matches (mental model)

Container Security builds an SBOM of the image's OS packages (from
`/var/lib/dpkg/status` on Debian/Ubuntu) and matches each package against the
**distro + release** security feed:

```
(image OS release)  +  (package name / Source)  +  (version)   →  distro CVE feed
   e.g. debian trixie      php8.4 / Source php8.4     8.4.26          → vulnerable (fixed 8.4.27)
```

Consequences discovered the hard way:
- A package only matches if the image's release feed has an advisory for that
  **source package**. (`Source:` in the dpkg entry is the key, not just the
  binary name.)
- Only packages in **`install ok installed`** state are cataloged. Packages left
  `install ok unpacked` are silently skipped.
- The image's OS release comes from `/etc/os-release`; the scanner's displayed
  "FROM debian:latest" label is cosmetic, not the matching key.

---

## 4. Distro feed coverage (the crux)

Verified against the official security trackers for CVE-2026-109618:

| PHP branch | Debian | Ubuntu | Detectable on |
|-----------|--------|--------|---------------|
| php8.2 | **bookworm** (vuln 8.2.32 → fixed 8.2.35) | jammy (8.1 only) | **Debian bookworm** |
| php8.3 | **not shipped** | **24.04 noble** (fixed 8.3.6-0ubuntu0.24.04.13) | **Ubuntu 24.04** |
| php8.4 | **trixie** (vuln 8.4.24 → fixed 8.4.27) | not shipped | **Debian trixie** |
| php8.5 | **not shipped** | **26.04 resolute** (fixed 8.5.4-0ubuntu1.5), 26.10 | **Ubuntu 26.04/26.10** |

Key point: **Debian trixie ships only php8.4**; it never shipped php8.2/8.3/8.5.
So php8.2 is only matchable on a **bookworm** base, php8.3/php8.5 only on
**Ubuntu** bases. Putting php8.3 on a Debian image never matches — the Debian feed
has no php8.3 entry at all.

---

## 5. The dead ends (and why)

Chronological, because each failure taught the next constraint.

1. **Scanned the official `php:` images first.** Result: lots of base-OS CVEs,
   but **neither PHP2Shell CVE**. Root cause: source-built PHP isn't in the OS
   package DB → not fingerprinted. → **Must use distro-packaged PHP.**

2. **Put build Dockerfiles (`FROM debian:trixie` + `RUN apt install php`) in the
   repo and ran a repo scan.** Result: nothing — the engine resolves the `FROM`
   base and **does not execute `RUN` layers**. → **Build + push the image, then
   scan it by reference** (`--container-images`, or a one-line `FROM <ghcr ref>`
   scan target in the repo).

3. **Wanted exact upstream versions (8.3.35, 8.5.11) via the Sury repo.** Sury
   packages every branch at exact upstream versions — but **prunes old amd64
   patch builds**, keeping only the current (fixed) amd64. Old vulnerable versions
   survive only as **arm64** in the pool. → amd64 of the exact vulnerable version
   doesn't exist.

4. **Built the exact version as an arm64 image.** The Checkmarx analyzer is
   **amd64-only** and rejects arm64 (`mismatched platform (expected
   linux/amd64)`). Workaround exists (skopeo `--override-arch arm64` into an OCI
   dir, then scan that), but it's a known CLI limitation, not a clean demo path.

5. **Repackaged the real Sury arm64 debs as amd64** (relabel `Architecture`,
   strip maintainer scripts, install). First attempt installed with
   `dpkg --unpack` → packages were `install ok unpacked` → **cataloger skipped
   them** → still no detection. Fix: `dpkg --configure` (scripts stripped, so it's
   safe) so they're `install ok installed`.

6. **Repackaged php8.3 / php8.5 on a Debian trixie base still didn't detect** —
   even installed and cataloged. Root cause (§4): **php8.3 and php8.5 are in no
   Debian feed**, and php8.2 on trixie isn't either (it's a bookworm package). The
   repackage was never the blocker here; the **base release's feed** was.

7. **Exact upstream versions are fundamentally undetectable** where the distro
   ships a different patch level. Example: Ubuntu noble's php8.3 fix is
   `8.3.6-0ubuntu0.24.04.13`; installing Sury's `8.3.35` there reads as *newer*
   than the fix (`8.3.35 > 8.3.6`) → treated as already-patched → no match. So the
   detectable 8.3 is Ubuntu's `8.3.6-…12`, not `8.3.35`.

---

## 6. What actually works (verified)

Each PHP branch on the base release whose feed tracks it; vulnerable = a version
below the fix, fixed = the patched version; all **amd64**, PHP as an **OS
package**.

| Branch | Base | Vulnerable image | Fixed image |
|--------|------|------------------|-------------|
| 8.2 | Debian bookworm | `php8.2` **8.2.34** (Sury, repackaged amd64, `installed`) | `php8.2` **8.2.35** (bookworm native) |
| 8.3 | Ubuntu 24.04 | `php8.3` **8.3.6-0ubuntu0.24.04.12** (noble snapshot) | `php8.3` **…04.13** (noble native) |
| 8.4 | Debian trixie | `php8.4` **8.4.26** (trixie snapshot) | `php8.4` **8.4.27** (trixie native) |
| 8.5 | Ubuntu 26.04 | `php8.5` **8.5.4-0ubuntu1.4** (resolute snapshot) | `php8.5` **8.5.4-0ubuntu1.5** (resolute native) |

- **Vulnerable** images → both CVEs fire, Critical, on the `php8.x` package.
- **Fixed** images → clean for both CVEs.
- `8.4.26` and `8.2.34` were confirmed detecting in live scans; 8.3/8.5 are
  native Ubuntu packages (same mechanism as the confirmed 8.4.26) so behave the
  same.

Build recipes for every image are in [`BUILD.md`](BUILD.md); the scan targets are
`images/<tag>/Dockerfile`.

---

## 7. Techniques that made it reproducible

- **Pre-patch native versions** via archive snapshots:
  `snapshot.debian.org/archive/debian/<ts>` (Debian) and
  `snapshot.ubuntu.com/ubuntu/<ts>` (Ubuntu), apt pinned with
  `Acquire::Check-Valid-Until "false"`.
- **Exact upstream version where no amd64 exists** (8.2.34): download the Sury
  arm64 debs, `dpkg-deb -R`, set `Architecture: amd64`, strip maintainer scripts,
  `dpkg-deb --build`, `dpkg --unpack` + `dpkg --configure -a` so state is
  `install ok installed`. Detection-only image (binaries are arm64; it exists to
  be scanned, not run). Matches because `Source: php8.2` and `8.2.34 < 8.2.35` in
  the bookworm feed.
- **Scan built images, not build recipes.** Keep only one-line `FROM <ghcr ref>`
  Dockerfiles in the scanned path; keep the real `FROM debian:…`/`ubuntu:…`
  recipes in `BUILD.md` so a repo scan doesn't pull in the base OS.

---

## 8. How to scan

**Repo / GUI** — enable Container Security on the project; it resolves the eight
`images/<tag>/Dockerfile` references and scans the published images.

**CLI**:
```bash
cx scan create --project-name "php2shell-cx" --branch main -s . \
  --scan-types container-security \
  --container-images "ghcr.io/cxsmtp/php2shell-distro:8.2.34,ghcr.io/cxsmtp/php2shell-distro:8.2.35,\
ghcr.io/cxsmtp/php2shell-distro:8.3-vuln,ghcr.io/cxsmtp/php2shell-distro:8.3-fixed,\
ghcr.io/cxsmtp/php2shell-distro:8.4.26,ghcr.io/cxsmtp/php2shell-distro:8.4.27,\
ghcr.io/cxsmtp/php2shell-distro:8.5-vuln,ghcr.io/cxsmtp/php2shell-distro:8.5-fixed"
```

**For an arm64-only image** (if you ever need the exact-version arm64 build):
```bash
skopeo copy --override-arch arm64 docker://<ref> oci:/path/img:tag
cx scan create ... --container-images "oci-dir:/path/img" --containers-local-resolution
```

---

## 9. Field guidance (for customer conversations)

- **SCA won't flag this** — PHP is the runtime, not a declared dependency.
  **Container Security** is the engine that catches it.
- **Demo on distro-packaged images, not the official `php:` images** — the latter
  won't show the CVEs (source-built blind spot), and the remediation output is
  misleading for this case.
- **PHP on servers/VMs** needs host inventory, not container scanning. Point to the
  fixed versions: **8.5.12 / 8.4.27 / 8.3.36 / 8.2.35**.
- Lead with the vendor's statement: **mod_php highest risk**, demonstrated
  unauthenticated exploit. **Do not claim in-the-wild exploitation** — none
  confirmed.

---

## 10. References

- [PHP advisory (2026 archive)](https://www.php.net/archive/2026.php)
- [Checkmarx Zero — PHP2Noise](https://checkmarx.com/zero-post/php2noise-two-php-critical-rces-patched-as-users-scramble-for-fix-amid-misinformation/)
- Debian security tracker: `security-tracker.debian.org/tracker/CVE-2026-109618`
- Ubuntu security: `ubuntu.com/security/CVE-2026-109618`
- Checkmarx CLI container scanning (flags, `--containers-local-resolution`,
  skopeo OCI workaround): Checkmarx docs.
