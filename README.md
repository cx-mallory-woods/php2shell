# php2shell-cx — Container Security detection demo

Demonstrates that **Checkmarx One Container Security** flags a known-vulnerable
PHP *runtime* shipped inside a container image. This is a **detection demo**, not
an exploit: nothing here triggers or weaponizes the vulnerabilities. The flaws
live in the PHP runtime from the base image, not in the application code.

## The vulnerabilities (PHP2Shell)

| CVE | Type | Severity |
|-----|------|----------|
| CVE-2026-109618 | Unauthenticated RCE via out-of-memory corruption | CVSS v4.0 9.2 |
| CVE-2026-109619 | Unauthenticated RCE via stale global request state | CVSS v4.0 9.2 |

Fixed in **PHP 8.5.12, 8.4.27, 8.3.36, 8.2.35** (released 21 Oct 2026).
Any version below the fix on its branch is affected. Updating is the only
complete fix — config workarounds do not fully mitigate.

## Images

| Dir | Base image | PHP | Expected result |
|-----|-----------|-----|-----------------|
| `mod_php/` | `php:8.3.35-apache` | 8.3.35 | **Vulnerable** — both CVEs (mod_php = highest-risk SAPI) |
| `fpm/` | `php:8.4.26-fpm` | 8.4.26 | **Vulnerable** — both CVEs (PHP-FPM) |
| `patched/` | `php:8.3.36-apache` | 8.3.36 | **Clean** for both CVEs (control) |

All build with the **repo root as the build context**:

```bash
docker build -f mod_php/Dockerfile  -t php2shell-modphp  .
docker build -f fpm/Dockerfile      -t php2shell-fpm     .
docker build -f patched/Dockerfile  -t php2shell-patched .
```

## Scanning

### Option A — scan the base images directly (no build needed)

```bash
cx scan create --project-name "php2shell-cx" -s . --branch main \
  --scan-types "container-security" \
  --container-images "php:8.3.35-apache,php:8.4.26-fpm,php:8.3.36-apache"
```

> Confirm flag names with `cx scan create --help`; they vary by CLI version.

### Option B — repo scan in CxOne

Push this repo, create a CxOne project with Container Security enabled, and
scan. The engine reads the `FROM` lines in each Dockerfile and resolves the
base-image PHP runtime.

## What a good demo shows

- **Vulnerable images** (`8.3.35`, `8.4.26`): flagged with both CVEs, critical,
  fix version shown.
- **Patched control** (`8.3.36`): clean for both CVEs.
- **Talking point:** SCA on the app code finds nothing — PHP is the *runtime*,
  not a declared dependency. Container Security is what catches it.

## If the vulnerable images come back clean

Two likely causes, both worth confirming with the Checkmarx content team before
demoing to a customer:

1. **CVE-feed lag** — the fix shipped 21 Oct 2026; the CVEs may not be in the
   Container Security vulnerability DB yet.
2. **Source-built PHP** — the official `php` images compile PHP from source
   rather than from an OS package. If detection relies on the OS package DB, a
   source-built runtime may not be identified. (An optional distro-packaged
   variant — e.g. Debian `php8.x` from a pre-21-Oct snapshot — gives better
   odds, at the cost of version numbers that won't match upstream exactly.)
