#!/usr/bin/env bash
# Generates a self-signed HTTPS cert for this PC's LAN IP.
# Uses a temporary Docker container so you don't need openssl installed.
#
# Usage:
#   ./scripts/generate-cert.sh                    # auto-detect LAN IP
#   ./scripts/generate-cert.sh 192.168.1.50       # use a specific IP

set -e

LAN_IP="${1:-}"

# Auto-detect if not provided
if [ -z "$LAN_IP" ]; then
    if command -v ipconfig >/dev/null 2>&1; then
        # Windows (Git Bash)
        LAN_IP=$(ipconfig | grep -E "IPv4.*: " | grep -oE "192\.168\.[0-9]+\.[0-9]+|10\.[0-9]+\.[0-9]+\.[0-9]+" | head -1)
    elif command -v hostname >/dev/null 2>&1; then
        # Linux
        LAN_IP=$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E "^(192\.168|10\.)" | head -1)
    fi
fi

if [ -z "$LAN_IP" ]; then
    echo "ERROR: Could not detect LAN IP. Please provide as argument:"
    echo "  ./scripts/generate-cert.sh 192.168.1.50"
    exit 1
fi

echo "==> Generating self-signed cert for: $LAN_IP"

mkdir -p docker/nginx/certs

# Use alpine/openssl container so no local openssl needed
docker run --rm -v "$(pwd)/docker/nginx/certs:/certs" alpine/openssl req -x509 \
    -newkey rsa:2048 -nodes -days 365 \
    -keyout /certs/server.key \
    -out /certs/server.crt \
    -subj "/CN=$LAN_IP/O=IT Asset Inventory" \
    -addext "subjectAltName=IP:$LAN_IP,IP:127.0.0.1,DNS:localhost" 2>/dev/null

echo "==> Cert generated:"
echo "     docker/nginx/certs/server.crt"
echo "     docker/nginx/certs/server.key"
echo ""
echo "==> Restart nginx to load the new cert:"
echo "     docker compose restart nginx"
echo ""
echo "==> Access URLs:"
echo "     HTTP:  http://$LAN_IP:8081"
echo "     HTTPS: https://$LAN_IP:8443   (needed for /scan camera)"
