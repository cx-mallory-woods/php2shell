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
