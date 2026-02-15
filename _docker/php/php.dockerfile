FROM php:8.2.19-fpm

WORKDIR /var/www/gg

RUN apt-get update && apt-get install -y nodejs npm zlib1g-dev g++ git libicu-dev zip libzip-dev zip \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-install intl opcache pdo pdo_mysql \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install gd \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node.js
RUN curl -sL https://deb.nodesource.com/setup_current.x -o nodesource_setup.sh
RUN bash nodesource_setup.sh
RUN apt-get install nodejs -y
RUN npm install npm@latest -g
RUN command -v node
RUN command -v npm

COPY ./backend /var/www/gg
#COPY ./_docker/php /var/www/gg/php

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
