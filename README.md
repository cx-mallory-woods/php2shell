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

## Actual scan result (22 Oct 2026, CxOne container-security engine)

The three official `php` images were scanned (API mode, repo scan). Outcome:

- **3,807 findings** returned (186 critical / 1,172 high) — but **every** sampled
  finding is attributed to a **Debian OS package** (`curl`, `linux` kernel, …),
  **not** to PHP.
- **Neither CVE-2026-109618 nor CVE-2026-109619 is present** anywhere in the
  results (searched all 3,807 by ID).
- CxOne's own image-remediation rates **`php:8.3.35-alpine` as "0 vulnerabilities,
  MOST SECURE"** and suggests "upgrading" to `php:8.5.7-zts` / `8.4.22-zts-trixie`
  — all *below* the PHP2Shell fix line (8.5.12 / 8.4.27 / 8.3.36).

**Conclusion: Container Security scores these images on their Debian OS packages
and does not fingerprint the source-built PHP runtime.** A vulnerable 8.3.35
Alpine build could not score 0 if the PHP runtime were tracked. So on the
official `php` images this demo cannot surface the PHP2Shell CVEs, and the
remediation guidance would steer a customer toward still-vulnerable versions.
(Feed lag — the CVEs are ~1 day old — likely compounds this but is not the
primary cause.)

## Getting detection to work: distro-packaged PHP

The only path that gives the scanner a `php` package to match CVEs against is
installing PHP from the OS package manager. See **`distro/Dockerfile`** and
**`distro/BUILD_AND_SCAN.md`** — note it must be **built and pushed to a
registry**, then scanned by reference, because a repo scan does not execute
`RUN` layers. Confirm with the Checkmarx content team whether the feed maps the
two CVEs to the Debian `php8.4` package before relying on it in a customer demo.

**Empirically confirmed (scan `c819e8a5`, 22 Oct 2026):** re-scanning the repo
after adding `distro/Dockerfile` resolved its `FROM debian:trixie` base and
raised the count to 3,875 findings — but still **zero** findings attributed to a
`php` package and still **neither** PHP2Shell CVE. The apt-installed `php8.4`
only exists after a build, which a repo scan never performs. Hence the
build + push + scan-by-reference workflow in `distro/BUILD_AND_SCAN.md`.

### Working detection (scan `e79158c8`, 22 Oct 2026)

The distro image was built with podman (php8.4 **8.4.26-1~deb13u1**, pre-patch,
Debian snapshot `20261020T213255Z`), pushed to
`ghcr.io/cxsmtp/php2shell-distro:prepatch` (public), and scanned via
`distro-scan/Dockerfile`. CxOne pulled it and **both CVEs fired:**

| CVE | Severity | Package | Version |
|-----|----------|---------|---------|
| CVE-2026-109618 | Critical (9.2, CWE-416) | `php8.4` | 8.4.26-1~deb13u1 |
| CVE-2026-109619 | Critical (9.2, CWE-416) | `php8.4` | 8.4.26-1~deb13u1 |

**Bottom line for the demo:** the official source-built `php` images surface
**neither** CVE (PHP runtime not fingerprinted), while the distro-packaged
`php8.4` image surfaces **both** — Checkmarx's feed does map these CVEs to the
Debian `php8.4` package. Detection works only when PHP is an OS package.
