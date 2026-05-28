#!/bin/sh
set -e

echo "=== Master Gambar - Development Entrypoint ==="

cd /var/www/html

# ---- 1. GENERATE .ENV FROM ENVIRONMENT VARIABLES ----
echo "-> Generating .env from environment variables..."
php /tmp/generate_env.php 2>/dev/null || true

# Fallback: ensure minimal .env exists
if [ ! -f .env ] || [ ! -s .env ]; then
    echo "APP_NAME=Master Gambar Dev
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://192.168.100.173:8081
DB_CONNECTION=mysql
DB_HOST=mysql-dev
DB_PORT=3306
DB_DATABASE=db_master_dev
DB_USERNAME=dev
DB_PASSWORD=devpassword
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stack
LOG_LEVEL=debug
" > .env
fi

# ---- 2. ENSURE APP_KEY LINE EXISTS + AUTO-GENERATE ----
# Ensure APP_KEY line exists in .env (required for key:generate)
if ! grep -q "^APP_KEY=" .env 2>/dev/null; then
    echo "APP_KEY=" >> .env
fi
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "-> APP_KEY not set, generating..."
    php artisan key:generate --force
fi

# Export APP_KEY to PHP-FPM
APP_KEY_VALUE=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2-)
if [ -n "$APP_KEY_VALUE" ]; then
    echo "env[APP_KEY] = \"$APP_KEY_VALUE\"" >> /usr/local/etc/php-fpm.d/www.conf
fi

echo "ping.path = /ping" >> /usr/local/etc/php-fpm.d/www.conf
echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/www.conf

# ---- 3. WAIT FOR MYSQL ----
echo "-> Waiting for MySQL..."
cat > /tmp/wait_mysql.php << 'PHPEOF'
<?php
$pass = getenv('MYSQL_ROOT_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'devpassword';
$host = getenv('DB_HOST') ?: 'mysql-dev';
for ($i = 0; $i < 30; $i++) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=3306",
            'root',
            $pass,
            [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false]
        );
        echo "MySQL connected!\n";
        exit(0);
    } catch (Exception $e) {
        echo "  MySQL not ready (" . ($i + 1) . "/30)...\n";
        sleep(2);
    }
}
echo "ERROR: MySQL not ready after 30 retries\n";
exit(1);
PHPEOF
php /tmp/wait_mysql.php
echo "  MySQL ready!"

# ---- 4. AUTO-MIGRATE DATABASE ----
echo "-> Running database migrations..."
php artisan migrate --force 2>/dev/null || echo "  (no migrations to run)"

# ---- 5. DEVELOPMENT: CLEAR INSTEAD OF CACHE ----
echo "-> Clearing caches for development..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

echo "-> Storage link..."
php artisan storage:link 2>/dev/null || true

echo "-> Laravel development environment ready!"
exec php-fpm
