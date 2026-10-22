# DISTRO-PACKAGED PHP — the only path that gives the scanner a `php` OS-package
# entry to match PHP CVEs against. The official php:* images compile PHP from
# source, so Container Security (which reads the OS package database) never sees
# the PHP runtime and cannot flag PHP-core CVEs on them.
#
# Debian trixie ships PHP 8.4 as the `php8.4` apt package. NOTE: Debian's package
# version (e.g. 8.4.x-Ndeb13uM) will NOT match upstream 8.4.26/8.4.27 exactly;
# whether it is pre- or post-patch depends on the apt snapshot date (see
# BUILD_AND_SCAN.md). The dated base tag the earlier draft used
# (debian:trixie-20261001) does NOT exist on Docker Hub — verified. Use the
# plain tag and pin the apt index via snapshot.debian.org instead.
#
# IMPORTANT: CxOne Container Security resolves and scans the image named in a
# FROM line from a registry — it does NOT execute RUN layers during a repo scan.
# So this `RUN apt-get install` only becomes visible after you BUILD this image
# and PUSH it to a registry, then scan that pushed reference. See
# distro/BUILD_AND_SCAN.md for the full workflow.
FROM debian:trixie

# Pin apt to a snapshot from before the 21 Oct 2026 PHP release so the installed
# php8.4 is a pre-patch build (adjust the date if you want a patched control).
# Verified: 20261020T213255Z installs php8.4 8.4.26-1~deb13u1 (pre-patch; the
# Debian security update for the PHP2Shell CVEs would raise this version).
ARG SNAPSHOT=20261020T213255Z
RUN printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/10no-check-valid \
    && sed -i 's|http://deb.debian.org/debian|http://snapshot.debian.org/archive/debian/'"$SNAPSHOT"'|g' \
         /etc/apt/sources.list.d/debian.sources 2>/dev/null || true

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
         php8.4 libapache2-mod-php8.4 \
    && rm -rf /var/lib/apt/lists/*

COPY app/ /var/www/html/
EXPOSE 80
