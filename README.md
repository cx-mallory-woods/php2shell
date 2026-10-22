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

## Demo images (GHCR, public)

For each branch: the **last vulnerable** release and the **fixed** release. All
are amd64 so the Checkmarx analyzer resolves them.

| Branch | Vulnerable (expect both CVEs) | Fixed (expect clean) |
|--------|-------------------------------|----------------------|
| 8.2 | `ghcr.io/cxsmtp/php2shell-distro:8.2.34` | `ghcr.io/cxsmtp/php2shell-distro:8.2.35` |
| 8.3 | `ghcr.io/cxsmtp/php2shell-distro:8.3.35` | `ghcr.io/cxsmtp/php2shell-distro:8.3.36` |
| 8.4 | `ghcr.io/cxsmtp/php2shell-distro:8.4.26` | `ghcr.io/cxsmtp/php2shell-distro:8.4.27` |
| 8.5 | `ghcr.io/cxsmtp/php2shell-distro:8.5.11` | `ghcr.io/cxsmtp/php2shell-distro:8.5.12` |

## How to scan

**Repo / GUI** — the repo carries one `FROM` Dockerfile per image under
`images/<version>/`, so a CxOne project scan with Container Security enabled
resolves and scans all eight by reference.

**CLI** — scan the references directly:

```bash
cx scan create --project-name "php2shell-cx" --branch main -s . \
  --scan-types container-security \
  --container-images "ghcr.io/cxsmtp/php2shell-distro:8.2.34,ghcr.io/cxsmtp/php2shell-distro:8.2.35,\
ghcr.io/cxsmtp/php2shell-distro:8.3.35,ghcr.io/cxsmtp/php2shell-distro:8.3.36,\
ghcr.io/cxsmtp/php2shell-distro:8.4.26,ghcr.io/cxsmtp/php2shell-distro:8.4.27,\
ghcr.io/cxsmtp/php2shell-distro:8.5.11,ghcr.io/cxsmtp/php2shell-distro:8.5.12"
```

## How the images are built

All are Debian-trixie based, PHP installed as an **OS package** (that's what
Container Security reads). Full recipe: [`distro/BUILD_AND_SCAN.md`](distro/BUILD_AND_SCAN.md).

- **Fixed (8.2.35 / 8.3.36 / 8.4.27 / 8.5.12):** installed from the current Sury
  repo (`deb.sury.org/php`) — these are the patched releases.
- **8.4.26 (vulnerable):** Debian trixie with apt pinned to the 20 Oct 2026
  snapshot (`20261020T213255Z`), giving pre-patch `php8.4` 8.4.26.
- **8.2.34 / 8.3.35 / 8.5.11 (vulnerable):** no amd64 build of these old patch
  releases is published anymore (Sury keeps only the current/fixed amd64; Debian
  doesn't track exact upstream patches). So the real Sury packages for those exact
  versions are **repackaged to amd64** — the OS package database lists the exact
  vulnerable `php8.x` at the right version (with `Source: php8.x`, which is what
  Checkmarx matches the Debian CVE against). These are detection-only images; the
  binaries underneath are arm64 and won't run.

## The key finding

- **Distro-packaged PHP** → both CVEs fire, Critical, attributed to the `php8.x`
  package. The fixed releases come back clean. ✅ (proven on `8.4.26`, scan
  `e79158c8`.)
- **Official source-built `php` images** (`php:8.3.35-apache`, `php:8.4.26-fpm`,
  …) → **neither CVE** appears. Container Security scores those only on their
  Debian OS packages and does not fingerprint the compiled-from-source PHP
  runtime — its remediation even rates the vulnerable `php:8.3.35-alpine` as
  "0 vulnerabilities."

**Takeaway for the team:** "SCA misses it, Container Security catches it" holds
for distro-packaged PHP — but **not** for the official source-built images on
these PHP-core CVEs. Confirm feed coverage with the Checkmarx content team before
a customer demo.
