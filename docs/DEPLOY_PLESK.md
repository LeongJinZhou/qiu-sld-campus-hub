# Deploying to Exabytes (Plesk)

Stack: **React 19 frontend (pre-built) · PHP 8.3+ (Laravel 13) backend · MySQL 8 / MariaDB 10.6+**.
The ZIP from `scripts/package-plesk.sh` (`qiu-sld-campus-hub-plesk.zip`) already contains the built frontend and
the production Composer packages, so the server needs no Node.js or Composer.

## 1. PHP

Plesk → **Websites & Domains → your domain → PHP**:

- PHP **8.3 or newer**, handler *FPM served by Apache* (or nginx).
- Extensions: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `fileinfo`, `openssl`, `exif`, `ctype`, `xml`.
- `memory_limit` 256M, `upload_max_filesize` and `post_max_size` 20M, `max_execution_time` 120.

## 2. Database

Plesk → **Databases → Add Database**: name e.g. `sld_portal`, a new user with full rights on it, collation
`utf8mb4_unicode_ci`. Then fill it in one of two ways:

- **phpMyAdmin** (no SSH): open the database → **Import** → `database/schema/install.sql` from the ZIP.
  It creates every table and the clean starting data (roles, lookups, Super Admin `ai@qiu.edu.my`,
  SLD Officer `yeeshiu.chong@qiu.edu.my`). No demo data.
- **SSH / Laravel Toolkit**: skip the import and run the commands in step 4 (`migrate` and the seeder).

The schema is documented in [`DATABASE.md`](DATABASE.md).

## 3. Upload the files

1. Plesk → **Files**: upload the ZIP into the domain's home folder, e.g. `/httpdocs/../sld` (one level **above**
   the web root) and **Extract**.
2. **Hosting Settings → Document root** = `sld/public`. Only `public/` may be web-reachable; `storage/`, `.env`
   and the source are outside it.
3. Copy `.env.production.example` to `.env` and fill in `APP_URL`, `DB_*`, `GOOGLE_CLIENT_ID/SECRET`, mail and
   (optionally) `CAMSYS_*`. Keep `APP_DEBUG=false` and `DEMO_LOGIN=false`.
4. **SSL/TLS Certificates**: issue a free Let's Encrypt certificate and turn on *Redirect from HTTP to HTTPS*
   (the session cookie is HTTPS-only).

## 4. First-time commands

Plesk → **Laravel Toolkit** (if available: *Add application* → point it at `sld`) or **SSH Terminal**, in `sld/`:

```bash
php artisan key:generate --force
# only if you did NOT import install.sql in step 2:
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Without SSH, the same commands can be run once each as a Plesk **Scheduled Task → Run a PHP script**
(`artisan` with the arguments above).

Make sure `storage/` and `bootstrap/cache/` are writable by the site's system user (Plesk default).

## 5. Scheduler (required)

Plesk → **Scheduled Tasks → Add Task** → *Run a command*, **every minute** (`* * * * *`):

```text
cd /var/www/vhosts/your-domain/sld && /opt/plesk/php/8.3/bin/php artisan schedule:run >> /dev/null 2>&1
```

It sends reminders, runs the membership grace/termination and handover jobs and the nightly student-records sync,
and (with `QUEUE_VIA_SCHEDULER=true`) processes queued jobs. Use the PHP path of the version you selected.

## 6. Google sign-in

Google Cloud Console → **APIs & Services → Credentials → OAuth client (Web)**:

- Authorised redirect URI: `https://your-domain/auth/google/callback`
- Put the client ID and secret in `.env`, then `php artisan config:cache`.

Sign in with `ai@qiu.edu.my` (Super Admin). `yeeshiu.chong@qiu.edu.my` signs in as SLD Officer (Admin) and can add
and manage other SLD admins under **SLD Admin → Roles**.

## Optional features

| Feature | Needs |
| --- | --- |
| Official PDFs of Word/Excel documents | LibreOffice on the server (`SOFFICE_PATH`). Without it the original file still downloads. |
| Annual Activities Report PPTX | Python 3 with `python-pptx` (`PYTHON_PATH`). Without it SLD is told the PPTX could not be built. |
| AI drafting / assistant | `ANTHROPIC_API_KEY` |
| Toxic-comment filter | `HF_TOKEN` |

## Updating

Build a new ZIP, extract it over `sld/` (keep `.env` and `storage/`), then:

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Checks after deploying

- `https://your-domain/up` returns 200.
- `https://your-domain/.env` and `https://your-domain/../storage` are **not** reachable.
- `/dev/login-as` returns 404 (demo login is off).
- Sign in as `ai@qiu.edu.my`, then SLD Admin → Student records sync → run once.
