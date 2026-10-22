# php8.4 8.4.27 — FIXED. Current Debian trixie. Native amd64.
#   docker build -f images/8.4.27/Dockerfile -t ghcr.io/cxsmtp/php2shell-distro:8.4.27 .
FROM debian:trixie
RUN apt-get update \
 && apt-get install -y --no-install-recommends php8.4 libapache2-mod-php8.4 \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
EXPOSE 80
