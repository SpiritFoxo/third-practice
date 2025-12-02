FROM php:8.3-fpm-alpine

RUN apk add --no-cache bash curl git unzip rsync postgresql-dev dos2unix \
 && docker-php-ext-install pdo pdo_pgsql

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# каталоги
RUN mkdir -p /var/www/html /opt/laravel-patches
WORKDIR /var/www/html

# entrypoint
COPY entrypoint.sh /usr/local/bin/php_entrypoint.sh

# ВАЖНО: Конвертируем окончания строк в Unix-формат и даем права на исполнение
RUN dos2unix /usr/local/bin/php_entrypoint.sh \
 && chmod +x /usr/local/bin/php_entrypoint.sh

CMD ["bash", "/usr/local/bin/php_entrypoint.sh"]