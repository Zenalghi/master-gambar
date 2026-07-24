FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS composer

FROM php:8.3.16-fpm-bookworm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        libzip-dev \
        locales \
        ghostscript \
        wget \
        libfcgi-bin \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql zip mbstring xml curl bcmath gd exif intl \
    && apt-get clean \
    && rm -rf /var/lib/apt-lists/* \
    && wget -O /usr/local/bin/php-fpm-healthcheck https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Install Xdebug for development (tidak ada di production)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker/php/generate_env.php /tmp/generate_env.php
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

RUN mkdir -p /app-shared && chown -R www-data:www-data /app-shared

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
