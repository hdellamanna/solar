# syntax=docker/dockerfile:1.7
#
# Solar Money — production Dockerfile for Render.com
#
# Multi-stage build:
#   1. assets  — install npm deps and run `npm run build` to produce public/build/*
#   2. vendor  — install composer deps without dev tools
#   3. runtime — PHP 8.4-fpm + nginx + supervisord, served on port 10000 (Render)
#
# Build:   docker build -t solar-money .
# Run:     docker run --rm -p 10000:10000 --env-file .env solar-money

# ── Stage 1: build frontend assets ────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app

# Copy only the manifest first to leverage Docker layer cache
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Copy build config + source, then build
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ── Stage 2: install PHP dependencies ──────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
# --no-scripts skips the auto-discover post-install hook (which requires
# a full app to bootstrap, e.g. APP_KEY, and the artisan binary).
# --no-dev excludes dev-only packages (phpunit, mockery, fakerphp).
# --optimize-autoloader builds the classmap for faster boot.
# We do NOT run `composer dump-autoload --classmap-authoritative` here:
# it triggers the @php artisan package:discover script, which fails
# because this stage only contains composer.json/lock. The non-authoritative
# dump is run in the runtime stage (where artisan exists) after the app
# code is copied.
RUN composer install \
        --no-dev \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist \
        --no-interaction

# ── Stage 3: runtime image ────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

# Install system packages: nginx, supervisor (process manager), curl (health),
# and the PHP extensions Laravel needs (pdo_mysql, intl, zip, bcmath, opcache).
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        bash \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        intl \
        zip \
        bcmath \
        gd \
        opcache

# PHP-FPM tuning for low-memory containers
RUN { \
        echo 'pm = ondemand'; \
        echo 'pm.max_children = 10'; \
        echo 'pm.process_idle_timeout = 60s'; \
        echo 'pm.max_requests = 500'; \
        echo 'clear_env = no'; \
    } > /usr/local/etc/php-fpm.d/zz-solar.conf

# OPcache tuning for production
RUN { \
        echo 'opcache.enable = 1'; \
        echo 'opcache.enable_cli = 0'; \
        echo 'opcache.memory_consumption = 192'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'opcache.revalidate_freq = 0'; \
        echo 'opcache.save_comments = 1'; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /app

# Copy the application code
COPY . .

# Copy vendor + assets from earlier stages
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
# Bring the composer binary across so we can regenerate the autoloader
# against the now-present app code (vendor stage skipped the dump because
# artisan wasn't there yet, and `composer dump-autoload --classmap-authoritative`
# would have triggered @php artisan package:discover prematurely).
COPY --from=vendor /usr/bin/composer /usr/bin/composer

# Regenerate the autoloader in non-authoritative mode now that artisan is
# available. The vendor stage skipped the dump because it would have triggered
# `@php artisan package:discover` before the app code was present. We avoid
# --classmap-authoritative here so Laravel's runtime class discovery (e.g.
# service providers, event listeners registered via config files) still works.
RUN composer dump-autoload --optimize --no-dev

# Create runtime directories with correct ownership
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
        bootstrap/cache \
        public/storage \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        public/storage

# Copy Render-specific configs
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php-fpm.conf     /usr/local/etc/php-fpm.d/zz-solar.conf
COPY docker-entrypoint.sh    /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Render expects the public service on port 10000.
EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]