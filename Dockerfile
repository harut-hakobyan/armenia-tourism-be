FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.4-fpm-alpine
RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install bcmath intl opcache pdo_mysql zip \
    && apk del .build-deps
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
