# syntax=docker/dockerfile:1.7

# =============================================================================
# Stage: node — Sass → CSS (assets/styles/*.css)
# =============================================================================
FROM node:24-alpine AS node

WORKDIR /build
# sass 는 devDependencies — 빌드 도구라 build stage 에서 필요.
# multi-stage 이므로 node_modules 는 final image 에 포함 안 됨 (assets/styles 만 COPY).
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
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
# Symfony Dotenv 가 부팅 시 .env 를 요구. .env 자체는 committed placeholder
# (실값은 compose.yaml 의 environment: 블록에서 주입). 누락 시 dotenv 가 throw.
COPY .env ./

# CSS produced in the node stage.
COPY --from=node /build/assets/styles ./assets/styles

# 1) 빌드 산출물 디렉토리를 *미리 www-data 소유*로 생성.
#    (이전엔 cache:warmup 이 root 로 cache/prod/* 생성 후 chown 했는데, 일부 환경
#    에서 chown -R 이 서브디렉토리에 적용 안 됨. 처음부터 www-data 권한이 안전.)
RUN mkdir -p var/cache/prod/http_cache var/cache/prod/pools var/log var/sessions public/assets \
 && chown -R www-data:www-data /var/www/html

# 2) Symfony auto-scripts — *www-data 로 실행*해야 cache 산출물도 www-data 소유.
# 순서 주의: importmap:install (vendor JS 다운로드) → asset-map:compile (manifest 생성).
USER www-data
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && php bin/console cache:clear --no-warmup \
 && php bin/console cache:warmup \
 && php bin/console importmap:install \
 && php bin/console asset-map:compile


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
