# php2shell-cx — Container Security detection demo

Demonstrates that **Checkmarx One Container Security** flags the PHP2Shell RCEs on
**distro-packaged** PHP — vulnerable vs fixed — and misses them on the official
source-built `php` images.

## The vulnerabilities (PHP2Shell)

| CVE | Type | Severity |
|-----|------|----------|
| CVE-2026-109618 | Unauthenticated RCE — out-of-memory corruption | Critical (CVSS 9.2) |
| CVE-2026-109619 | Unauthenticated RCE — stale global request state | Critical (CVSS 9.2) |

## Why these two branches

Container Security matches a container's OS packages against that **distro
release's own CVE feed**. For these CVEs the Debian tracker lists PHP only as:

- **`php8.2` in bookworm** (fixed 8.2.35)
- **`php8.4` in trixie** (fixed 8.4.27)

`php8.3` and `php8.5` were never shipped as Debian source packages, so no Debian
feed tracks them and Container Security cannot match them on a Debian image — so
the demo covers the two detectable branches, each on the base release where its
PHP is tracked.

## The images

| Branch | Base | Vulnerable (both CVEs) | Fixed (clean) |
|--------|------|------------------------|---------------|
| 8.2 | Debian bookworm | `8.2.34` | `8.2.35` |
| 8.4 | Debian trixie | `8.4.26` | `8.4.27` |

Published at `ghcr.io/cxsmtp/php2shell-distro:<version>` (public). Scan targets are
`images/<version>/Dockerfile` (one-line `FROM` refs); build recipes are in
[`BUILD.md`](BUILD.md).

### How each is built

- **8.2.35 / 8.4.27 (fixed):** native current Debian (bookworm / trixie).
- **8.4.26 (vulnerable):** Debian trixie, apt pinned to the 20 Oct 2026 snapshot.
- **8.2.34 (vulnerable):** no amd64 build of that exact patch is published, so the
  real Sury `php8.2` 8.2.34 packages are repackaged to amd64 and marked
  `installed` on a bookworm image. `8.2.34 < 8.2.35` in the bookworm feed →
  flagged. Detection-only image (binaries are arm64, won't run).

## How to scan

**Repo / GUI** — enable Container Security on the `php2shell-cx` project and scan;
it resolves the four `FROM` references in `images/`.

**CLI**:

```bash
cx scan create --project-name "php2shell-cx" --branch main -s . \
  --scan-types container-security \
  --container-images "ghcr.io/cxsmtp/php2shell-distro:8.2.34,ghcr.io/cxsmtp/php2shell-distro:8.2.35,\
ghcr.io/cxsmtp/php2shell-distro:8.4.26,ghcr.io/cxsmtp/php2shell-distro:8.4.27"
```

Expected: `8.2.34` and `8.4.26` fire **CVE-2026-109618** + **CVE-2026-109619**
(Critical) on the `php8.x` package; `8.2.35` and `8.4.27` are clean for them.

## The key finding

- **Distro-packaged PHP** → both CVEs fire, Critical, on the `php8.x` package; the
  fixed releases come back clean.
- **Official source-built `php` images** → **neither CVE** appears. Container
  Security scores those only on their Debian OS packages and does not fingerprint
  the compiled-from-source PHP runtime — its remediation even rates the vulnerable
  `php:8.3.35-alpine` as "0 vulnerabilities."
- **Feed coverage is per distro release.** Only PHP versions a distro actually
  ships (php8.2/bookworm, php8.4/trixie) can be matched; php8.3 / php8.5 have no
  distro feed and cannot be detected by container scanning at all.

**Takeaway for the team:** "SCA misses it, Container Security catches it" holds for
distro-packaged PHP on a release that tracks that PHP version — but not for the
official source-built images, and not for PHP branches no distro ships.
