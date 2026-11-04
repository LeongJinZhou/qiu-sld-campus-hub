#!/usr/bin/env bash
# Builds a clean, ready-to-upload ZIP for Exabytes Plesk (or any PHP 8.3+ / MySQL host):
# compiled React frontend, production Composer packages, the clean-install SQL, and no .env, tests,
# demo data, node_modules or local databases. Usage: scripts/package-plesk.sh [output.zip]
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="$(pwd)/${1:-qiu-sld-campus-hub-plesk.zip}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

[ -f database/schema/install.sql ] || { echo "database/schema/install.sql is missing (see docs/DATABASE.md)."; exit 1; }

npm ci --no-audit --no-fund
npm run build
git archive --format=tar HEAD | tar -x -C "$STAGE"
cp -R public/build "$STAGE/public/build"
mkdir -p "$STAGE/database/schema" && cp database/schema/install.sql "$STAGE/database/schema/install.sql"

# Clean build: no demo seeders, sample student records, dev tools, tests or CI.
rm -rf "$STAGE/tests" "$STAGE/tools/pwa" "$STAGE/tools/templates" "$STAGE/.github" "$STAGE/docs/screenshots" \
       "$STAGE/database/camsys" "$STAGE/phpunit.xml" "$STAGE/render.yaml" "$STAGE/scripts" \
       "$STAGE"/database/seeders/{Demo*,RestoreDemoMediaSeeder,StaffSeeder,SettingsSeeder}.php
cat > "$STAGE/database/seeders/DatabaseSeeder.php" <<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionSeeder::class);
    }
}
PHP

cat > "$STAGE/.env.production.example" <<'ENV'
APP_NAME="QIU SLD Campus Hub"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=sld_portal
DB_USERNAME=sld_portal
DB_PASSWORD=

# camsys student records (read-only). Ask QIU IT for a read-only MySQL user.
CAMSYS_DRIVER=mysql
CAMSYS_HOST=
CAMSYS_PORT=3306
CAMSYS_DATABASE=camsys
CAMSYS_USERNAME=
CAMSYS_PASSWORD=

# Google sign-in. Redirect URI: ${APP_URL}/auth/google/callback
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_HOSTED_DOMAIN=qiu.edu.my
SUPERADMIN_EMAILS=ai@qiu.edu.my
SUPERADMIN_EMAIL=ai@qiu.edu.my
SLD_OFFICER_EMAIL=yeeshiu.chong@qiu.edu.my
SLD_OFFICER_NAME="Yee Shiu Chong"

DEMO_LOGIN=false

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_VIA_SCHEDULER=true
FILESYSTEM_DISK=local

# Every notification is emailed. Haraka (if running, docs/EMAIL_SMTP.md) relays through Gmail; if Haraka is not
# reachable the app sends through Gmail directly. Put the Google app password below (never commit it anywhere).
MAIL_MAILER=failover
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_SCHEME=smtp
MAIL_USERNAME=campushub
MAIL_PASSWORD=
MAIL_VERIFY_PEER=false
GMAIL_USERNAME=qiuairesearch@gmail.com
GMAIL_APP_PASSWORD=
MAIL_FROM_ADDRESS=qiuairesearch@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Optional
ANTHROPIC_API_KEY=
HF_TOKEN=
PYTHON_PATH=
SOFFICE_PATH=
ENV

(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress)
rm -rf "$STAGE/node_modules"
mkdir -p "$STAGE/storage/app/private" "$STAGE/storage/app/public" "$STAGE/storage/framework/"{cache/data,sessions,views} \
         "$STAGE/storage/logs" "$STAGE/bootstrap/cache"
rm -f "$OUT"
(cd "$STAGE" && zip -qr "$OUT" . -x '*.DS_Store')
echo "Built $OUT ($(du -h "$OUT" | cut -f1)). Follow docs/DEPLOY_PLESK.md."
