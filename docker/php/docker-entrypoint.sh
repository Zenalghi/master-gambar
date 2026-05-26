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

# ... (Sisa script ke bawah seperti wait_mysql dan artisan migrate tetap sama)