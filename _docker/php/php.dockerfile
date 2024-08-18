FROM php:8.2.19-fpm

WORKDIR /var/www/gg

RUN apt-get update && apt-get install -y nodejs npm zlib1g-dev g++ git libicu-dev zip libzip-dev zip \
    && docker-php-ext-install intl opcache pdo pdo_mysql\
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

COPY ./src /var/www/gg
#COPY ./_docker/php /var/www/gg/php

RUN chown -R www-data:www-data /var/www/gg

EXPOSE 9000