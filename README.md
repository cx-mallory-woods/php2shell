# php2shell-cx — Container Security detection demo

Shows that **Checkmarx One Container Security** flags the PHP2Shell CVEs only
when PHP is installed as an **OS package** (distro build), and misses them on the
official source-built `php` images.

> **No Dockerfiles live in this repo on purpose.** A repo scan resolves `FROM`
> base images and pulls in hundreds of unrelated OS-package findings. To keep a
> scan clean, the demo images are pre-built and pushed to GHCR, and scanned
> **by image reference** (not from a Dockerfile here). Build recipe:
> [`distro/BUILD_AND_SCAN.md`](distro/BUILD_AND_SCAN.md).

## The vulnerabilities

| CVE | Type | Severity | Fixed in |
|-----|------|----------|----------|
| CVE-2026-109618 | Unauth RCE — out-of-memory corruption | Critical 9.2 | 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 |
| CVE-2026-109619 | Unauth RCE — stale global request state | Critical 9.2 | 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 |

## Demo images (GHCR, public)

Scan these by reference (e.g. `cx scan create --scan-types container-security
--container-images <ref>`):

| Image | PHP | Result |
|-------|-----|--------|
| `ghcr.io/cxsmtp/php2shell-distro:vuln-modphp` | php8.4 **8.4.26** (apache/mod_php) | **Vulnerable** — both CVEs |
| `ghcr.io/cxsmtp/php2shell-distro:vuln-fpm` | php8.4-fpm **8.4.26** | **Vulnerable** — both CVEs |
| `ghcr.io/cxsmtp/php2shell-distro:fixed` | php8.4 **8.4.27** | **Clean** for both CVEs |

(`:prepatch` is an earlier alias of `:vuln-modphp`.) All are Debian-trixie based;
the vulnerable ones pin apt to the 20 Oct 2026 snapshot (`20261020T213255Z`) to
get the pre-patch `php8.4` 8.4.26-1~deb13u1.

## The finding (confirmed by scan `e79158c8`)

- **Distro-packaged PHP** → both CVEs fire, Critical, attributed to the `php8.4`
  package. ✅
- **Official source-built `php` images** (`php:8.3.35-apache`, `php:8.3.36`,
  `php:8.4.26-fpm`) → **neither CVE** appears. Container Security scores those
  images only on their Debian OS packages and does not fingerprint the
  compiled-from-source PHP runtime. Its remediation even rates the vulnerable
  `php:8.3.35-alpine` as "0 vulnerabilities."

**Takeaway for the team:** "SCA misses it, Container Security catches it" holds
for distro-packaged PHP — but **not** for the official source-built images on
these PHP-core CVEs. Confirm feed coverage with the Checkmarx content team before
a customer demo.
