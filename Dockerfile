# Campus Hub container for Google Cloud Run (behind Firebase Hosting).
# PHP 8.4 + Apache, LibreOffice (official PDFs), Python + python-pptx (Annual Activities Report),
# Haraka SMTP (Node.js) for notification emails, a queue worker and the scheduler.

# --- Front-end build ---
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY . .
RUN npm run build

# --- Haraka SMTP server (runs inside the app on 127.0.0.1 and relays through Gmail) ---
FROM node:22-bookworm-slim AS haraka
RUN npm install -g --omit=dev --prefix /opt/haraka-pkg Haraka@3 \
    && /opt/haraka-pkg/bin/haraka -i /opt/haraka
COPY deploy/haraka/config/ /opt/haraka/config/

# --- PHP dependencies ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm -f bootstrap/cache/*.php

# --- Runtime ---
FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libzip-dev libicu-dev \
        libreoffice-writer-nogui libreoffice-calc-nogui libreoffice-impress-nogui fonts-dejavu fonts-liberation2 \
        python3 python3-venv default-mysql-client openssl \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl exif bcmath opcache \
    && python3 -m venv /opt/pptx && /opt/pptx/bin/pip install --no-cache-dir python-pptx \
    && a2enmod rewrite headers \
    && sed -ri 's!^(\s*Alias /icons/)!#\1!' /etc/apache2/mods-available/alias.conf \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PYTHON_PATH=/opt/pptx/bin/python \
    SOFFICE_PATH=/usr/bin/soffice \
    HOME=/tmp
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/hardening.conf && a2enconf hardening \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=20M\npost_max_size=25M\nmemory_limit=512M\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/campus-hub.ini"

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN rm -f public/storage && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY --from=haraka /usr/local/bin/node /usr/local/bin/node
COPY --from=haraka /opt/haraka-pkg /opt/haraka-pkg
COPY --from=haraka /opt/haraka /opt/haraka
COPY deploy/haraka/configure.sh /usr/local/bin/haraka-configure
RUN ln -s /opt/haraka-pkg/bin/haraka /usr/local/bin/haraka && chmod +x /usr/local/bin/haraka-configure \
    && useradd --system --no-create-home --shell /usr/sbin/nologin haraka && chown -R haraka /opt/haraka

COPY deploy/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start
EXPOSE 8080
CMD ["start"]
