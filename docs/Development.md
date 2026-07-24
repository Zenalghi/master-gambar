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

## Persiapan Awal

Pastikan Anda sudah menginstall salah satu dari environment berikut di komputer Anda:
- **Docker Desktop / Docker Compose** (Direkomendasikan agar environment seragam)
- **Atau Laragon** (Jika ingin menjalankan PHP & MySQL langsung di OS lokal)

---

## Cara Setup Development

### Step 1: Buat File `.env`

```bash
cd ~/laravel/master-gambar
cp .env.example .env
```

Buka file `.env` dengan editor teks dan pastikan konfigurasi berikut wajib Anda ubah/isi:

- `DB_USERNAME=master_gambar_user` (Untuk koneksi dari aplikasi Laravel)
- `DB_PASSWORD=...` (Wajib diisi, pastikan **sama persis** dengan `MYSQL_PASSWORD`)
- `MYSQL_ROOT_PASSWORD=...` (Wajib diisi, password root untuk server MySQL)
- `MYSQL_PASSWORD=...` (Wajib diisi, pastikan **sama persis** dengan `DB_PASSWORD`)

Tidak perlu mengubah `DB_HOST` jika memakai Docker, karena otomatis dialihkan oleh `docker-compose.yml`.

> [!IMPORTANT]
> **Tentang Pembuatan Database & User:**
> - **Jika menggunakan Docker:** Anda tidak perlu repot. Container akan otomatis membuatkan database `master_gambar_db` dan user `master_gambar_user` dengan password dari `MYSQL_PASSWORD` saat pertama kali dijalankan.
> - **Jika menggunakan Laragon:** Anda **tetap perlu** membuka HeidiSQL (atau DBeaver/phpMyAdmin), lalu membuat database `master_gambar_db` secara manual. Anda juga harus membuat user `master_gambar_user` (beserta passwordnya) di menu User Manager HeidiSQL. *(Atau, cara paling gampang di Laragon: cukup ubah `DB_USERNAME=root` dan kosongkan `DB_PASSWORD` di file `.env` Anda).*

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
