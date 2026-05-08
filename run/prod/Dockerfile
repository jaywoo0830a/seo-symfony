# syntax=docker/dockerfile:1.7

# =============================================================================
# Stage: node — Sass → CSS (assets/styles/*.css)
# =============================================================================
FROM node:24-alpine AS node

WORKDIR /build
COPY package.json ./
RUN npm install --no-audit --no-fund --omit=dev
COPY assets/scss ./assets/scss
RUN npm run build:prod


# =============================================================================
# Stage: app — composer install + cache + asset-map compile
# =============================================================================
FROM php:8.4.21-fpm-alpine AS app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=__build_placeholder__ \
    DATABASE_URL=postgresql://app:app@nowhere/app?serverVersion=16&charset=utf8 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Runtime libs first; build deps wrapped in a virtual package and dropped after.
RUN apk add --no-cache \
        icu-libs libpq libzip git unzip \
 && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" \
        opcache pdo_pgsql intl zip \
 && apk del --no-network .build-deps

# Composer binary (pinned to its own image — fastest, reproducible).
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

# Production php.ini + opcache.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY run/prod/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

# Install dependencies first to maximize layer cache.
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

# App source.
COPY bin ./bin
COPY config ./config
COPY migrations ./migrations
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY translations ./translations
COPY assets ./assets
COPY importmap.php ./

# CSS produced in the node stage.
COPY --from=node /build/assets/styles ./assets/styles

# Symfony auto-scripts (cache + assets) require source to be present.
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && php bin/console cache:clear --no-warmup \
 && php bin/console cache:warmup \
 && php bin/console asset-map:compile \
 && php bin/console importmap:install \
 && mkdir -p var/cache var/log var/sessions \
 && chown -R www-data:www-data var public/assets


# =============================================================================
# Stage: php — final FPM image
# =============================================================================
FROM app AS php

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD php -r "exit((@fsockopen('127.0.0.1', 9000) === false) ? 1 : 0);"

CMD ["php-fpm"]


# =============================================================================
# Stage: caddy — serves /var/www/html/public, proxies PHP to php:9000.
# Automatic HTTPS via Let's Encrypt (real domain) or local CA (localhost).
# Persisted cert/state lives in the caddy_data volume.
# =============================================================================
FROM caddy:2.11-alpine AS caddy

COPY run/prod/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 80 443

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://127.0.0.1:2019/config/ || exit 1
