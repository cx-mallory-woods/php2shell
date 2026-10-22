# Build recipes

How each published `ghcr.io/cxsmtp/php2shell-distro:<version>` image is built.
These are kept as a document (not as `Dockerfile`s) so a Checkmarx repo scan
does not resolve their `FROM debian:trixie` base — only the `images/` scan
targets (which point at the published images) are scanned.

Build from the repo root, e.g.:
```bash
docker build -f - -t ghcr.io/cxsmtp/php2shell-distro:8.3.35 . < <recipe below>
```

## 8.2.34

```dockerfile
# php8.2 8.2.34 — VULNERABLE. No amd64 build of this old patch is published anymore
# (Sury keeps only the current/fixed amd64), so the real Sury arm64 packages for
# this exact version are repackaged to amd64 and marked 'installed'. The OS package
# DB then lists the exact vulnerable php8.2 (Source: php8.2) that Checkmarx matches
# against the Debian CVE feed. DETECTION-ONLY image: the binaries are arm64 and
# will not run — it exists to be scanned, not executed.
#   docker build -f images/8.2.34/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.2.34 .
FROM debian:trixie
ARG VER=8.2.34-1%2B0~20260924.93%2Bdebian13~1.gbpa3951d
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl dpkg-dev; \
    BASE="https://packages.sury.org/php/pool/main/p/php8.2"; \
    mkdir -p /tmp/out; cd /tmp; \
    for p in php8.2-common php8.2-opcache php8.2-readline php8.2-cli libapache2-mod-php8.2; do \
      curl -fsSL "${BASE}/${p}_${VER}_arm64.deb" -o in.deb || { echo "skip ${p} (not published)"; continue; }; \
      dpkg-deb -R in.deb "x_${p}"; \
      sed -i 's/^Architecture: arm64/Architecture: amd64/' "x_${p}/DEBIAN/control"; \
      rm -f "x_${p}/DEBIAN/preinst" "x_${p}/DEBIAN/postinst" "x_${p}/DEBIAN/prerm" "x_${p}/DEBIAN/postrm"; \
      dpkg-deb --build --nocheck "x_${p}" "/tmp/out/${p}.deb"; \
    done; \
    dpkg --unpack --force-depends /tmp/out/*.deb || true; \
    dpkg --configure -a --force-depends || true; \
    sed -i 's/^Status: install ok unpacked/Status: install ok installed/' /var/lib/dpkg/status; \
    rm -rf /var/lib/apt/lists/* /tmp/out /tmp/x_* /tmp/in.deb
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.2.35

```dockerfile
# php8.2 8.2.35 — FIXED. Current Sury repo (deb.sury.org/php). Native amd64.
#   docker build -f images/8.2.35/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.2.35 .
FROM debian:trixie
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl; \
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg; \
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php trixie main" > /etc/apt/sources.list.d/sury-php.list; \
    apt-get update; apt-get install -y --no-install-recommends php8.2 libapache2-mod-php8.2; \
    rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.3.35

```dockerfile
# php8.3 8.3.35 — VULNERABLE. No amd64 build of this old patch is published anymore
# (Sury keeps only the current/fixed amd64), so the real Sury arm64 packages for
# this exact version are repackaged to amd64 and marked 'installed'. The OS package
# DB then lists the exact vulnerable php8.3 (Source: php8.3) that Checkmarx matches
# against the Debian CVE feed. DETECTION-ONLY image: the binaries are arm64 and
# will not run — it exists to be scanned, not executed.
#   docker build -f images/8.3.35/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.3.35 .
FROM debian:trixie
ARG VER=8.3.35-1%2B0~20260925.87%2Bdebian13~1.gbp19383d
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl dpkg-dev; \
    BASE="https://packages.sury.org/php/pool/main/p/php8.3"; \
    mkdir -p /tmp/out; cd /tmp; \
    for p in php8.3-common php8.3-opcache php8.3-readline php8.3-cli libapache2-mod-php8.3; do \
      curl -fsSL "${BASE}/${p}_${VER}_arm64.deb" -o in.deb || { echo "skip ${p} (not published)"; continue; }; \
      dpkg-deb -R in.deb "x_${p}"; \
      sed -i 's/^Architecture: arm64/Architecture: amd64/' "x_${p}/DEBIAN/control"; \
      rm -f "x_${p}/DEBIAN/preinst" "x_${p}/DEBIAN/postinst" "x_${p}/DEBIAN/prerm" "x_${p}/DEBIAN/postrm"; \
      dpkg-deb --build --nocheck "x_${p}" "/tmp/out/${p}.deb"; \
    done; \
    dpkg --unpack --force-depends /tmp/out/*.deb || true; \
    dpkg --configure -a --force-depends || true; \
    sed -i 's/^Status: install ok unpacked/Status: install ok installed/' /var/lib/dpkg/status; \
    rm -rf /var/lib/apt/lists/* /tmp/out /tmp/x_* /tmp/in.deb
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.3.36

```dockerfile
# php8.3 8.3.36 — FIXED. Current Sury repo (deb.sury.org/php). Native amd64.
#   docker build -f images/8.3.36/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.3.36 .
FROM debian:trixie
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl; \
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg; \
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php trixie main" > /etc/apt/sources.list.d/sury-php.list; \
    apt-get update; apt-get install -y --no-install-recommends php8.3 libapache2-mod-php8.3; \
    rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.4.26

```dockerfile
# php8.4 8.4.26 — VULNERABLE. Debian trixie with apt pinned to the 20 Oct 2026
# snapshot (before the 21 Oct fix). Native amd64.
#   docker build -f images/8.4.26/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.4.26 .
FROM debian:trixie
ARG SNAPSHOT=20261020T213255Z
RUN set -eux; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/10snapshot; \
    printf 'Types: deb\nURIs: https://snapshot.debian.org/archive/debian/%s\nSuites: trixie trixie-updates\nComponents: main\nSigned-By: /usr/share/keyrings/debian-archive-keyring.pgp\n\nTypes: deb\nURIs: https://snapshot.debian.org/archive/debian-security/%s\nSuites: trixie-security\nComponents: main\nSigned-By: /usr/share/keyrings/debian-archive-keyring.pgp\n' "$SNAPSHOT" "$SNAPSHOT" > /etc/apt/sources.list.d/debian.sources; \
    apt-get update; \
    apt-get install -y --no-install-recommends php8.4 libapache2-mod-php8.4; \
    rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.4.27

```dockerfile
# php8.4 8.4.27 — FIXED. Current Debian trixie. Native amd64.
#   docker build -f images/8.4.27/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.4.27 .
FROM debian:trixie
RUN apt-get update \
 && apt-get install -y --no-install-recommends php8.4 libapache2-mod-php8.4 \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.5.11

```dockerfile
# php8.5 8.5.11 — VULNERABLE. No amd64 build of this old patch is published anymore
# (Sury keeps only the current/fixed amd64), so the real Sury arm64 packages for
# this exact version are repackaged to amd64 and marked 'installed'. The OS package
# DB then lists the exact vulnerable php8.5 (Source: php8.5) that Checkmarx matches
# against the Debian CVE feed. DETECTION-ONLY image: the binaries are arm64 and
# will not run — it exists to be scanned, not executed.
#   docker build -f images/8.5.11/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.5.11 .
FROM debian:trixie
ARG VER=8.5.11-1%2B0~20260924.26%2Bdebian13~1.gbpbcb504
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl dpkg-dev; \
    BASE="https://packages.sury.org/php/pool/main/p/php8.5"; \
    mkdir -p /tmp/out; cd /tmp; \
    for p in php8.5-common php8.5-opcache php8.5-readline php8.5-cli libapache2-mod-php8.5; do \
      curl -fsSL "${BASE}/${p}_${VER}_arm64.deb" -o in.deb || { echo "skip ${p} (not published)"; continue; }; \
      dpkg-deb -R in.deb "x_${p}"; \
      sed -i 's/^Architecture: arm64/Architecture: amd64/' "x_${p}/DEBIAN/control"; \
      rm -f "x_${p}/DEBIAN/preinst" "x_${p}/DEBIAN/postinst" "x_${p}/DEBIAN/prerm" "x_${p}/DEBIAN/postrm"; \
      dpkg-deb --build --nocheck "x_${p}" "/tmp/out/${p}.deb"; \
    done; \
    dpkg --unpack --force-depends /tmp/out/*.deb || true; \
    dpkg --configure -a --force-depends || true; \
    sed -i 's/^Status: install ok unpacked/Status: install ok installed/' /var/lib/dpkg/status; \
    rm -rf /var/lib/apt/lists/* /tmp/out /tmp/x_* /tmp/in.deb
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.5.12

```dockerfile
# php8.5 8.5.12 — FIXED. Current Sury repo (deb.sury.org/php). Native amd64.
#   docker build -f images/8.5.12/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.5.12 .
FROM debian:trixie
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl; \
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg; \
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php trixie main" > /etc/apt/sources.list.d/sury-php.list; \
    apt-get update; apt-get install -y --no-install-recommends php8.5 libapache2-mod-php8.5; \
    rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```
