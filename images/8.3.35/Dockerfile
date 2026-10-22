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
