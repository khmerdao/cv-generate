FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache icu-dev libzip-dev libpng-dev git unzip $PHPIZE_DEPS \
 && docker-php-ext-install intl pdo_mysql zip gd opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /app

FROM base AS dev
CMD ["php-fpm"]

FROM base AS prod
COPY . /app
RUN composer install --no-dev --optimize-autoloader --no-scripts && composer dump-env prod
CMD ["php-fpm"]
