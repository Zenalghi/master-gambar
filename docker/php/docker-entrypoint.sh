#!/bin/sh
set -e

echo "=== Master Gambar - Laravel Docker Entrypoint ==="

cd /var/www/html

# Sync shared volume on first run
if [ -d /app-shared ] && [ ! -f /app-shared/public/index.php ]; then
    cp -a /var/www/html/. /app-shared/
    chown -R www-data:www-data /app-shared
fi

# 1. Jalankan script PHP untuk membuat .env dari environment variable Docker Compose
echo "-> Generating .env from environment variables..."
php /tmp/generate_env.php 2>/dev/null || true

# 2. SEBAGAI FALLBACK: Jika .env masih belum ada, copy langsung dari example
if [ ! -f .env ]; then
    cp /var/www/html/.env.docker.example /var/www/html/.env 2>/dev/null || true
fi

# 3. OTOMATIS GENERATE APP_KEY: Jika APP_KEY kosong, script ini akan membuatkannya untukmu
if grep -q "^APP_KEY=$" .env 2>/dev/null || grep -q "^APP_KEY=base64:$" .env 2>/dev/null || ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "-> Generating APP_KEY..."
    php artisan key:generate --force
fi

# Export APP_KEY ke lingkungan PHP-FPM
APP_KEY_VALUE=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2-)
if [ -n "$APP_KEY_VALUE" ]; then
    echo "env[APP_KEY] = \"$APP_KEY_VALUE\"" >> /usr/local/etc/php-fpm.d/www.conf
fi


# Wait for MySQL
echo "-> Waiting for MySQL..."
cat > /tmp/wait_mysql.php << 'PHPEOF'
<?php
$pass = getenv('MYSQL_ROOT_PASSWORD') ?: 'root_anti_ini';
for ($i = 0; $i < 30; $i++) {
    try {
        $pdo = new PDO(
            'mysql:host=mysql;port=3306',
            'root',
            $pass,
            [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false]
        );
        echo "MySQL connected!\n";
        exit(0);
    } catch (Exception $e) {
        echo "  MySQL not ready (" . ($i + 1) . "/30), retrying...\n";
        sleep(3);
    }
}
echo "ERROR: MySQL not ready after 30 retries\n";
exit(1);
PHPEOF
php /tmp/wait_mysql.php
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
