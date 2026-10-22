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
