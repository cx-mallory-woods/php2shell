# PHP2Shell — Container Security demonstration path

**Audience:** Checkmarx SE / content team
**Date:** 22 Oct 2026
**Tenant used:** DEU (`deu.ast.checkmarx.net`), project `php2shell-cx`

---

## 1. What we set out to prove

Can Checkmarx One **Container Security** detect the two PHP2Shell RCEs in a
container image, and does the official Checkmarx Zero narrative ("SCA misses the
PHP runtime, Container Security catches it") actually hold?

## 2. The vulnerabilities

| CVE | Type | Severity | Fixed in |
|-----|------|----------|----------|
| CVE-2026-109618 | Unauthenticated RCE — out-of-memory corruption | Critical (CVSS 9.2) | 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 |
| CVE-2026-109619 | Unauthenticated RCE — stale global request state | Critical (CVSS 9.2) | 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 |

Released 21 Oct 2026. Any version below the fix on its branch is affected.

## 3. Headline finding (the important part)

**Container Security detects these PHP-core CVEs *only* when PHP is installed as
an OS package (a distro build). It does NOT detect them on the official `php`
Docker images, which compile PHP from source.**

| Image type | Example | CVE-2026-109618/619 detected? |
|------------|---------|-------------------------------|
| Official (source-built) | `php:8.3.35-apache`, `php:8.4.26-fpm` | ❌ No — findings only on Debian OS packages (curl, kernel, …) |
| Distro-packaged (apt) | `php8.4` 8.4.26 on Debian trixie | ✅ Yes — both CVEs, Critical, on the `php8.4` package |

The scanner reads the image's **OS package database**. Source-built PHP has no
entry there, so the runtime is never fingerprinted. Confirming evidence:
Checkmarx's own image-remediation rated the still-vulnerable `php:8.3.35-alpine`
as **"0 vulnerabilities, MOST SECURE"** and suggested "upgrades" to versions
*below* the fix line. So on the official images the demo shows nothing — and the
remediation would steer a customer toward still-vulnerable versions.

## 4. The demonstration path (reproducible)

> Two hard constraints learned the hard way:
> 1. CxOne Container Security scans the image referenced in a `FROM` line **from a
>    registry** — it does **not** execute `RUN` layers. So an `apt install php`
>    Dockerfile must be **built and pushed**, then scanned **by reference**.
> 2. PHP must come from the OS package manager. Debian trixie ships **php8.4**.

### Step 1 — Build a vulnerable (pre-patch) and a fixed distro image

```bash
# Vulnerable: pin apt to the 20 Oct snapshot -> php8.4 8.4.26-1~deb13u1 (pre-fix)
#   FROM debian:trixie
#   sources -> https://snapshot.debian.org/archive/debian/20261020T213255Z
#   apt-get install -y php8.4 libapache2-mod-php8.4      # or php8.4-fpm
#
# Fixed: install from live Debian -> php8.4 8.4.27-1~deb13u1 (patched)
#   apt-get install -y php8.4 libapache2-mod-php8.4
```
Full build recipes per image live in [`images/<version>/Dockerfile`](images/).

### Step 2 — Push to a registry CxOne can pull

```bash
podman push ghcr.io/<org>/php2shell-distro:vuln-modphp   # 8.4.26, apache/mod_php
podman push ghcr.io/<org>/php2shell-distro:vuln-fpm      # 8.4.26, php-fpm
podman push ghcr.io/<org>/php2shell-distro:fixed         # 8.4.27
# make the GHCR package public (or configure a pull credential in the tenant)
```

### Step 3 — Scan by image reference

```bash
cx scan create --project-name "php2shell-cx" --branch main \
  --scan-types "container-security" \
  --container-images "ghcr.io/<org>/php2shell-distro:vuln-modphp,\
ghcr.io/<org>/php2shell-distro:vuln-fpm,\
ghcr.io/<org>/php2shell-distro:fixed"
```
(Keep Dockerfiles out of the scanned repo so a repo scan doesn't pull `FROM`
base images and bury the result in OS-package noise.)

## 5. Results observed

| Scan | Target | Outcome |
|------|--------|---------|
| `e79158c8` | distro `php8.4` 8.4.26 (vuln) | **CVE-2026-109618 + CVE-2026-109619**, Critical, package `php8.4` 8.4.26-1~deb13u1 ✅ |
| `e79158c8` | official `php:8.3.35/8.3.36/8.4.26` | neither CVE (0/3875 findings matched) — OS-package findings only |
| `1607c724` | distro vuln-modphp + vuln-fpm | both CVEs on both ✅ |
| `1607c724` | distro `fixed` (8.4.27) | clean for both CVEs ✅ |

Live demo images (public): `ghcr.io/cxsmtp/php2shell-distro:{vuln-modphp,vuln-fpm,fixed}`.

## 6. Talking points for a customer demo

- **The win:** distro-packaged PHP → both Critical CVEs flagged on the `php8.4`
  package, and the 8.4.27 control comes back clean. Clear before/after.
- **The honest caveat:** on the official source-built `php` images the same CVEs
  do **not** appear, and the remediation guidance is misleading. Lead with the
  distro images; don't demo on `php:*` official tags for these CVEs.
- **SCA vs Container Security:** SCA on the app code finds nothing (PHP is the
  runtime, not a declared dependency) — so Container Security is the right engine,
  *provided* PHP is a distro package.

## 7. Action items for the content team

1. Confirm CVE-2026-109618/109619 are mapped to the distro `php` packages in the
   Container Security feed (verified working for Debian `php8.4` on 22 Oct).
2. Decide whether the source-built official `php` images can be fingerprinted —
   today they are a detection blind spot for PHP-core CVEs.
3. Fix the remediation output that rates vulnerable `php:8.3.35-alpine` as
   "0 vulnerabilities / MOST SECURE."
