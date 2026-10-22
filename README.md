# php2shell-cx — Container Security detection demo

Demonstrates that **Checkmarx One Container Security** flags the PHP2Shell RCEs on
**distro-packaged** PHP — vulnerable vs fixed — and misses them on the official
source-built `php` images.

## The vulnerabilities (PHP2Shell)

| CVE | Type | Severity |
|-----|------|----------|
| CVE-2026-109618 | Unauthenticated RCE — out-of-memory corruption | Critical (CVSS 9.2) |
| CVE-2026-109619 | Unauthenticated RCE — stale global request state | Critical (CVSS 9.2) |

## Why these bases

Container Security matches a container's OS packages against that **distro
release's own CVE feed**, so each PHP branch must sit on the distro that actually
ships (and tracks) it:

| Branch | Base | Vulnerable (both CVEs) | Fixed (clean) |
|--------|------|------------------------|---------------|
| 8.2 | Debian bookworm | `8.2.34` (php8.2 8.2.34) | `8.2.35` (php8.2 8.2.35) |
| 8.3 | Ubuntu 24.04 | `8.3-vuln` (php8.3 8.3.6-0ubuntu0.24.04.12) | `8.3-fixed` (…04.13) |
| 8.4 | Debian trixie | `8.4.26` (php8.4 8.4.26) | `8.4.27` (php8.4 8.4.27) |
| 8.5 | Ubuntu 26.04 | `8.5-vuln` (php8.5 8.5.4-0ubuntu1.4) | `8.5-fixed` (8.5.4-0ubuntu1.5) |

Published at `ghcr.io/cxsmtp/php2shell-distro:<tag>` (public). Scan targets are
`images/<tag>/Dockerfile` (one-line `FROM` refs); build recipes are in
[`BUILD.md`](BUILD.md).

> **Note on versions.** Debian trixie ships php8.4 and bookworm ships php8.2 at
> near-upstream patch levels, so 8.2/8.4 use the advisory's exact-ish numbers.
> Ubuntu ships php8.3/php8.5 at its *own* patch levels (8.3.6-x, 8.5.4-x), so the
> 8.3/8.5 images are tagged by role, not by upstream version — the upstream
> numbers 8.3.35 / 8.5.11 don't exist in any distro feed and can't be matched.

## How to scan

**Repo / GUI** — enable Container Security on the `php2shell-cx` project and scan;
it resolves the eight `FROM` references in `images/`.

**CLI**:

```bash
cx scan create --project-name "php2shell-cx" --branch main -s . \
  --scan-types container-security \
  --container-images "ghcr.io/cxsmtp/php2shell-distro:8.2.34,ghcr.io/cxsmtp/php2shell-distro:8.2.35,\
ghcr.io/cxsmtp/php2shell-distro:8.3-vuln,ghcr.io/cxsmtp/php2shell-distro:8.3-fixed,\
ghcr.io/cxsmtp/php2shell-distro:8.4.26,ghcr.io/cxsmtp/php2shell-distro:8.4.27,\
ghcr.io/cxsmtp/php2shell-distro:8.5-vuln,ghcr.io/cxsmtp/php2shell-distro:8.5-fixed"
```

Expected: the four `vuln` images fire **CVE-2026-109618** + **CVE-2026-109619**
(Critical) on the `php8.x` package; the four fixed images are clean for them.

## The key finding

- **Distro-packaged PHP** → both CVEs fire, Critical, on the `php8.x` package; the
  fixed releases come back clean.
- **Official source-built `php` images** → **neither CVE** appears. Container
  Security scores those only on their OS packages and does not fingerprint the
  compiled-from-source PHP runtime — its remediation even rates the vulnerable
  `php:8.3.35-alpine` as "0 vulnerabilities."
- **Matching is per distro release.** A PHP branch only matches on the distro that
  ships it (php8.2→bookworm, php8.3→Ubuntu 24.04, php8.4→trixie, php8.5→Ubuntu
  26.04). The exact upstream patch numbers from the advisory aren't what any distro
  ships, so the detectable versions are the distro's.

**Takeaway for the team:** "SCA misses it, Container Security catches it" holds for
distro-packaged PHP on a release that tracks that PHP version — but not for the
official source-built images, and detection is bounded by distro feed coverage.
