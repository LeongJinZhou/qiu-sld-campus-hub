# Deploying to Exabytes

The app is **PHP 8.3+ (Laravel 13) + MySQL**, which is what Exabytes hosting provides. Two options:

| | Exabytes **VPS / Cloud** (recommended) | Exabytes **cPanel shared hosting** |
| --- | --- | --- |
| How | Docker, using the repository `Dockerfile` | Upload a ZIP, point the domain at `public/` |
| Official PDFs (LibreOffice) | Yes | No. The PDF button explains this and the Word/Excel original still downloads |
| Annual Activities Report PPTX (python-pptx) | Yes | Only if the plan offers "Setup Python App" (see below) |
| Background jobs (AI drafting, archives) | Queue worker | Cron drains the queue every minute (`QUEUE_VIA_SCHEDULER=true`) |

## 1. Create the MySQL database

cPanel → **MySQL Databases**: create a database (e.g. `user_sld`) and a user with **ALL PRIVILEGES** on it.
Use `utf8mb4` / `utf8mb4_unicode_ci` (the default on current MySQL/MariaDB).

## 2a. cPanel shared hosting

1. On your computer: `scripts/package-exabytes.sh`. This produces `sld-campus-hub-exabytes.zip` with the built
   frontend and production packages.
2. cPanel → **File Manager**: upload the ZIP to your home folder **outside** `public_html` (e.g. `/home/USER/sld`) and extract it.
3. Point the web root at `sld/public`:
   - **Domains → (your domain) → Document Root** = `/home/USER/sld/public`, or
   - for the main domain, replace `public_html` with a symlink: `ln -s /home/USER/sld/public /home/USER/public_html`
     (Terminal or SSH).
4. cPanel → **Select PHP Version**: PHP **8.3 or newer**, with the extensions `pdo_mysql`, `mbstring`, `intl`, `gd`,
   `zip`, `bcmath`, `fileinfo`, `openssl` and `exif`.
5. Copy `.env.example` to `.env` (in `/home/USER/sld`) and set:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_DATABASE=user_sld
   DB_USERNAME=user_sld
   DB_PASSWORD=********
   QUEUE_CONNECTION=database
   QUEUE_VIA_SCHEDULER=true
   SESSION_DRIVER=database
   CACHE_STORE=database
   GOOGLE_CLIENT_ID=...        # redirect URI: https://your-domain/auth/google/callback
   GOOGLE_CLIENT_SECRET=...
   ANTHROPIC_API_KEY=...       # optional: AI drafting and the assistant
   HF_TOKEN=...                # optional: toxic-comment model
   SUPERADMIN_EMAILS=you@qiu.edu.my
   ```

6. Terminal (or SSH), in `/home/USER/sld`:

   ```bash
   php artisan key:generate --force
   php artisan migrate --force --seed     # roles, lookups, staff; first time only
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

7. cPanel → **Cron Jobs**, every minute:

   ```text
   * * * * * cd /home/USER/sld && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```

   This runs the reminders, the nightly student-records sync and the membership/handover jobs, and (with
   `QUEUE_VIA_SCHEDULER=true`) processes queued jobs. Use the PHP path cPanel shows for your selected version.

8. Optional: Annual Activities Report on cPanel. **Setup Python App**: create an app with Python 3, run
   `pip install python-pptx` in it, then set `PYTHON_PATH=/home/USER/virtualenv/<app>/3.x/bin/python` in `.env`.
   Without it, the report data is kept and SLD is told the PPTX could not be built.

**Updating:** build a new ZIP, extract it over `/home/USER/sld` (keep `.env` and `storage/`), then run
`php artisan migrate --force` and the three `:cache` commands again.

## 2b. Exabytes VPS / Cloud (full features)

```bash
git clone https://github.com/LeongJinZhou/qiu-sld-campus-hub.git && cd qiu-sld-campus-hub
docker build -t sld .
docker run -d --name sld -p 80:80 --env-file .env -v sld-storage:/var/www/html/storage sld
```

Use the same `.env` as above, but with `DB_HOST` set to the VPS's MySQL server and `QUEUE_VIA_SCHEDULER=false`.
The image includes LibreOffice and python-pptx. Put a reverse proxy (Caddy or Nginx with Let's Encrypt) in front
for HTTPS.

## Checks after deploying

- `https://your-domain/up` returns 200.
- Sign in with a `@qiu.edu.my` Google account listed in `SUPERADMIN_EMAILS`.
- SLD Admin → Student records sync → run once.
- Never upload `.env` to Git; `storage/` holds private uploads and must not be web-accessible (only `public/` is).
