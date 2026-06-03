# Development Environment Guide - Master Gambar

Panduan setup development environment menggunakan Docker di VM Server.

## Arsitektur

```
VM SERVER (192.168.100.173)
├── PRODUCTION (Port 8080)  ← Docker (docker-compose.prod.yml)
│   ├── master-gambar-app
│   ├── master-gambar-mysql
│   └── master-gambar-nginx
│
├── DEVELOPMENT (Port 8081) ← Docker (docker-compose.dev.yml)
│   ├── master-gambar-app-dev
│   ├── master-gambar-mysql-dev
│   ├── master-gambar-nginx-dev
│   └── master-gambar-phpmyadmin-dev (Port 8082)
│
└── PORTAINER (Port 9000)
```

### Port Mapping

| Service | Production | Development |
|---------|------------|-------------|
| App | 8080 | 8081 |
| MySQL | 3307 | 3308 |
| phpMyAdmin | - | 8082 |

## Cara Setup Development

### Step 1: Buat File Secrets

```bash
cd ~/laravel/master-gambar
cp docker/.env.dev.secrets.example docker/.env.dev.secrets
nano docker/.env.dev.secrets
```

Ganti semua `GANTI_PASSWORD_ROOT_DI_SINI` dan `GANTI_PASSWORD_DEV_DI_SINI` dengan password pilihan Anda. Simpan.

Atau, jalankan script auto-setup:
```bash
bash docker/setup-dev.sh
```
Script akan generate random password otomatis.

### Step 2: Build & Jalankan

```bash
docker compose -f docker-compose.dev.yml up -d --build
```

Otomatis:
- Generate APP_KEY
- Buat database
- Jalankan semua migration
- Setup storage link

### Step 3: Verifikasi

```bash
docker compose -f docker-compose.dev.yml ps
```

Akses:
- App: http://192.168.100.173:8081
- phpMyAdmin: http://192.168.100.173:8082 (root / password Anda)
- MySQL: 127.0.0.1:3308

## Database Development

### Migration Otomatis
Setiap `docker compose up -d --build`, migration otomatis di-run.

### Manual Migration
```bash
docker exec master-gambar-app-dev php artisan migrate
```

### Fresh Migration (reset total)
```bash
docker exec master-gambar-app-dev php artisan migrate:fresh --force
```

### Seed Data
```bash
docker exec master-gambar-app-dev php artisan db:seed --force
```

## VS Code Remote SSH + Xdebug

1. Install extension: `PHP Debug` di VS Code
2. Buat `.vscode/launch.json`:
```json
{
    "version": "0.2.0",
    "configurations": [{
        "name": "Listen for Xdebug",
        "type": "php",
        "request": "launch",
        "port": 9003,
        "pathMappings": { "/var/www/html": "${workspaceFolder}" }
    }]
}
```
3. Tekan F5, buka halaman di browser, breakpoint akan aktif

## Troubleshooting

### MYSQL_ROOT_PASSWORD variable is not set
File `docker/.env.dev.secrets` belum dibuat. Lihat Step 1.

### Container restart loop
```bash
docker compose -f docker-compose.dev.yml down -v
docker compose -f docker-compose.dev.yml up -d --build
```

### Reset Database
```bash
docker compose -f docker-compose.dev.yml down -v
docker compose -f docker-compose.dev.yml up -d --build
```

## Update dari Production
```bash
git pull origin main
docker compose -f docker-compose.dev.yml up -d --build
```

## Struktur File

```
~/laravel/master-gambar/
├── docker/
│   ├── .env.dev.secrets          ← BUAT SENDIRI dari .env.dev.secrets.example (gitignore)
│   ├── .env.dev.secrets.example  ← Template (ada di git)
│   ├── .env.secrets              ← Production secrets (gitignore)
│   ├── .env.secrets.example      ← Production template (ada di git)
│   ├── nginx/
│   │   ├── default.conf          ← Production nginx config
│   │   └── default-dev.conf      ← Development nginx config
│   └── php/
│       ├── Dockerfile            ← Production Dockerfile
│       ├── Dockerfile.dev        ← Development Dockerfile (+ Xdebug)
│       ├── docker-entrypoint.sh  ← Production entrypoint
│       ├── docker-entrypoint.dev.sh ← Development entrypoint
│       └── generate_env.php      ← Generate .env dari env vars
├── docker-compose.dev.yml        ← Development compose
└── docker-compose.prod.yml       ← Production compose
```
