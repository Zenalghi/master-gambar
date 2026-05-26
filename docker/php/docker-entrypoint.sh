#!/bin/sh
set -e

if [ -d /app-shared ] && [ ! -f /app-shared/public/index.php ]; then
    cp -a /var/www/html/. /app-shared/
fi

if [ ! -f /var/www/html/.env ]; then
    cp /tmp/.env.docker /var/www/html/.env
fi

php artisan config:cache
php artisan migrate --force

exec php-fpm
