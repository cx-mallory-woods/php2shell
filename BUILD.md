# Build recipes

How each published `ghcr.io/cxsmtp/php2shell-distro:<version>` image is built.
Kept as a document (not as `Dockerfile`s) so a Checkmarx repo scan does not
resolve their `FROM` base — only the `images/` scan targets are scanned.

The demo covers the two branches whose PHP package is in a Debian release's CVE
feed, so Container Security can match them: **php8.2 (bookworm)** and
**php8.4 (trixie)**.

## 8.2.35 — FIXED (Debian bookworm, native)
```dockerfile
FROM debian:bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends php8.2 libapache2-mod-php8.2 \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.2.34 — VULNERABLE (Debian bookworm + Sury php8.2 8.2.34, repackaged amd64)
No amd64 build of php8.2 8.2.34 is published, so the real Sury arm64 packages are
repackaged to amd64 and marked `installed`. On a bookworm image the Debian feed
has php8.2 (fixed 8.2.35), so 8.2.34 is flagged vulnerable. Detection-only image.
```dockerfile
FROM debian:bookworm
ARG VER=8.2.34-1%2B0~20260924.93%2Bdebian12~1.gbpa3951d
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends ca-certificates curl dpkg-dev; \
    BASE="https://packages.sury.org/php/pool/main/p/php8.2"; \
    mkdir -p /tmp/out; cd /tmp; \
    for p in php8.2-common php8.2-opcache php8.2-readline php8.2-cli libapache2-mod-php8.2; do \
      curl -fsSL "${BASE}/${p}_${VER}_arm64.deb" -o in.deb || { echo "skip ${p}"; continue; }; \
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

## 8.4.27 — FIXED (Debian trixie, native)
```dockerfile
FROM debian:trixie
RUN apt-get update \
 && apt-get install -y --no-install-recommends php8.4 libapache2-mod-php8.4 \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.4.26 — VULNERABLE (Debian trixie, apt pinned to 20 Oct 2026 snapshot)
```dockerfile
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

## 8.3-fixed — FIXED (Ubuntu 24.04, native → php8.3 8.3.6-0ubuntu0.24.04.13)
```dockerfile
FROM ubuntu:24.04
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends php8.3 libapache2-mod-php8.3 \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.3-vuln — VULNERABLE (Ubuntu 24.04, apt pinned to 20 Oct 2026 snapshot → php8.3 8.3.6-0ubuntu0.24.04.12)
```dockerfile
FROM ubuntu:24.04
RUN set -eux; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/10snapshot; \
    rm -f /etc/apt/sources.list /etc/apt/sources.list.d/*; \
    printf 'Types: deb\nURIs: https://snapshot.ubuntu.com/ubuntu/20261020T000000Z\nSuites: noble noble-updates noble-security\nComponents: main universe\nSigned-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n' > /etc/apt/sources.list.d/ubuntu.sources; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends php8.3 libapache2-mod-php8.3; \
    rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.5-fixed — FIXED (Ubuntu 26.04, native → php8.5 8.5.4-0ubuntu1.5)
```dockerfile
FROM ubuntu:26.04
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends php8.5 libapache2-mod-php8.5 \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```

## 8.5-vuln — VULNERABLE (Ubuntu 26.04, apt pinned to 20 Oct 2026 snapshot → php8.5 8.5.4-0ubuntu1.4)
```dockerfile
FROM ubuntu:26.04
RUN set -eux; \
    printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/10snapshot; \
    rm -f /etc/apt/sources.list /etc/apt/sources.list.d/*; \
    printf 'Types: deb\nURIs: https://snapshot.ubuntu.com/ubuntu/20261020T000000Z\nSuites: resolute resolute-updates resolute-security\nComponents: main universe\nSigned-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n' > /etc/apt/sources.list.d/ubuntu.sources; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends php8.5 libapache2-mod-php8.5; \
    rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
```
