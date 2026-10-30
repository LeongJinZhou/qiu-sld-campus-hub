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
su -s /bin/sh www-data -c "php artisan config:cache && php artisan route:cache && php artisan view:cache"

exec apache2-foreground
