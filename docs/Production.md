# 🚀 Production Deploy Guide - Master Gambar

Panduan lengkap deploy aplikasi Master Gambar ke server production.

**Server:** `192.168.100.17`  
**Aplikasi:** `/srv/workspace/apps/master-gambar`

---

## 📋 Daftar Isi

1. [Pre-requisites](#1-pre-requisites)
2. [Clone Repository](#2-clone-repository)
3. [Konfigurasi Production](#3-konfigurasi-production)
4. [Build & Jalankan](#4-build--jalankan)
5. [Setup Auto-Start & Backup](#5-setup-auto-start--backup)
6. [Verifikasi](#6-verifikasi)
7. [Migrasi dari Laragon](#7-migrasi-dari-laragon)
8. [Update Aplikasi](#8-update-aplikasi)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Pre-requisites

Pastikan Docker sudah terinstall di server:

```bash
docker -v
docker compose -v
```

**Output yang diharapkan:**
```
Docker version 24.x.x, build xxxxxxx
Docker Compose version v2.x.x
```

---

## 2. Clone Repository

```bash
# Pastikan Anda berada di dalam folder apps pada workspace
cd /srv/workspace/apps

# Clone repository
git clone https://github.com/Zenalghi/master-gambar.git

# Masuk ke folder project
cd /srv/workspace/apps/master-gambar
```

**Struktur folder:**
```
/srv/workspace/apps/
└── master-gambar/
    ├── docker/
    │   ├── nginx/
    │   ├── mysql/
    │   └── php/
    ├── docker-compose.yml           # Development compose (Git)
    ├── docker-compose.prod.yml      # Production compose (Git)
    ├── .env.example                 # Template env (Git)
    └── ...
```

---

## 3. Konfigurasi Production

### 3.1 Buat File Production (.env.production)

```bash
cd /srv/workspace/apps/master-gambar

# Copy template env
cp .env.example .env.production
```

### 3.2 Edit Password & Konfigurasi

Anda **tidak perlu** mengedit `docker-compose.prod.yml`. Seluruh konfigurasi akan dibaca secara otomatis dari `.env.production`.

```bash
nano .env.production
```

**Variabel Penting yang Wajib Diubah:**

> [!CAUTION]
> **Peringatan Password:** Ini adalah environment PRODUCTION yang berisiko terkena serangan siber. Anda **WAJIB** menggunakan kombinasi password yang sangat kuat dan unik (gunakan huruf besar, kecil, angka, dan simbol) untuk database. Jangan pernah menggunakan password yang sama dengan environment development!

| Variable | Keterangan |
|----------|------------|
| `APP_URL` | Ubah ke IP/domain Server, contoh: `http://192.168.100.17` (via Nginx Proxy Manager) |
| `DB_PASSWORD` | Password untuk App Laravel konek ke MySQL |
| `MYSQL_ROOT_PASSWORD` | Password root MySQL (Wajib diganti!) |
| `MYSQL_PASSWORD` | Sama dengan `DB_PASSWORD` |

**Contoh hasil edit:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.100.17

DB_CONNECTION=mysql
DB_HOST=infra-mysql
DB_PORT=3306
DB_DATABASE=master_gambar_db
DB_USERNAME=master_gambar_user
DB_PASSWORD=PasswordAppKuat456!

# Docker MySQL Initialization (Hanya untuk development lokal)
# Di production, MySQL dikelola oleh repository infra.
# Variabel ini boleh dikosongkan jika MySQL sudah berjalan di infra.
MYSQL_ROOT_PASSWORD=
MYSQL_DATABASE=master_gambar_db
MYSQL_USER=master_gambar_user
MYSQL_PASSWORD=PasswordAppKuat456!

# Backup Configuration
BACKUP_DIR=/mnt/data/backups
RETENTION_DAYS=7
```

**Simpan:** `Ctrl+O` → `Enter` → `Ctrl+X`

### 📌 Mengapa Menggunakan .env.production?

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  SEBELUMNYA:                                                    │
│  ───────────                                                    │
│  Konfigurasi password tersebar di berbagai file dan folder      │
│  docker/.env.secrets dan docker-compose.prod.yml terpisah       │
│                                                                 │
│  SEKARANG (Lebih Rapih & Terpusat):                             │
│  ──────────────────────────────────                             │
│  File `.env.production` menjadi satu-satunya sumber             │
│  kebenaran (Single Source of Truth) untuk password dan setting. │
│                                                                 │
│  Semua script backup, update, dan docker-compose.prod.yml       │
│  otomatis membaca dari file `.env.production` di root folder.   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Build & Jalankan

```bash
cd /srv/workspace/apps/master-gambar

# Build dan jalankan dengan file production
docker compose -f docker-compose.prod.yml up -d --build
```

**Proses build akan memakan waktu 5-15 menit**.

**Output yang diharapkan:**
```
[+] Building 120.0s (15/15) FINISHED
[+] Running 3/3
 ✔ Container master-gambar-app    Started
 ✔ Container master-gambar-nginx  Started
```

---

## 5. Setup Auto-Start & Backup

### 5.1 Auto-Start Saat Boot

Karena menggunakan konfigurasi `restart: always` di file docker-compose, **secara otomatis container aplikasi ini akan menyala** saat service Docker dijalankan pada saat booting server (dikonfigurasi oleh repositori `infra`). Anda tidak perlu melakukan pengaturan khusus untuk autostart aplikasi ini.

### 5.2 Backup Otomatis

Backup otomatis (termasuk penyimpanan log nya) kini telah disederhanakan dan dikelola langsung melalui file `setup-app-autostart.sh` yang ada di aplikasi ini.

Jalankan perintah berikut:
```bash
cd /srv/workspace/apps/master-gambar
sudo bash setup-app-autostart.sh
```

Script tersebut akan mendaftarkan cron job agar `autobackup.sh` berjalan setiap jam **12:15 siang** (15 menit setelah backup infrastruktur) dan membuang output log-nya ke `/srv/workspace/logs/2-cron-master-gambar.log`.

**Verifikasi cron jobs (karena dijalankan dengan sudo, periksa di root):**
```bash
sudo crontab -l
```

**Struktur backup per-aplikasi:**
```
/mnt/data/backups/
└── 2024-01-15-1200-master-autobackup/
    ├── master_gambar_db-2024-01-15-120000.sql
    └── app/
        └── master/
```

---

## 6. Verifikasi

### 6.1 Cek Status Container

```bash
docker compose -f docker-compose.prod.yml ps
```

**Output yang diharapkan:**
```
NAME                    STATUS                   PORTS
master-gambar-app       Up 2 minutes (healthy)   9000/tcp
master-gambar-nginx     Up 2 minutes (healthy)   80/tcp
```

### 6.2 Test Aplikasi

```bash
# Dari dalam server (langsung ke container nginx)
curl -I http://master-gambar-nginx
```

### 6.3 Test dari Browser (Via Nginx Proxy Manager)

Pastikan Anda sudah mendaftarkan domain/IP di panel NPM (`http://<IP-SERVER>:81`).
Setelah itu, akses via browser:
```
http://192.168.100.17
```

### 6.4 Test API (untuk Flutter)

Jika aplikasi ini menyediakan API yang dikonsumsi oleh aplikasi Flutter, pastikan endpoint API dapat diakses:
```bash
curl -I http://192.168.100.17/api
```

Pastikan response status adalah `200 OK` atau sesuai dengan routing API Anda.

---

## 7. Migrasi dari Laragon

Jika Anda sebelumnya menggunakan Laragon dan ingin memindahkan data ke Docker.

### 7.1 Backup Database dari Laragon (HeidiSQL)

```
Di HeidiSQL:
1. Connect ke Laragon MySQL (127.0.0.1:3306, user: root)
2. Pilih database master_gambar_db
3. Klik kanan → Export database as SQL
4. Pilih: Structure + Data
5. Simpan sebagai master_gambar_db.sql di komputer Anda, lalu upload ke `/srv/workspace/` di server.
```

### 7.2 Restore Database ke Docker

```bash
# Restore dari file SQL
cat /srv/workspace/master_gambar_db.sql | docker exec -i infra-mysql mysql -u root -p master_gambar_db

# Masukkan password root MySQL Anda saat diminta
```

Atau Gunakan Heidisql dengan MariaDB or MySQSL sshtunnel

![SSH Tunnel](../public/sshtunnel.png)

setelah masuk execute sql ke database

### 7.3 Copy Storage dari Laragon ke Docker

Jika lokasi storage di  ~/laravel/master-gambar

```bash
# Copy folder storage dari host ke container
docker cp ~/laravel/master-gambar/storage/app/master master-gambar-app:/var/www/html/storage/app/

# Kritis: Ubah permission agar Laravel bisa baca/tulis
docker exec -u root master-gambar-app chown -R www-data:www-data /var/www/html/storage/app/master
docker exec -u root master-gambar-app chmod -R 775 /var/www/html/storage/app/master
```

Jika lokasi storage di /srv/workspace/apps/master-gambar
```bash
# Copy folder storage dari host ke container
docker cp /srv/workspace/apps/master-gambar/storage/app/master master-gambar-app:/var/www/html/storage/app/

# Kritis: Ubah permission agar Laravel bisa baca/tulis
docker exec -u root master-gambar-app chown -R www-data:www-data /var/www/html/storage/app/master
docker exec -u root master-gambar-app chmod -R 775 /var/www/html/storage/app/master
```

### 7.4 Verifikasi Migrasi

```bash
# Cek database
docker exec -it infra-mysql mysql -u root -p -e "SHOW TABLES;" master_gambar_db

# Cek storage
docker exec master-gambar-app ls -lh /var/www/html/storage/app/
docker exec master-gambar-app ls -lh /var/www/html/storage/app/master/

# Test aplikasi
curl -I http://localhost:8080
```

---

## 8. Update Aplikasi

### ✅ Cara Update Aman (Menggunakan Script Otomatis)

Script `docker/update-safe.sh` akan melakukan backup otomatis database dan storage sebelum update, sehingga data Anda aman!

```bash
cd /srv/workspace/apps/master-gambar

# Jalankan script update aman (backup + update + rebuild)
bash docker/update-safe.sh
```

**Apa yang dilakukan script ini:**

1. **Backup Database** - Export semua database MySQL ke folder `/mnt/data/backups/`
2. **Backup Storage** - Copy semua file (PDF, PNG, ZIP) dari container ke host
3. **Pull Latest Code** - Download update terbaru dari Git
4. **Stop Containers** - Hentikan container (volume data TIDAK terhapus!)
5. **Rebuild Images** - Build ulang Docker images
6. **Start Containers** - Jalankan container kembali
7. **Verifikasi** - Cek status container

**Output yang diharapkan:**
```
==========================================
  Master Gambar - Safe Update
  2024-01-15 10:30:00
==========================================

[1/8] Backup database MySQL...
  ✓ Database backup selesai (150M)

[2/8] Backup storage (file PDF/PNG/ZIP)...
  ✓ Storage backup selesai (2.3G)

[3/8] Pull latest code dari Git...
  ✓ Code updated

[4/8] Stop containers...
  ✓ Containers stopped

[5/8] Rebuild Docker images...
  ✓ Images rebuilt

[6/8] Start containers...
  ✓ Containers started

[7/8] Backup database MySQL (Setelah Update)...
  ✓ Database backup (updated) selesai (150M)

[8/8] Verifikasi...
  ✓ Semua container running

==========================================
  Update Selesai!
==========================================
```

### 📌 Kenapa Aman?

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  Data yang AMAN (tidak terhapus):                               │
│  ────────────────────────────────                               │
│  ✓ MySQL database (volume: mysql-data)                          │
│  ✓ File storage (volume: storage-data)                          │
│  ✓ Backup database (volume: mysql-backup)                       │
│  ✓ Backup storage (folder: /mnt/data/backups/)                  │
│  ✓ Shared code (volume: app-code)                               │
│                                                                 │
│  Yang BERUBAH saat update:                                      │
│  ──────────────────────────                                     │
│  → Docker images (dibuild ulang)                                │
│  → Application code (git pull)                                  │
│                                                                 │
│  File production tetap AMAN:                                    │
│  ──────────────────────────                                     │
│  ✓ docker-compose.prod.yml (tidak berubah)                      │
│  ✓ .env.production (tidak berubah)                              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### ⚠️ Catatan Penting

- Script akan otomatis mendeteksi dan menggunakan `docker-compose.prod.yml` jika tersedia. Jika tidak ada, script akan fallback ke `docker-compose.yml`.
- Pastikan `.env.production` sudah dibuat sebelum menjalankan script
- Backup disimpan di folder `/mnt/data/backups/` dengan format tanggal
- Jika terjadi masalah, Anda bisa restore dari backup

### 🔄 Verifikasi Setelah Update

```bash
# Cek status container
docker compose -f docker-compose.prod.yml ps

# Cek logs jika ada masalah
docker compose -f docker-compose.prod.yml logs -f

# Test aplikasi
curl -I http://localhost
```
---

## 9. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Container tidak start | `docker compose -f docker-compose.prod.yml logs app` |
| MySQL connection refused | Pastikan container infra-mysql berjalan dan healthy di infra |
| Password salah | Edit `.env.production`, lalu restart dengan docker compose |
| Backup gagal | Cek konfigurasi backup di repository `infra` |
| Storage permission error | Jalankan `chown -R www-data:www-data /var/www/html/storage/app/` |
| Backup path salah | Pastikan `BACKUP_DIR` menggunakan path absolut, bukan `~` |

### 📌 Best Practice: Path Handling

**Masalah Umum dengan `~` (Tilde):**
- `~` di shell akan di-expand ke home directory user yang menjalankan script
- Tapi jika di-set sebagai variabel (misal `BACKUP_DIR=~/path`), `~` tidak akan di-expand
- Di dalam container, `~` merujuk ke home directory container, bukan host

**Solusi yang Digunakan:**
```bash
# Gunakan eval untuk resolve ~ ke path absolut
HOME_DIR=$(eval echo "~${USER}")
BACKUP_DIR="${BACKUP_DIR:-${HOME_DIR}/laravel/backups}"
```

**Semua script backup sudah menggunakan best practice ini:**
- `docker/autobackup.sh` - Auto-backup script
- `docker/update-safe.sh` - Safe update script
- `docker/backup-only-manual.sh` - Backup manual database dan storage script

---

## 📁 Struktur File

| File | Keterangan | Git |
|------|------------|-----|
| `docker-compose.yml` | Development compose (self-contained) | ✅ Masuk |
| `docker-compose.prod.yml` | Production compose (tanpa MySQL, pakai infra) | ✅ Masuk |
| `.env.example` | Template env untuk development | ✅ Masuk |
| `.env.production` | Production secrets | ❌ Ignore |
---

## 🔒 Security Checklist

- [ ] `.env.production` sudah dibuat dan password sudah diganti
- [ ] `.env.production` ada di `.gitignore`
- [ ] `APP_DEBUG=false` di `.env.production`
- [ ] Repository `infra` sudah berjalan di server
- [ ] Aplikasi terhubung ke `rekayasa-network`
- [ ] Docker auto-start sudah di-enable (via `infra`)
- [ ] Backup otomatis sudah di-setup (via `infra`)

---

## 📊 Resource Usage (Hanya Aplikasi)

| Container | Memory Limit | CPU Limit |
|-----------|-------------|-----------|
| app (PHP-FPM) | 3 GB | 3.0 cores |
| nginx | 256 MB | 0.5 cores |

**Total:** ~3.25 GB RAM, ~3.5 cores

**Spesifikasi Server:**
- Intel i5 Gen 12 ✅
- RAM 16 GB ✅
