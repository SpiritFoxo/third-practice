FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash curl git unzip rsync postgresql-dev dos2unix \
    autoconf g++ make pcre-dev linux-headers

RUN docker-php-ext-install pdo pdo_pgsql

RUN pecl install redis \
    && docker-php-ext-enable redis


RUN apk del autoconf g++ make pcre-dev linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/html /opt/laravel-patches
WORKDIR /var/www/html

COPY entrypoint.sh /usr/local/bin/php_entrypoint.sh
RUN dos2unix /usr/local/bin/php_entrypoint.sh \
 && chmod +x /usr/local/bin/php_entrypoint.sh

CMD ["bash", "/usr/local/bin/php_entrypoint.sh"]