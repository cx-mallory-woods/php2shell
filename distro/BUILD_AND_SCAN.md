# Distro-packaged PHP — build, push, and scan

## Why this is a separate workflow

CxOne Container Security, in a repo/source scan, **resolves the image named in
each `FROM` line and scans that published image from a registry**. It does
**not** execute `RUN` instructions. The `distro/Dockerfile` installs PHP via
`apt` in a `RUN` layer, so the `php8.4` package only exists *after a build*. A
plain repo scan of this Dockerfile would just scan bare `debian:trixie` (no PHP).

To make the distro-packaged PHP visible to the scanner you must:

1. **Build** the image (produces the `php8.4` OS package in the layer).
2. **Push** it to a registry CxOne can pull.
3. **Scan the pushed image by reference** — either via a trivial
   `FROM <pushed-ref>` Dockerfile scanned as a repo, or the CLI
   `--container-images <ref>` flag.

> This container has no Docker daemon, so the build must run somewhere that does
> (your laptop, a CI runner, any Docker host).

## Steps

```bash
# 1. Build (run from the repo root so the build context includes app/)
export REF=ghcr.io/cxsmtp/php2shell-distro:prepatch
docker build -f distro/Dockerfile -t "$REF" .

# 2. Push to GHCR (make the package public, or give CxOne registry creds)
echo "$GITHUB_TOKEN" | docker login ghcr.io -u <you> --password-stdin
docker push "$REF"

# 3a. Scan by reference with the cx CLI
cx scan create --project-name "php2shell-cx" -s . --branch main \
  --scan-types "container-security" --container-images "$REF"
```

### 3b. Scan by reference without the CLI

Add a one-line Dockerfile that points at the pushed, already-built image and
commit it, then run a normal repo scan — CxOne will resolve and scan the pushed
image, which now contains the `php8.4` package:

```dockerfile
# distro-scan/Dockerfile
FROM ghcr.io/cxsmtp/php2shell-distro:prepatch
```

Once it's pushed, tell me the reference and I can trigger the CxOne scan on it
from here.

## What to expect (and verify with the content team first)

- **Best case:** the image now carries a `php8.4` OS package, and if the
  Checkmarx feed maps CVE-2026-109618 / CVE-2026-109619 to the Debian `php8.4`
  source package, they appear — critical, with a fixed version. This is the
  working version of the demo.
- **Likely near-term:** the two CVEs are ~1 day old; the Debian security tracker
  and the Checkmarx feed may not map them to the `php8.4` package yet, so they
  may still not show even though the `php` package is now detected. Confirm feed
  coverage before demoing.
- **Either way** you can prove the *mechanism*: distro-packaged PHP produces
  `php8.4` findings in the scan, whereas the source-built official `php` images
  produce **zero** PHP-runtime findings — which is the real, demonstrable gap.

## Snapshot date

`snapshot.debian.org` serves Debian's archive as of a timestamp. The Dockerfile
defaults to `SNAPSHOT=20261015T000000Z` (before the 21 Oct release). For a
patched control, rebuild with a post-21-Oct snapshot:

```bash
docker build -f distro/Dockerfile --build-arg SNAPSHOT=20261101T000000Z \
  -t ghcr.io/cxsmtp/php2shell-distro:patched .
```

Verify the snapshot resolves (`curl -sI http://snapshot.debian.org/archive/debian/20261015T000000Z/`)
and check the installed version with `apt-cache policy php8.4` inside the build.
