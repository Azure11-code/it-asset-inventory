# Generates a self-signed HTTPS cert for this PC's LAN IP.
# Uses a temporary Docker container — no local openssl needed.
#
# Usage:
#   .\scripts\generate-cert.ps1                    # auto-detect LAN IP
#   .\scripts\generate-cert.ps1 -LanIp 192.168.1.50

param(
    [string]$LanIp = ""
)

# Auto-detect if not provided
if (-not $LanIp) {
    $LanIp = (Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -match '^(192\.168\.|10\.)' -and $_.InterfaceAlias -notmatch 'Loopback' } |
        Select-Object -First 1).IPAddress
}

if (-not $LanIp) {
    Write-Host "ERROR: Could not detect LAN IP. Please provide it:" -ForegroundColor Red
    Write-Host "  .\scripts\generate-cert.ps1 -LanIp 192.168.1.50"
    exit 1
}

Write-Host "==> Generating self-signed cert for: $LanIp" -ForegroundColor Cyan

New-Item -ItemType Directory -Force -Path "docker\nginx\certs" | Out-Null

$pwd = (Get-Location).Path.Replace('\', '/')

docker run --rm -v "${pwd}/docker/nginx/certs:/certs" alpine/openssl req -x509 `
    -newkey rsa:2048 -nodes -days 365 `
    -keyout /certs/server.key `
    -out /certs/server.crt `
    -subj "/CN=$LanIp/O=IT Asset Inventory" `
    -addext "subjectAltName=IP:$LanIp,IP:127.0.0.1,DNS:localhost" 2>$null

Write-Host ""
Write-Host "==> Cert generated:" -ForegroundColor Green
Write-Host "     docker\nginx\certs\server.crt"
Write-Host "     docker\nginx\certs\server.key"
Write-Host ""
Write-Host "==> Restart nginx to load the new cert:" -ForegroundColor Yellow
Write-Host "     docker compose restart nginx"
Write-Host ""
Write-Host "==> Access URLs:" -ForegroundColor Cyan
Write-Host "     HTTP:  http://${LanIp}:8081"
Write-Host "     HTTPS: https://${LanIp}:8443   (needed for /scan camera)"
