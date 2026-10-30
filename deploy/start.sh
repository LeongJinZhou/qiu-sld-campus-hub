#!/bin/sh
# Cloud Run start-up: listen on $PORT, prepare storage, migrate, seed the demo once, then serve.
set -e
PORT="${PORT:-8080}"
sed -ri "s/Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html
# storage/app is a Cloud Storage bucket mount (survives restarts); make sure the folders exist.
mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage/framework storage/logs bootstrap/cache 2>/dev/null || true

# Dummy camsys copy for the demo (read-only source of student records).
if [ "${CAMSYS_DRIVER:-sqlite}" = "sqlite" ]; then
    touch database/camsys.sqlite && chown www-data:www-data database/camsys.sqlite
    su -s /bin/sh www-data -c "php artisan camsys:import-dump --no-interaction" >/dev/null
fi

su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction"
if [ "${SEED_DEMO:-false}" = "true" ] && [ "$(su -s /bin/sh www-data -c "php artisan tinker --execute='echo App\\Models\\Club::count();'" 2>/dev/null | tail -1)" = "0" ]; then
    su -s /bin/sh www-data -c "php artisan db:seed --force --no-interaction"
fi
su -s /bin/sh www-data -c "php artisan config:cache && php artisan route:cache && php artisan view:cache"

exec apache2-foreground
