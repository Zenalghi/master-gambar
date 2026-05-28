# 🚀 Development Environment Guide - Master Gambar

Panduan setup development environment menggunakan Docker di VM Server.

## 📋 Daftar Isi

1. [Arsitektur](#1-arsitektur)
2. [Prerequisites](#2-prerequisites)
3. [File yang Perlu Dibuat](#3-file-yang-perlu-dibuat)
4. [Setup Development](#4-setup-development)
5. [VS Code Remote SSH](#5-vs-code-remote-ssh)
6. [Migrasi dari Laragon](#6-migrasi-dari-laragon)
7. [Development Workflow](#7-development-workflow)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Arsitektur

```
┌─────────────────────────────────────────────────────────────────┐
│                    VM SERVER (192.168.100.173)                  │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              PRODUCTION (Port 8080)                       │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐                    │  │
│  │  │  Nginx  │→ │   App   │→ │  MySQL  │                    │  │
│  │  │  :8080  │  │  :9000  │  │  :3307  │                    │  │
│  │  └─────────┘  └─────────┘  └─────────┘                    │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              DEVELOPMENT (Port 8081)                      │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌──────────┐      │  │
│  │  │  Nginx  │→ │   App   │→ │  MySQL  │  │phpMyAdmin│      │  │
│  │  │  :8081  │  │  :9000  │  │  :3308  │  │  :8082   │      │  │
│  │  └─────────┘  └─────────┘  └─────────┘  └──────────┘      │  │
│  │                  ↑ Xdebug :9003                           │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              PORTAINER (Port 9000)                        │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
         ↑
         │ SSH / Browser
         │
┌─────────────────────────────────────────────────────────────────┐
│                    WINDOWS 10 LAPTOP                            │
│  - VS Code + Remote SSH Extension                               │
│  - Browser untuk akses aplikasi                                 │
│  - Git untuk version control                                    │
└─────────────────────────────────────────────────────────────────┘
```

### Port Mapping

| Service | Production | Development |
|---------|------------|-------------|
| Nginx | 8080 | 8081 |
| MySQL | 3307 | 3308 |
| phpMyAdmin | - | 8082 |
| Xdebug | - | 9003 |
| Portainer | 9000 | 9000 |

---

## 2. Prerequisites

Pastikan sudah terinstall di VM Server:

```bash
# Docker & Docker Compose
docker -v
docker compose -v

# Git
git --version

# SSH (untuk remote development)
sudo systemctl status sshd
```

---

## 3. File yang Perlu Dibuat

### 3.1 `docker-compose.dev.yml`

Buat file `docker-compose.dev.yml` di root project:

```yaml
# =====================================================================
# DOCKER COMPOSE DEVELOPMENT - MASTER GAMBAR
# =====================================================================
#
# File ini untuk development environment di VM Server
# Production tetap menggunakan docker-compose.prod.yml
#
# Cara jalankan:
#   docker compose -f docker-compose.dev.yml up -d --build
#
# Akses:
#   - App: http://192.168.100.173:8081
#   - MySQL: 127.0.0.1:3308
#   - phpMyAdmin: http://192.168.100.173:8082
# =====================================================================

services:
  app-dev:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.dev
    image: master-gambar:development
    container_name: master-gambar-app-dev
    restart: unless-stopped
    working_dir: /var/www/html
    depends_on:
      mysql-dev:
        condition: service_healthy
    env_file:
      - docker/.env.dev
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
      APP_URL: http://192.168.100.173:8081
      DB_HOST: mysql-dev
      DB_PORT: 3306
      DB_DATABASE: db_master_dev
      DB_USERNAME: dev
      DB_PASSWORD: devpassword123
      # Xdebug configuration
      XDEBUG_MODE: develop,debug,coverage
      XDEBUG_CONFIG: client_host=192.168.100.173 client_port=9003
      PHP_IDE_CONFIG: serverName=master-gambar-dev
    volumes:
      - .:/var/www/html
      - storage-data-dev:/var/www/html/storage/app
    networks:
      - master-gambar-dev-network
    ports:
      - "9003:9003"  # Xdebug port
    deploy:
      resources:
        limits:
          memory: 2G
          cpus: '2.0'
        reservations:
          memory: 512M
          cpus: '0.5'
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"
    healthcheck:
      test: ["CMD-SHELL", "SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

  nginx-dev:
    image: nginx:1.27-alpine
    container_name: master-gambar-nginx-dev
    restart: unless-stopped
    depends_on:
      - app-dev
    ports:
      - "8081:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - .:/var/www/html:ro
    networks:
      - master-gambar-dev-network
    deploy:
      resources:
        limits:
          memory: 128M
          cpus: '0.25'
    logging:
      driver: json-file
      options:
        max-size: "5m"
        max-file: "2"
    healthcheck:
      test: ["CMD", "wget", "--quiet", "--tries=1", "--spider", "http://127.0.0.1:80/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 10s

  mysql-dev:
    image: mysql:9.7.0
    container_name: master-gambar-mysql-dev
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword123
      MYSQL_PASSWORD: devpassword123
      MYSQL_USER: dev
      MYSQL_DATABASE: db_master_dev
    ports:
      - "127.0.0.1:3308:3306"
    volumes:
      - mysql-data-dev:/var/lib/mysql
      - ./docker/mysql/custom.cnf:/etc/mysql/conf.d/custom.cnf:ro
      - ./docker/mysql/healthcheck.sh:/healthcheck.sh:ro
    healthcheck:
      test: ["CMD", "sh", "/healthcheck.sh"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 60s
    networks:
      - master-gambar-dev-network
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: '1.0'
        reservations:
          memory: 256M
          cpus: '0.25'
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"

  # Optional: phpMyAdmin untuk development
  phpmyadmin-dev:
    image: phpmyadmin:5.2
    container_name: master-gambar-phpmyadmin-dev
    restart: unless-stopped
    depends_on:
      - mysql-dev
    environment:
      PMA_HOST: mysql-dev
      PMA_PORT: 3306
      PMA_USER: root
      PMA_PASSWORD: rootpassword123
    ports:
      - "8082:80"
    networks:
      - master-gambar-dev-network
    deploy:
      resources:
        limits:
          memory: 256M
          cpus: '0.25'

volumes:
  mysql-data-dev:
  storage-data-dev:

networks:
  master-gambar-dev-network:
    driver: bridge
```

### 3.2 `docker/.env.dev`

Buat file `docker/.env.dev`:

```bash
# =====================================================================
# DEVELOPMENT ENVIRONMENT VARIABLES
# =====================================================================

APP_NAME="Master Gambar Dev"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://192.168.100.173:8081

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=4  # Lebih cepat untuk development

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql-dev
DB_PORT=3306
DB_DATABASE=db_master_dev
DB_USERNAME=dev
DB_PASSWORD=devpassword123

MYSQL_ROOT_PASSWORD=rootpassword123
MYSQL_PASSWORD=devpassword123
MYSQL_USER=dev
MYSQL_DATABASE=db_master_dev

# Session & Cache (file-based untuk development)
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Mail (log untuk development)
MAIL_MAILER=log
MAIL_FROM_ADDRESS="dev@master-gambar.local"
MAIL_FROM_NAME="${APP_NAME}"
```

### 3.3 `docker/php/Dockerfile.dev`

Buat file `docker/php/Dockerfile.dev`:

```dockerfile
# syntax=docker/dockerfile:1

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
        pdo_mysql \
        zip \
        mbstring \
        xml \
        curl \
        bcmath \
        gd \
        exif \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt-lists/* \
    && wget -O /usr/local/bin/php-fpm-healthcheck https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Install Xdebug for development
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configure Xdebug
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
COPY . /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

RUN mkdir -p /app-shared && chown -R www-data:www-data /app-shared

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
```

### 3.4 Update `.gitignore`

Pastikan file-file berikut ada di `.gitignore`:

```gitignore
# Development
docker/.env.dev
docker-compose.dev.yml
```

---

## 4. Setup Development

### 4.1 Clone Repository (jika belum)

```bash
cd ~/laravel
git clone https://github.com/Zenalghi/master-gambar.git master-gambar-dev
cd master-gambar-dev
```

### 4.2 Buat File-file Development

Buat file-file yang dijelaskan di bagian [File yang Perlu Dibuat](#3-file-yang-perlu-dibuat).

### 4.3 Build & Jalankan

```bash
cd ~/laravel/master-gambar-dev

# Build dan jalankan development environment
docker compose -f docker-compose.dev.yml up -d --build
```

### 4.4 Generate APP_KEY

```bash
# Generate APP_KEY
docker exec master-gambar-app-dev php artisan key:generate

# Clear cache
docker exec master-gambar-app-dev php artisan config:clear
docker exec master-gambar-app-dev php artisan cache:clear
```

### 4.5 Migrasi Database

```bash
# Jalankan migration
docker exec master-gambar-app-dev php artisan migrate

# Jalankan seeder (jika ada)
docker exec master-gambar-app-dev php artisan db:seed
```

### 4.6 Verifikasi

```bash
# Cek status container
docker compose -f docker-compose.dev.yml ps

# Test aplikasi
curl -I http://localhost:8081

# Akses di browser
# http://192.168.100.173:8081
```

---

## 5. VS Code Remote SSH

### 5.1 Install Extension

Install extension berikut di VS Code:
- **Remote - SSH** (ms-vscode-remote.remote-ssh)
- **PHP Debug** (felixfbecker.php-debug)
- **PHP Intelephense** (bmewburn.vscode-intelephense-client)

### 5.2 Setup SSH Config

Edit file `~/.ssh/config` di Windows:

```
Host vm-dev
    HostName 192.168.100.173
    User grace
    Port 22
```

### 5.3 Connect ke VM

1. Buka VS Code
2. Tekan `F1` → `Remote-SSH: Connect to Host`
3. Pilih `vm-dev`
4. Buka folder `~/laravel/master-gambar-dev`

### 5.4 Setup Xdebug di VS Code

Buat file `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}"
            },
            "hostname": "192.168.100.173"
        }
    ]
}
```

### 5.5 Debugging

1. Set breakpoint di kode PHP
2. Tekan `F5` untuk start debugging
3. Buka aplikasi di browser
4. Xdebug akan otomatis terhubung

---

## 6. Migrasi dari Laragon

### 6.1 Backup Database dari Laragon

**Menggunakan HeidiSQL:**
1. Connect ke Laragon MySQL (127.0.0.1:3306, user: root)
2. Pilih database `db_master`
3. Klik kanan → Export database as SQL
4. Pilih: Structure + Data
5. Simpan sebagai `db_master.sql`

**Menggunakan mysqldump:**
```bash
# Di Laragon terminal
mysqldump -u root db_master > db_master.sql
```

### 6.2 Transfer File ke VM

```bash
# Dari Windows (PowerShell)
scp db_master.sql grace@192.168.100.173:~/laravel/
```

### 6.3 Restore Database ke Docker

```bash
# Di VM Server
cd ~/laravel

# Restore ke development database
cat db_master.sql | docker exec -i master-gambar-mysql-dev mysql -u root -prootpassword123 db_master_dev
```

### 6.4 Copy Storage Files

```bash
# Transfer storage files
scp -r storage/app/master grace@192.168.100.173:~/laravel/master-gambar-dev/storage/app/

# Fix permissions
docker exec -u root master-gambar-app-dev chown -R www-data:www-data /var/www/html/storage/app/master
docker exec -u root master-gambar-app-dev chmod -R 775 /var/www/html/storage/app/master
```

### 6.5 Verifikasi Migrasi

```bash
# Cek database
docker exec -it master-gambar-mysql-dev mysql -u root -prootpassword123 -e "SHOW TABLES;" db_master_dev

# Cek storage
docker exec master-gambar-app-dev ls -lh /var/www/html/storage/app/master/

# Test aplikasi
curl -I http://localhost:8081
```

---

## 7. Development Workflow

### 7.1 Daily Workflow

```bash
# Start development environment
docker compose -f docker-compose.dev.yml up -d

# Lihat logs
docker compose -f docker-compose.dev.yml logs -f app-dev

# Run artisan commands
docker exec master-gambar-app-dev php artisan migrate
docker exec master-gambar-app-dev php artisan make:controller MyController

# Run composer
docker exec master-gambar-app-dev composer require package-name

# Run npm (jika perlu)
docker exec master-gambar-app-dev npm install
docker exec master-gambar-app-dev npm run dev

# Stop development environment
docker compose -f docker-compose.dev.yml down
```

### 7.2 Git Workflow

```bash
# Buat branch baru
git checkout -b feature/my-feature

# Development...

# Commit & push
git add .
git commit -m "feat: add my feature"
git push origin feature/my-feature

# Merge ke main setelah review
git checkout main
git merge feature/my-feature
git push origin main
```

### 7.3 Update dari Production

```bash
# Pull latest code
git pull origin main

# Rebuild container (jika ada perubahan Dockerfile)
docker compose -f docker-compose.dev.yml up -d --build

# Run migration
docker exec master-gambar-app-dev php artisan migrate

# Clear cache
docker exec master-gambar-app-dev php artisan config:clear
docker exec master-gambar-app-dev php artisan cache:clear
docker exec master-gambar-app-dev php artisan view:clear
```

### 7.4 Backup Development Database

```bash
# Manual backup
docker exec master-gambar-mysql-dev mysqldump -u root -prootpassword123 db_master_dev > ~/laravel/backups/dev-backup-$(date +%Y%m%d-%H%M%S).sql

# Restore from backup
cat ~/laravel/backups/dev-backup-xxx.sql | docker exec -i master-gambar-mysql-dev mysql -u root -prootpassword123 db_master_dev
```

---

## 8. Troubleshooting

### Container tidak start

```bash
# Cek logs
docker compose -f docker-compose.dev.yml logs app-dev
docker compose -f docker-compose.dev.yml logs mysql-dev

# Restart container
docker compose -f docker-compose.dev.yml restart app-dev
```

### MySQL connection refused

```bash
# Tunggu container mysql healthy
docker compose -f docker-compose.dev.yml ps

# Cek logs mysql
docker compose -f docker-compose.dev.yml logs mysql-dev
```

### Port sudah dipakai

```bash
# Cek port yang dipakai
sudo netstat -tlnp | grep :8081

# Ubah port di docker-compose.dev.yml
```

### Permission error

```bash
# Fix permissions
docker exec -u root master-gambar-app-dev chown -R www-data:www-data /var/www/html/storage
docker exec -u root master-gambar-app-dev chmod -R 775 /var/www/html/storage
```

### Xdebug tidak connect

```bash
# Cek Xdebug config
docker exec master-gambar-app-dev php -i | grep xdebug

# Pastikan port 9003 terbuka
sudo ufw allow 9003/tcp
```

### Reset Development Environment

```bash
# Hapus semua data (hati-hati!)
docker compose -f docker-compose.dev.yml down -v

# Rebuild dari awal
docker compose -f docker-compose.dev.yml up -d --build
```

---

## 📁 Struktur File

```
~/laravel/
├── master-gambar/              # Production (Port 8080)
│   ├── docker-compose.prod.yml
│   └── ...
├── master-gambar-dev/          # Development (Port 8081)
│   ├── docker-compose.dev.yml
│   ├── docker/
│   │   ├── .env.dev
│   │   └── php/
│   │       └── Dockerfile.dev
│   └── ...
└── backups/                    # Backup folder
    ├── dev-backup-xxx.sql
    └── prod-backup-xxx.sql
```

---

## 🔒 Security Notes

- Development environment hanya bisa diakses dari network internal
- Password development berbeda dengan production
- `APP_DEBUG=true` hanya untuk development
- Jangan pernah push `docker-compose.dev.yml` atau `docker/.env.dev` ke Git

---

## 📊 Resource Usage

| Container | Memory Limit | CPU Limit |
|-----------|-------------|-----------|
| app-dev | 2 GB | 2.0 cores |
| nginx-dev | 128 MB | 0.25 cores |
| mysql-dev | 1 GB | 1.0 cores |
| phpmyadmin-dev | 256 MB | 0.25 cores |

**Total:** ~3.4 GB RAM, ~3.5 cores

**Spesifikasi Server:**
- Intel i5 Gen 12 ✅
- RAM 16 GB ✅ (cukup untuk production + development)
