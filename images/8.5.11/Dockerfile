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
