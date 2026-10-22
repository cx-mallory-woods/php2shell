# VULNERABLE: PHP 8.4.26 (fixed in 8.4.27), PHP-FPM SAPI.
# Expected scan result: CVE-2026-109618 + CVE-2026-109619, both critical.
# Build context is the repo root:  docker build -f fpm/Dockerfile -t php2shell-fpm .
FROM php:8.4.26-fpm
COPY app/ /var/www/html/
