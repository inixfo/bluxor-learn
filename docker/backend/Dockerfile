FROM composer:2 AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

COPY backend ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

FROM php:8.3-fpm-bookworm AS runtime

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/backend/php.ini /usr/local/etc/php/conf.d/zz-learn-bluxor.ini
COPY docker/backend/www.conf /usr/local/etc/php-fpm.d/zz-learn-bluxor.conf
COPY docker/backend/entrypoint.sh /usr/local/bin/learn-bluxor-entrypoint

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN chmod +x /usr/local/bin/learn-bluxor-entrypoint \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["learn-bluxor-entrypoint"]
CMD ["php-fpm"]
