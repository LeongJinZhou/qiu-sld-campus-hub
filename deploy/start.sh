#!/bin/sh
# Container start-up (Cloud Run, Render): listen on $PORT, prepare storage, migrate, seed the demo once, then serve.
set -e
PORT="${PORT:-8080}"
sed -ri "s/Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html
# storage/app may be a mounted volume (Cloud Storage on Cloud Run); make sure the folders exist.
mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
# The web server runs as www-data and must be able to write uploads, signatures and generated files.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Dummy camsys copy for the demo (read-only source of student records).
if [ "${CAMSYS_DRIVER:-sqlite}" = "sqlite" ]; then
    touch database/camsys.sqlite && chown www-data:www-data database/camsys.sqlite
    su -s /bin/sh www-data -c "php artisan camsys:import-dump --no-interaction" >/dev/null
fi

# Free demo hosts without a database service use an SQLite file in the container (re-seeded on each start).
if [ "${DB_CONNECTION:-mysql}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    touch "$DB_FILE" && chown www-data:www-data "$DB_FILE"
fi

MIGRATED=$(su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction")
echo "$MIGRATED"
# A brand-new database (the clubs table was just created) gets the demo data when SEED_DEMO=true.
if [ "${SEED_DEMO:-false}" = "true" ] && echo "$MIGRATED" | grep -q "create_clubs_table"; then
    su -s /bin/sh www-data -c "php artisan db:seed --force --no-interaction"
fi
# Email: Haraka runs inside this container, on 127.0.0.1 only (never exposed), and relays through Gmail.
# The app logs in with a random password made at every start; Gmail directly is the fallback (MAIL_MAILER=failover).
if [ -n "${GMAIL_APP_PASSWORD:-}" ] || [ -n "${SMTP_RELAY_HOST:-}" ]; then
    HARAKA_PASS="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    HARAKA_DIR=/opt/haraka SMTP_LISTEN=127.0.0.1:2525 SMTP_NODES=0 SMTP_TLS=off SMTP_USER=campushub SMTP_PASS="$HARAKA_PASS" \
        SMTP_HOSTNAME="${SMTP_HOSTNAME:-$(echo "${APP_URL:-localhost}" | sed -E 's#^https?://##; s#/.*##')}" haraka-configure
    chown -R haraka /opt/haraka
    su -s /bin/sh haraka -c "haraka -c /opt/haraka" >> /var/www/html/storage/logs/haraka.log 2>&1 &
    export MAIL_MAILER="${MAIL_MAILER:-failover}" MAIL_HOST=127.0.0.1 MAIL_PORT=2525 MAIL_SCHEME=smtp \
        MAIL_USERNAME=campushub MAIL_PASSWORD="$HARAKA_PASS" MAIL_VERIFY_PEER=false \
        MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-${GMAIL_USERNAME:-}}"
    unset HARAKA_PASS
fi

su -s /bin/sh www-data -c "php artisan config:cache && php artisan route:cache && php artisan view:cache"

# Background workers: queued jobs (emails, AI drafts, archives) and the scheduler (reminders, nightly jobs).
# Each restarts itself if it stops.
su -s /bin/sh www-data -c 'while true; do php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --quiet; sleep 2; done' &
su -s /bin/sh www-data -c 'while true; do php artisan schedule:work --quiet; sleep 5; done' &

exec apache2-foreground
