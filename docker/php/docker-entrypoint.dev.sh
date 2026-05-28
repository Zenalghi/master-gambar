#!/bin/sh
set -e

echo "=== Master Gambar - Development Entrypoint ==="

cd /var/www/html

# ---- AUTO-GENERATE APP_KEY ----
# Check if .env exists and has a valid APP_KEY
if [ -f .env ]; then
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "-> APP_KEY not set, generating..."
        php artisan key:generate --force 2>/dev/null || true
    fi
else
    echo "-> No .env found, running key:generate..."
    # Create minimal .env first
    touch .env
    php artisan key:generate --force 2>/dev/null || true
fi

# Ensure APP_KEY is exported to PHP-FPM
if [ -f .env ]; then
    APP_KEY_VALUE=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2-)
    if [ -n "$APP_KEY_VALUE" ]; then
        echo "env[APP_KEY] = \"$APP_KEY_VALUE\"" >> /usr/local/etc/php-fpm.d/www.conf
    fi
fi

echo "ping.path = /ping" >> /usr/local/etc/php-fpm.d/www.conf
echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/www.conf

# ---- WAIT FOR MYSQL ----
echo "-> Waiting for MySQL..."
DB_HOST_VAL="${DB_HOST:-mysql-dev}"
MYSQL_ROOT_PASS="${MYSQL_ROOT_PASSWORD:-changeme_root_password_here}"
cat > /tmp/wait_mysql.php << 'PHPEOF'
<?php
$pass = getenv('MYSQL_ROOT_PASSWORD') ?: 'changeme_root_password_here';
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

# ---- AUTO-MIGRATE DATABASE ----
echo "-> Running database migrations..."
php artisan migrate --force 2>/dev/null || echo "  (no migrations to run)"

# ---- DEVELOPMENT: CLEAR INSTEAD OF CACHE ----
echo "-> Clearing caches for development..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

echo "-> Storage link..."
php artisan storage:link 2>/dev/null || true

echo "-> Laravel development environment ready!"
exec php-fpm
