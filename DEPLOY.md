# Deployment Guide — Production PC Setup

Step-by-step para mag-deploy ng IT Asset Inventory sa **ibang PC** (production).
Kailangan lang naka-install si **Docker Desktop** sa target PC.

---

## Prerequisites sa Production PC

- ✅ **Docker Desktop** installed and running
- ✅ **Git** (para mag-clone) OR ability to copy files via USB/network share
- ✅ Same LAN as the phones/tablets na gagamit ng scan feature
- ✅ Windows Firewall permissions (details below)
- ✅ (Optional) **Google Gemini API key** if you want to enable the AI Assistant chat widget

---

## Step 1 — Get the project files sa target PC

**Option A: Git clone (recommended)**
```bash
git clone <repo-url> C:\it-asset-inventory
cd C:\it-asset-inventory
```

**Option B: Copy via USB/network share**
- Zip the whole project folder from your dev PC
- Copy to target PC (e.g., `C:\it-asset-inventory`)
- Extract

---

## Step 2 — Configure environment

Sa target PC's project folder, gumawa ng `.env` file:

```bash
cp .env.example .env
```

Then i-edit yung `.env` — palitan ang **APP_URL** para may correct LAN IP ng production PC:

```env
APP_URL=http://192.168.1.50:8081     # ← palitan ng actual LAN IP
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=it_asset_inventory
DB_USERNAME=laravel
DB_PASSWORD=secret
DB_ROOT_PASSWORD=root

# ── AI Assistant (optional — floating chat widget uses Google Gemini) ──
# Kunin dito: https://aistudio.google.com/apikey (free — 15 requests/min quota)
# DAPAT AI Studio key ito — nagsisimula sa "AIza", 39 characters. Ang key na galing
# sa ibang Google product (hal. nagsisimula sa "AQ.") ay hindi tatanggapin — HTTP 401.
# Kung wala pa, skip mo muna — magana pa rin ang app, chat widget lang mag-e-error.
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.5-flash-lite

# Pagkatapos mag-set: patakbuhin ito para ma-verify ang key at makita ang
# listahan ng models na pwede nitong gamitin:
#   docker compose exec app php artisan ai:check
```

**Para malaman yung LAN IP ng target PC:**
```powershell
ipconfig
# Hanapin yung IPv4 Address ng Ethernet or Wi-Fi adapter
# Example: 192.168.1.50
```

---

## Step 3 — Generate HTTPS cert para sa camera scan

Camera access sa mobile browsers requires HTTPS (except localhost). Kailangan mo ng self-signed cert para sa LAN IP ng production PC.

**Auto-detect at generate (PowerShell):**
```powershell
.\scripts\generate-cert.ps1
```

**Or specific IP:**
```powershell
.\scripts\generate-cert.ps1 -LanIp 192.168.1.50
```

**Or via Git Bash:**
```bash
./scripts/generate-cert.sh
# or with specific IP:
./scripts/generate-cert.sh 192.168.1.50
```

The script generates `docker/nginx/certs/server.crt` at `server.key` — valid for **365 days**, tied specifically sa provided IP.

---

## Step 4 — Start the containers

```bash
docker compose up -d --build
```

Ito ang gagawin:
- Builds the PHP-FPM app image
- Downloads MySQL 8.0, Nginx, Node
- Starts everything sa background

**Verify running:**
```bash
docker compose ps
```

Dapat lahat ng services (`app`, `nginx`, `mysql`, `node`) ay **Up**.

---

## Step 5 — Setup Laravel

Run ang mga initialization commands na `once-only`:

```bash
# Generate app encryption key
docker compose exec app php artisan key:generate --force

# Run all migrations (assets, employees, RBAC, backups, permits, incidents, recommendations, signatories)
docker compose exec app php artisan migrate --force

# Seed initial master data (categories, brands, signatories, etc.)
docker compose exec app php artisan db:seed --force

# Create symlink for public storage (uploads, images)
docker compose exec app php artisan storage:link

# Ensure backups + attachments directories are writable
# (auto-handled by entrypoint.sh on container start — this is just for the first-time setup)
docker compose exec app mkdir -p storage/app/backups storage/app/attachments
docker compose exec app chown -R www-data:www-data storage/app
docker compose exec app chmod -R 775 storage/app

# Clear all caches
docker compose exec app php artisan optimize:clear
```

**⚠️ Important — First user becomes ADMIN automatically.** Ang migration na `add_permissions_system` ay auto-promote sa unang user sa `users` table bilang admin. If wala pang user sa DB, create one first sa web UI (register/login page) BEFORE running the migration — or the promotion silently does nothing at kailangan mo mag-manual promote.

Kung na-run mo na yung migration bago mag-add ng first user, i-manual promote via tinker:
```bash
docker compose exec app php artisan tinker --execute='App\Models\User::first()->update(["is_admin" => true]);'
```

---

## Step 6 — Build the frontend assets

```bash
# Install npm packages (includes marked for AI chat markdown rendering)
docker compose exec node npm install --legacy-peer-deps

# Build production assets
docker compose exec node npm run build

# IMPORTANT: para hindi ma-override yung production mode ng Vite,
# stop the node service (hindi kailangan ng dev server sa production):
docker compose stop node
rm -f public/hot
```

---

## Step 7 — Windows Firewall (production PC)

Open **elevated PowerShell** (Right-click → Run as Administrator), tapos:

```powershell
New-NetFirewallRule -DisplayName "IT Asset HTTP (8081)"  -Direction Inbound -Protocol TCP -LocalPort 8081 -Action Allow -Profile Private,Domain
New-NetFirewallRule -DisplayName "IT Asset HTTPS (8443)" -Direction Inbound -Protocol TCP -LocalPort 8443 -Action Allow -Profile Private,Domain
```

Ito ang nagpapayag ng inbound access mula LAN peers (phones, other PCs) papunta sa ports 8081 at 8443.

---

## Step 8 — Access URLs

Sa production PC's LAN IP:

- **Full app (HTTP):** `http://192.168.1.50:8081`
- **HTTPS + Scan:** `https://192.168.1.50:8443/scan`
- **Scan (from phone):** Open browser sa phone → same URL

**Sa unang HTTPS access:** Chrome/Safari sasabihin "Your connection is not private" — click **Advanced → Proceed anyway** (self-signed cert, expected). Once accepted, camera scan will work.

---

## Step 9 (optional) — Expose via Cloudflare Tunnel

Gamitin ito kung gusto mong ma-access ang app mula labas ng LAN (public HTTPS URL, trusted cert, camera scan works) **nang hindi nagbubukas ng router ports**.

The backend is already Cloudflare-ready:

- `bootstrap/app.php` trusts `X-Forwarded-*` headers → tamang `https` links, redirects, at real client IP.
- `AppServiceProvider` forces `https://` URLs and a **secure** session cookie whenever `APP_URL` starts with `https://`.
- `docker/nginx/cloudflare.conf` restores the visitor IP from `CF-Connecting-IP` (para tama ang nginx logs / rate-limits).
- `docker-compose.yml` has a `cloudflared` service under the `cloudflare` profile.

### 9.1 — Create the tunnel

1. Cloudflare dashboard → **Zero Trust → Networks → Tunnels → Create a tunnel** (Cloudflared connector).
2. Copy the **tunnel token** (mahabang string after `--token`).
3. Add a **Public Hostname**, e.g. `assets.yourdomain.com`
   - Service **Type:** `HTTP`
   - **URL:** `nginx:80`  ← container name; same docker network
4. SSL/TLS mode sa Cloudflare domain: **Full** or **Flexible** ay OK (tunnel is already encrypted).

### 9.2 — Configure `.env`

```env
APP_URL=https://assets.yourdomain.com
CLOUDFLARE_TUNNEL_TOKEN=eyJhIjoi...   # from step 9.1
```

> `SESSION_SECURE_COOKIE` is auto-enabled when `APP_URL` is `https://`. Set it explicitly only if you need to override.

### 9.3 — Build assets & start with the profile

```bash
# Production assets — the Vite dev server (node) does NOT work through the tunnel
docker compose exec node npm run build
docker compose stop node
rm -f public/hot

# Start everything + cloudflared
docker compose --profile cloudflare up -d

# Verify tunnel is connected
docker compose logs -f cloudflared
```

Kapag may `Registered tunnel connection` sa logs, open `https://assets.yourdomain.com`.

### Notes

- **Access control:** Since public na ang URL, i-recommend na i-enable ang **Cloudflare Access** (Zero Trust → Access → Applications) para may email/OTP gate bago makarating sa login page.
- **Upload limit:** Cloudflare free plan caps request body at **100 MB**; nginx is set to 64 MB so OK.
- **LAN access remains:** `http://<LAN-IP>:8081` and `https://<LAN-IP>:8443` still work as before.
- **Stop the tunnel only:** `docker compose --profile cloudflare stop cloudflared`

---

## Post-deployment configuration

### User accounts & role-based access

1. Login as the admin user (first user auto-promoted in Step 5).
2. Punta sa **Users** page (sidebar).
3. Add mga karagdagang user, then click **Edit** para i-set kung anong pages at actions ang pwede nila.
4. Yung **Admin toggle** = full access sa lahat. Uncheck para makita yung permission matrix (page × action checkboxes: view / create / edit / delete / print / export / import).
5. Non-admin users: sidebar auto-hides yung pages na wala silang `view` perm, at buttons na wala silang perm.
6. **Users at Backups pages are admin-only** (route middleware enforced).

### In-app database backups

Punta sa **Documents → Backups** (admin only). Two buttons:
- **Create SQL Backup** — `.sql` dump lang, mabilis
- **Create Full Backup (ZIP)** — SQL + `storage/app` files (asset photos, uploaded docs)

Auto-prune: last **5 per type** ang naiiwan sa `storage/app/backups/`. Download at store externally regularly.

### AI Assistant (Gemini chat widget)

Kung na-add mo ang `GEMINI_API_KEY` sa `.env`:
- Refresh browser → makikita mo ang floating **✨ purple button** sa bottom-right
- Users can ask natural-language questions about inventory ("Sino ang may pinakamaraming asset?", "Ilan ang laptops sa Marketing?")
- 19 tools available na kayang tawagin ng AI — read-only, walang create/edit/delete access

Kung ayaw mo yet ng AI feature — leave `GEMINI_API_KEY` empty. App works normally, widget clickable pero error message pag ni-try i-send.

---

## Common issues

**"Camera unavailable" sa phone**
- Confirm you're using `https://` (not `http://`)
- Accept the self-signed cert warning
- Ensure firewall rules created (Step 7)

**"Cannot connect to server"**
- Confirm phone is on same WiFi as production PC
- Check Windows Firewall (Step 7)
- Some routers have "AP Isolation" — check router settings

**"Class not found" or PHP errors**
- Run: `docker compose exec app composer install --no-dev --optimize-autoloader`
- Run: `docker compose exec app php artisan config:cache`

**Vite hot mode ng-a-active (white screen sa LAN access)**
- Delete: `rm -f public/hot`
- Stop node: `docker compose stop node`
- Rebuild: `docker compose exec node npm run build`

**Lahat ng users pumapasok bilang non-admin (no sidebar items visible)**
- May migration timing issue — walang user na na-promote to admin.
- Fix: `docker compose exec app php artisan tinker --execute='App\Models\User::first()->update(["is_admin" => true]);'`

**AI Assistant may error sa chat window**

Una, patakbuhin ang diagnostic — sasabihin niya mismo kung key ba o model ang sira:

```bash
docker compose exec app php artisan ai:check
```

Ipapakita nito kung tanggap ang API key, at ililista ang lahat ng models na pwede gamitin ng key na iyon.

- **"The AI service rejected the API key"** — mali o expired ang `GEMINI_API_KEY`.
  Dapat **Google AI Studio key** ito: nagsisimula sa `AIza`, 39 characters.
  Kumuha ng bago sa https://aistudio.google.com/apikey → ilagay sa `.env` → `php artisan config:clear`.
  (Ang mga key na galing sa ibang Google product — halimbawa yung nagsisimula sa `AQ.` —
  ay tinatanggihan ng Gemini API na may HTTP 401.)
- **"The AI model … is not available"** — palitan ang `GEMINI_MODEL` sa `.env` ng isa sa
  nilista ng `ai:check`, then `php artisan config:clear`.
- **429 rate limit** — maghintay ng 1 minuto (free tier: 15 requests/min).
- Ang buong error galing sa Google ay nasa `storage/logs/laravel.log` — hindi ito ipinapakita
  sa chat window dahil kasama doon ang API key.

**Backup button walang response**
- Verify `storage/app/backups/` directory exists and writable
- Check `docker compose logs app` for permission errors

---

## Manual database Backup + Restore (CLI)

Kahit meron nang UI para sa backups, useful pa rin ang CLI approach for full disk-level backups.

**Backup MySQL:**
```bash
docker compose exec mysql mysqldump -uroot -proot it_asset_inventory > backup-$(date +%Y%m%d).sql
```

**Restore:**
```bash
cat backup-YYYYMMDD.sql | docker compose exec -T mysql mysql -uroot -proot it_asset_inventory
```

**Backup uploaded files at generated backups:**
- Copy `storage/app/` folder to backup location (includes `storage/app/backups/` na naka-generate via UI)

---

## Updates (deploy new code changes)

```bash
git pull                                          # or copy new files
docker compose exec app composer install --no-dev
docker compose exec app php artisan migrate --force
docker compose exec node npm install --legacy-peer-deps
docker compose exec node npm run build
rm -f public/hot
docker compose stop node
docker compose exec app php artisan optimize:clear
```

**Kung nagbago ang `.env` (e.g., dinagdag mo yung `GEMINI_API_KEY`):**
```bash
docker compose exec app php artisan config:clear
```
(No container restart needed — `config:clear` is enough for env changes.)

---

## What's included (feature checklist)

- ✅ **Assets** — full CRUD, bulk receive, Excel import/export, QR asset tags, movement tracking (issue/return/transfer), part-change history
- ✅ **Employees** — full CRUD, Excel import, view assets held, per-employee accountability .docx download
- ✅ **Accountability forms** — .docx generation from template with signatories, per-asset or all-assets-for-employee
- ✅ **Permits to Bring Asset (PBA)** — CRUD + print + .docx download
- ✅ **Incident Reports (IR)** — CRUD + print + .docx download, link to part changes
- ✅ **Recommendations** — CRUD + print + .docx download, link to part changes
- ✅ **Mobile Scan** — QR scan via phone camera (HTTPS required, cert auto-generated)
- ✅ **Global Search** — Ctrl+K quick search across all data types
- ✅ **Master Data** — brands, categories, conditions, departments, locations, signatories, asset code rules
- ✅ **RBAC** — admin flag + per-user permission matrix (page × action)
- ✅ **In-app Backups** — SQL and full-zip backups via Documents → Backups page (admin only)
- ✅ **AI Assistant** (optional) — Gemini-powered chat widget with 19 inventory query tools
