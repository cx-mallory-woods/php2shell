# php2shell-cx — Container Security detection demo

Demonstrates that **Checkmarx One Container Security** flags the PHP2Shell RCEs on
**distro-packaged** PHP — across every affected branch, vulnerable vs fixed — and
misses them on the official source-built `php` images.

## The vulnerabilities (PHP2Shell)

| CVE | Type | Severity |
|-----|------|----------|
| CVE-2026-109618 | Unauthenticated RCE — out-of-memory corruption | Critical (CVSS 9.2) |
| CVE-2026-109619 | Unauthenticated RCE — stale global request state | Critical (CVSS 9.2) |

Fixed in **8.5.12 / 8.4.27 / 8.3.36 / 8.2.35** (released 21 Oct 2026). Every
version at or below the last-vulnerable release on each branch is affected.

## The images

For each branch: the **last vulnerable** release and the **fixed** release. Each
`images/<version>/Dockerfile` is the build recipe; all are amd64 so the Checkmarx
analyzer resolves them, and all install PHP as an **OS package** (that's what
Container Security reads).

| Branch | Vulnerable (both CVEs) | Fixed (clean) |
|--------|------------------------|---------------|
| 8.2 | `images/8.2.34` | `images/8.2.35` |
| 8.3 | `images/8.3.35` | `images/8.3.36` |
| 8.4 | `images/8.4.26` | `images/8.4.27` |
| 8.5 | `images/8.5.11` | `images/8.5.12` |

Prebuilt and published at `ghcr.io/cxsmtp/php2shell-distro:<version>` (public).

### How each is built

- **Fixed (8.2.35 / 8.3.36 / 8.5.12):** current Sury repo (`deb.sury.org/php`).
- **8.4.27 (fixed):** current Debian trixie.
- **8.4.26 (vulnerable):** Debian trixie, apt pinned to the 20 Oct 2026 snapshot.
- **8.2.34 / 8.3.35 / 8.5.11 (vulnerable):** no amd64 build of these old patch
  releases is published anymore, so the real Sury packages for those exact versions
  are repackaged to amd64 and marked `installed`. The package DB lists the exact
  vulnerable `php8.x` (with `Source: php8.x`, which Checkmarx matches against the
  Debian CVE feed). **Detection-only** images — the binaries are arm64 and won't
  run; they exist to be scanned.

## How to scan

> **Important:** these Dockerfiles are **build recipes**. A plain repo scan would
> resolve the `FROM debian:trixie` base and *not* execute the `RUN` layers, so it
> would not see the PHP packages. Scan the **built/pushed images by reference**.

Build + push (or just use the already-published tags):

```bash
for v in 8.2.34 8.2.35 8.3.35 8.3.36 8.4.26 8.4.27 8.5.11 8.5.12; do
  docker build -f images/$v/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:$v .
  docker push ghcr.io/cxsmtp/php2shell-distro:$v
done
```

Scan them by reference:

```bash
cx scan create --project-name "php2shell-cx" --branch main -s . \
  --scan-types container-security \
  --container-images "ghcr.io/cxsmtp/php2shell-distro:8.2.34,ghcr.io/cxsmtp/php2shell-distro:8.2.35,\
ghcr.io/cxsmtp/php2shell-distro:8.3.35,ghcr.io/cxsmtp/php2shell-distro:8.3.36,\
ghcr.io/cxsmtp/php2shell-distro:8.4.26,ghcr.io/cxsmtp/php2shell-distro:8.4.27,\
ghcr.io/cxsmtp/php2shell-distro:8.5.11,ghcr.io/cxsmtp/php2shell-distro:8.5.12"
```

Expected: the four vulnerable images fire **CVE-2026-109618** and
**CVE-2026-109619** (Critical) on the `php8.x` package; the four fixed images are
clean for them.

## The key finding

- **Distro-packaged PHP** → both CVEs fire, Critical, on the `php8.x` package; the
  fixed releases come back clean.
- **Official source-built `php` images** (`php:8.3.35-apache`, `php:8.4.26-fpm`, …)
  → **neither CVE** appears. Container Security scores those only on their Debian OS
  packages and does not fingerprint the compiled-from-source PHP runtime — its
  remediation even rates the vulnerable `php:8.3.35-alpine` as "0 vulnerabilities."

**Takeaway for the team:** "SCA misses it, Container Security catches it" holds for
distro-packaged PHP — but **not** for the official source-built images on these
PHP-core CVEs. Confirm feed coverage with the Checkmarx content team before a
customer demo.
