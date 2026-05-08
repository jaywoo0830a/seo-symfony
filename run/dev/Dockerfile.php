FROM php:8.4.21-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apk add --no-cache \
        icu-libs libpq libzip git unzip bash \
 && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" \
        opcache pdo_pgsql intl zip \
 && apk del --no-network .build-deps

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Run as the host user when bind-mounting source so var/cache is writable
# without chown gymnastics. Override via UID / GID build args.
ARG UID=1000
ARG GID=1000
RUN deluser www-data 2>/dev/null || true \
 && (getent group "$GID" >/dev/null || addgroup -g "$GID" app) \
 && adduser -D -u "$UID" -G "$(getent group "$GID" | cut -d: -f1)" app

USER app
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
