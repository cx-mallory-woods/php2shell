# Scan target: the already-BUILT, pushed distro image (php8.4 8.4.26-1~deb13u1,
# pre-patch). CxOne Container Security resolves this FROM reference and scans the
# pushed image — which, unlike a RUN-layer install, actually contains the php8.4
# OS package. Requires CxOne to be able to pull the image (package public, or a
# GHCR pull credential configured in the tenant).
FROM ghcr.io/cxsmtp/php2shell-distro:prepatch
