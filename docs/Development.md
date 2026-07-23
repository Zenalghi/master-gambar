# Development Environment Guide - Master Gambar

Panduan setup development environment menggunakan Docker (dan bisa juga digunakan bersandingan dengan Laragon).

## Arsitektur

```
SERVER LOKAL / LAPTOP DEVELOPMENT
├── PORT 8081 (Nginx App)  ← Docker (docker-compose.yml)
└── PORT 3308 (MySQL)      ← Docker
```

Jika menggunakan Laragon:
```
LARAGON
├── PORT 80 (Apache/Nginx Laragon)
└── PORT 3306 (MySQL Laragon)
```

**Konsep Penting**:
File `.env` di root project menggunakan konfigurasi Laragon (`DB_HOST=127.0.0.1`), namun saat Anda menjalankan `docker compose up`, setting `DB_HOST` otomatis dioverride menjadi `mysql` agar Docker dapat terhubung ke MySQL container-nya sendiri. Anda bisa menggunakan Laragon maupun Docker tanpa perlu gonta-ganti `.env`!

---

## Cara Setup Development

### Step 1: Buat File `.env`

```bash
cd ~/laravel/master-gambar
cp .env.example .env
nano .env
```

Buka file `.env` dengan editor teks (misal: `nano .env`) dan pastikan konfigurasi berikut sudah Anda isi:

- `DB_USERNAME=master_gambar_user` (Untuk koneksi dari aplikasi Laravel)
- `DB_PASSWORD=...` (Password aplikasi)
- `MYSQL_ROOT_PASSWORD=...` (Password root, gunakan jika ingin login via HeidiSQL)

Tidak perlu mengubah `DB_HOST` jika memakai Docker, karena otomatis dialihkan oleh `docker-compose.yml`.

> [!TIP]
> **Saran Password:** Karena ini adalah environment development (lokal), gunakan saja password yang simpel dan mudah diingat (contoh: `root`, `1234`, dll) agar Anda tidak repot saat testing!

### Step 2: Build & Jalankan Docker

```bash
docker compose up -d --build
```

Otomatis:
- Generate APP_KEY
- Buat database
- Jalankan semua migration
- Setup storage link

### Step 3: Verifikasi

```bash
docker compose ps
```

Akses:
- App: http://localhost:8081
- MySQL Port (Eksternal): `127.0.0.1:3308` (Gunakan HeidiSQL dengan user `root`)

## Database Development

### Migration Otomatis
Setiap `docker compose up -d --build`, migration otomatis dijalankan lewat script entrypoint.

### Manual Migration (Lewat Docker)
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

## VS Code Remote SSH + Xdebug (Opsional)

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
3. Tekan F5, buka halaman di browser, breakpoint akan aktif.

## Troubleshooting

### Container restart loop
Jika terjadi crash data:
```bash
docker compose down -v
docker compose up -d --build
```

### Update Kode dari Production
```bash
git pull origin main
docker compose up -d --build
```

## Struktur File (Folder Docker)

```
~/laravel/master-gambar/
├── docker/
│   ├── nginx/
│   │   ├── default.prod.conf     ← Production nginx config
│   │   └── default.conf          ← Development nginx config
│   └── php/
│       ├── Dockerfile.prod       ← Production Dockerfile
│       ├── Dockerfile            ← Development Dockerfile (+ Xdebug)
│       ├── docker-entrypoint.prod.sh ← Production entrypoint
│       └── docker-entrypoint.sh  ← Development entrypoint
├── docker-compose.yml            ← Development compose
└── docker-compose.prod.yml       ← Production compose
```
