#!/bin/sh
set -e

echo "=== Master Gambar - Laravel Docker Entrypoint ==="

# Sync shared volume
if [ -d /app-shared ] && [ ! -f /app-shared/public/index.php ]; then
    cp -a /var/www/html/. /app-shared/
    chown -R www-data:www-data /app-shared
fi

cd /var/www/html

# Ensure .env exists
if [ ! -f .env ]; then
    cp /tmp/.env.docker /var/www/html/.env
fi

# Generate APP_KEY if empty
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=base64:$" .env; then
    php artisan key:generate --force
fi

# Wait for MySQL
echo "-> Waiting for MySQL..."
MAX_RETRIES=30
RETRY=0
until php -r "
try {
    \$pdo = new PDO('mysql:host=mysql;port=3306', 'root', 's3cur3_mysql_r00t', array(PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false));
    \$pdo = null;
    exit(0);
} catch (Exception \$e) { exit(1); }
" 2>/dev/null; do
    RETRY=$((RETRY + 1))
    if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
        echo "ERROR: MySQL not ready after $MAX_RETRIES retries"
        exit 1
    fi
    echo "  MySQL not ready ($RETRY/$MAX_RETRIES), retrying..."
    sleep 3
done
echo "  MySQL ready!"

# Run artisan commands
echo "-> Migrating database..."
php artisan migrate --force

echo "-> Caching config..."
php artisan config:cache

echo "-> Caching views..."
php artisan view:cache

echo "-> Storage link..."
php artisan storage:link 2>/dev/null || true

echo "-> Laravel ready!"
exec php-fpm
