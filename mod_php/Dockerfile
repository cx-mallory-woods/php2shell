# VULNERABLE: PHP 8.3.35 (fixed in 8.3.36).
# Apache mod_php is the SAPI the PHP team rates highest-risk for these CVEs.
# Expected scan result: CVE-2026-109618 + CVE-2026-109619, both critical.
# Build context is the repo root:  docker build -f mod_php/Dockerfile -t php2shell-modphp .
FROM php:8.3.35-apache
COPY app/ /var/www/html/
EXPOSE 80
