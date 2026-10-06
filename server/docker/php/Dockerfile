FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
      git unzip icu-dev libzip-dev postgresql-dev oniguruma-dev \
      libpng-dev libjpeg-turbo-dev freetype-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql intl zip bcmath pcntl opcache gd mbstring \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html
USER www-data
