FROM node:22.23.4-trixie-slim AS node_runtime

FROM php:8.4.27-fpm-trixie

WORKDIR /var/www/gg

RUN apt-get update && apt-get install -y zlib1g-dev g++ git libicu-dev zip libzip-dev \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-install intl opcache pdo pdo_mysql \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install gd \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

# Download over HTTPS directly: PECL channel discovery can fail on the build host.
# Keep this separate so a download retry reuses compiled PHP extensions.
RUN curl --fail --show-error --location \
        --retry 5 --retry-delay 3 --retry-all-errors \
        --connect-timeout 20 --max-time 120 \
        https://pecl.php.net/get/apcu-5.1.28.tgz -o /tmp/apcu.tgz \
    && pecl install /tmp/apcu.tgz \
    && docker-php-ext-enable apcu \
    && php -r 'exit(extension_loaded("apcu") ? 0 : 1);' \
    && rm /tmp/apcu.tgz

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Use the same patched Node.js branch as frontend and socket server.
COPY --from=node_runtime /usr/local/bin/node /usr/local/bin/node
COPY --from=node_runtime /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version \
    && npm --version

COPY ./backend /var/www/gg
#COPY ./_docker/php /var/www/gg/php

# Скрипт запуска контейнера (queue:work + schedule:work + php-fpm).
# Содержимое смонтируется поверх volume'ом из docker-compose.yml — кладём дефолт сюда тоже, чтобы образ был самодостаточен.
COPY ./_docker/php/start.sh /usr/local/bin/gg-start.sh
RUN chmod +x /usr/local/bin/gg-start.sh

RUN chown -R www-data:www-data /var/www/gg

EXPOSE 9000
#CMD ["php-fpm"]
#CMD ["php", "artisan", "queue:work"]
#CMD ["sh", "-c", "php-fpm & npm run dev & php artisan queue:work"]
# Запуск команд в зависимости от окружения
#CMD if [ "$APP_ENV" = "dev" ]; then \
#        php-fpm & \
#        php artisan queue:work & \
#        npm run dev & \
#    else \
#        php-fpm & \
#        php artisan queue:work; \
#        php artisan inertia:start-ssr; \
#    fi
