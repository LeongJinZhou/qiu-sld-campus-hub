# Deploying to Exabytes (Plesk)

Stack: **React 19 frontend (pre-built) · PHP 8.4.1+ 64-bit (Laravel 13) backend · MySQL 8 / MariaDB 10.6+**.
The ZIP from `scripts/package-plesk.sh` (`qiu-sld-campus-hub-plesk.zip`) already contains the built frontend and
the production Composer packages, so the server needs no Node.js or Composer.

## 1. PHP

Plesk → **Websites & Domains → your domain → PHP**:

- PHP **8.4 (8.4.1 or newer), 64-bit**, handler *FPM served by Apache* (or nginx). The bundled packages
  (Symfony 8.1) refuse to start on 8.3 or older: every page returns `500 Composer detected issues in your platform`.
- Extensions: `pdo_mysql`, `mbstring`, `intl`, `gd` (with JPEG, WebP, FreeType), `zip`, `bcmath`, `fileinfo`,
  `openssl`, `exif`, `ctype`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `simplexml`, `iconv`, `tokenizer`, `opcache`.
- `memory_limit` 512M, `upload_max_filesize` 55M, `post_max_size` 60M, `max_execution_time` 120. The largest upload
  the app accepts is a 50 MB event document (video); smaller limits make those uploads fail before the app sees them.
- `proc_open` must **not** be in `disable_functions` (PDF conversion and the PPTX report run LibreOffice/Python).
- Additional directives: `expose_php = Off` and `display_errors = Off` (hides the PHP version and errors).
- If nginx proxies the site, **Apache & nginx Settings → Additional nginx directives**: `client_max_body_size 60m;`
- **URL rewriting must work, or every page except the home page shows `404 Not Found` (sign out, links, everything).**
  With *FPM served by Apache* the shipped `public/.htaccess` does it (Apache needs `mod_rewrite` and `AllowOverride All`
  for the site; Plesk's default has both). With *FPM served by nginx* (no Apache behind it) `.htaccess` is **ignored**: put this in
  **Apache & nginx Settings → Additional nginx directives**:
  `location / { try_files $uri $uri/ /index.php?$query_string; }`
  Test: `https://your-domain/index.php/not-in-records` opens a page but `https://your-domain/not-in-records` shows 404 → rewriting is the problem.

Use the **same PHP 8.4** for the website, the SSH terminal and the scheduled task. Plesk's plain `php` in SSH is
often the operating system's older PHP, so the commands below use the full path `/opt/plesk/php/8.4/bin/php`
(check yours with `ls /opt/plesk/php/`).

## 2. Database

Plesk → **Databases → Add Database**: name e.g. `sld_portal`, a new user with full rights on it, collation
`utf8mb4_unicode_ci`. Then fill it in one of two ways:

- **phpMyAdmin** (no SSH): open the **empty** database → **Import** → `database/schema/install.sql` from the ZIP. The file has no `DROP` statements, so it can never wipe an existing database; if tables already exist the import simply stops.
  It creates every table and the clean starting data (roles, lookups, Super Admin `ai@qiu.edu.my`,
  SLD Officer `yeeshiu.chong@qiu.edu.my`). No demo data.
- **SSH / Laravel Toolkit**: skip the import and run the commands in step 4 (`migrate` and the seeder).

The schema is documented in [`DATABASE.md`](DATABASE.md).

## 3. Upload the files

1. Plesk → **Files**: upload the ZIP into the domain's home folder (the folder that contains `httpdocs`), into a new folder `sld` (one level **above**
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
PHP=/opt/plesk/php/8.4/bin/php
$PHP -r 'echo PHP_VERSION, " ", PHP_INT_SIZE * 8, "-bit\n";'   # must print 8.4.x 64-bit
$PHP artisan key:generate --force   # FIRST install only. Never on an update: it makes existing encrypted data unreadable.
# only if you did NOT import install.sql in step 2:
$PHP artisan migrate --force
$PHP artisan db:seed --class=ProductionSeeder --force
$PHP artisan storage:link
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
```

Without SSH, the same commands can be run once each as a Plesk **Scheduled Task → Run a PHP script**
(`artisan` with the arguments above, PHP version **8.4**).

Make sure `storage/` and `bootstrap/cache/` are writable by the site's system user (Plesk default).

## 5. Scheduler (required)

Plesk → **Scheduled Tasks → Add Task** → *Run a command*, **every minute** (`* * * * *`):

```text
cd /var/www/vhosts/your-domain/sld && /opt/plesk/php/8.4/bin/php artisan schedule:run >> /dev/null 2>&1
```

It sends reminders, runs the membership grace/termination and handover jobs and the nightly student-records sync,
and (with `QUEUE_VIA_SCHEDULER=true`, the packaged default) processes queued jobs and emails, so the queue runs on
the same PHP 8.4 as this task. Use the PHP 8.4 path, not plain `php`.

## 6. Email (SMTP)

Every notification is also emailed. Set `MAIL_*` in `.env` to an SMTP server: the bundled Haraka server
(`deploy/haraka/`, see [EMAIL_SMTP.md](EMAIL_SMTP.md)) or Plesk's own mail server (**Mail → Create Email Address**,
e.g. `noreply@your-domain`, then `MAIL_HOST=localhost`, `MAIL_PORT=587`, that address and its password). The
scheduler task in step 5 sends the queued emails.

## 7. Google sign-in

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
PHP=/opt/plesk/php/8.4/bin/php
$PHP artisan migrate --force
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
```

Do **not** run `key:generate` on an update.

## Checks after deploying

- `https://your-domain/up` returns 200 (proves the website runs a compatible PHP 8.4.1+ 64-bit).
- SSH: `/opt/plesk/php/8.4/bin/php artisan schedule:list` lists the jobs (proves the terminal/scheduler PHP).
- Scheduled task: Plesk → **Scheduled Tasks → Run Now** finishes without errors; a test notification email arrives
  within a minute (proves the queue runs).
- Exports: download an approved document as PDF and a member list as Excel (proves LibreOffice and `proc_open`).
- `https://your-domain/.env` and `https://your-domain/../storage` are **not** reachable.
- `/dev/login-as` returns 404 (demo login is off).
- Sign in as `ai@qiu.edu.my`, then SLD Admin → Student records sync → run once.
