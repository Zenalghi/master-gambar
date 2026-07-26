#!/bin/sh
set -e

echo "=== Master Gambar - Laravel Docker Entrypoint ==="

cd /var/www/html

# Sync shared volume on first run (Untuk membagi file ke Nginx)
if [ -d /app-shared ] && [ ! -f /app-shared/public/index.php ]; then
    cp -a /var/www/html/. /app-shared/
    chown -R www-data:www-data /app-shared
fi

# 1. Jalankan script PHP untuk membuat .env dari environment variable
echo "-> Generating .env from environment variables..."
php /tmp/generate_env.php 2>/dev/null || true

# 2. SEBAGAI FALLBACK: Jika .env masih belum ada, copy langsung dari example
if [ ! -f .env ]; then
    cp /var/www/html/.env.example /var/www/html/.env 2>/dev/null || true
fi

# 3. Pastikan baris APP_KEY= selalu ada di dalam file .env yang baru dirakit
if ! grep -q "^APP_KEY=" .env; then
    echo "APP_KEY=" >> .env
fi

# 4. Generate APP_KEY if empty
if grep -q "^APP_KEY=$" .env 2>/dev/null || grep -q "^APP_KEY=base64:$" .env 2>/dev/null || ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "-> Generating APP_KEY..."
    php artisan key:generate --force
fi

# Export APP_KEY dan konfigurasi PHP-FPM ke file terpisah (overwrite, bukan append)
APP_KEY_VALUE=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2-)
cat > /usr/local/etc/php-fpm.d/zz-custom.conf <<EOF
ping.path = /ping
pm.status_path = /status
EOF
if [ -n "$APP_KEY_VALUE" ]; then
    echo "env[APP_KEY] = \"$APP_KEY_VALUE\"" >> /usr/local/etc/php-fpm.d/zz-custom.conf
fi

# Wait for MySQL (gunakan env vars dari docker-compose)
echo "-> Waiting for MySQL..."
cat > /tmp/wait_mysql.php << 'PHPEOF'
<?php
$host = getenv('DB_HOST') ?: 'infra-mysql';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';
for ($i = 0; $i < 30; $i++) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port",
            $user,
            $pass,
            [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false]
        );
        echo "MySQL connected ($host as $user)!\n";
        exit(0);
    } catch (Exception $e) {
        echo "  MySQL not ready (" . ($i + 1) . "/30), retrying...\n";
        sleep(3);
    }
}
echo "ERROR: MySQL ($host) not ready after 30 retries\n";
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