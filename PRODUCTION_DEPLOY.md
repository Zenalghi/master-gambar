# 🚀 Production Deploy Guide - Master Gambar

Panduan lengkap deploy aplikasi Master Gambar ke server production.

**Server:** `192.168.100.17`  
**Aplikasi:** `~/laravel/master-gambar`

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
# Buat folder untuk aplikasi Laravel
mkdir -p ~/laravel
cd ~/laravel

# Clone repository
git clone https://github.com/Zenalghi/master-gambar.git

# Masuk ke folder project
cd ~/laravel/master-gambar
```

**Struktur folder:**
```
~/laravel/
└── master-gambar/
    ├── docker/
    │   ├── nginx/
    │   ├── mysql/
    │   │   ├── backup-example.sh    # Template (masuk Git)
    │   │   └── backup.sh            # Production (gitignore)
    │   └── php/
    ├── docker-compose.yml           # Template (masuk Git)
    ├── docker-compose.prod.yml      # Production (gitignore)
    └── ...
```

---

## 3. Konfigurasi Production

### 3.1 Buat File Production

```bash
cd ~/laravel/master-gambar

# Copy template ke file production
cp docker/mysql/backup-example.sh docker/mysql/backup.sh
cp docker-compose.yml docker-compose.prod.yml
```

### 3.2 Edit Password & APP_URL

```bash
# Edit docker-compose.prod.yml
nano docker-compose.prod.yml

# Edit backup.sh
nano docker/mysql/backup.sh
```

**File 1: `docker-compose.prod.yml`**

| Baris | Placeholder | Ganti Dengan |
|-------|-------------|--------------|
| 16 | `GANTI_PASSWORD_ROOT_DI_SINI` | Password root MySQL Anda |
| 17 | `GANTI_PASSWORD_APP_DI_SINI` | Password app MySQL Anda |
| 35 | `http://GANTI_IP_SERVER:8080` | `http://192.168.100.17:8080` |

**Contoh hasil edit:**
```yaml
x-passwords:
  root_password: &root_pass PasswordRootKuat123!
  app_password: &app_pass PasswordAppKuat456!

# ...

environment:
  APP_URL: http://192.168.100.17:8080
```

**File 2: `docker/mysql/backup.sh`**

| Baris | Placeholder | Ganti Dengan |
|-------|-------------|--------------|
| 22 | `GANTI_PASSWORD_ROOT_DI_SINI` | Password root MySQL Anda (sama dengan docker-compose.prod.yml) |

**Contoh hasil edit:**
```bash
# PASSWORD - GANTI DENGAN PASSWORD ANDA!
MYSQL_PASSWORD="PasswordRootKuat123!"
```

**Simpan:** `Ctrl+O` → `Enter` → `Ctrl+X`

### 📌 Mengapa Harus Copy?

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  Template (Git)              Production (Gitignore)             │
│  ─────────────               ──────────────────────             │
│                                                                 │
│  docker-compose.yml          docker-compose.prod.yml            │
│  - Placeholder password      - Password asli                    │
│  - Masuk ke Git              - DI-IGNORE oleh Git               │
│                                                                 │
│  docker/mysql/backup-example.sh  docker/mysql/backup.sh         │
│  - Placeholder password      - Password asli                    │
│  - Masuk ke Git              - DI-IGNORE oleh Git               │
│                                                                 │
│  Setiap git pull:                                               │
│  - Template berubah → OK                                        │
│  - Production tetap → Password aman!                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Build & Jalankan

```bash
cd ~/laravel/master-gambar

# Build dan jalankan dengan file production
docker compose -f docker-compose.prod.yml up -d --build
```

**Proses build akan memakan waktu 5-15 menit**.

**Output yang diharapkan:**
```
[+] Building 120.0s (15/15) FINISHED
[+] Running 3/3
 ✔ Container master-gambar-mysql  Healthy
 ✔ Container master-gambar-app    Started
 ✔ Container master-gambar-nginx  Started
```

---

## 5. Setup Auto-Start & Backup

```bash
cd ~/laravel/master-gambar

# Jalankan script setup (butuh sudo, jalankan SEKALI saja)
sudo bash docker/setup-autostart.sh
```

**Script ini akan:**
- ✅ Mengaktifkan Docker auto-start saat boot
- ✅ Mengatur backup database otomatis jam 12:00 siang
- ✅ Mengatur backup storage otomatis jam 12:30 siang
- ✅ Mengatur log rotation

**Verifikasi cron jobs:**
```bash
crontab -l
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
master-gambar-mysql     Up 2 minutes (healthy)   127.0.0.1:3307->3306/tcp
master-gambar-app       Up 2 minutes (healthy)   9000/tcp
master-gambar-nginx     Up 2 minutes (healthy)   0.0.0.0:8080->80/tcp
```

### 6.2 Test Aplikasi

```bash
curl -I http://localhost:8080
```

### 6.3 Test dari Browser

```
http://192.168.100.17:8080
```

---

## 7. Migrasi dari Laragon

Jika Anda sebelumnya menggunakan Laragon dan ingin memindahkan data ke Docker.

### 7.1 Backup Database dari Laragon (HeidiSQL)

```
Di HeidiSQL:
1. Connect ke Laragon MySQL (127.0.0.1:3306, user: root)
2. Pilih database db_master
3. Klik kanan → Export database as SQL
4. Pilih: Structure + Data
5. Simpan sebagai db_master.sql di ~/laravel/
```

### 7.2 Restore Database ke Docker

```bash
# Restore dari file SQL
cat ~/laravel/db_master.sql | docker exec -i master-gambar-mysql mysql -u root -p db_master

# Masukkan password MySQL Anda saat diminta
```

### 7.3 Copy Storage dari Laragon ke Docker

```bash
# Copy folder storage dari host ke container
docker cp ~/laravel/master-gambar/storage/app/master master-gambar-app:/var/www/html/storage/app/

# Kritis: Ubah permission agar Laravel bisa baca/tulis
docker exec -u root master-gambar-app chown -R www-data:www-data /var/www/html/storage/app/master
docker exec -u root master-gambar-app chmod -R 775 /var/www/html/storage/app/master
```

### 7.4 Verifikasi Migrasi

```bash
# Cek database
docker exec -it master-gambar-mysql mysql -u root -p -e "SHOW TABLES;" db_master

# Cek storage
docker exec master-gambar-app ls -lh /var/www/html/storage/app/

# Test aplikasi
curl -I http://localhost:8080
```

---

## 8. Update Aplikasi

### ⚠️ Backup Sebelum Update (Opsional tapi Disarankan)

```bash
# Backup database
cd ~/laravel/master-gambar

docker exec master-gambar-mysql sh /backup.sh

# Backup storage
bash docker/backup-storage.sh
```

### ✅ Cara Update (Password Aman!)

```bash
cd ~/laravel/master-gambar

# 1. Pull update (template berubah, tapi production tetap aman)
git pull

# 2. Rebuild dan restart (gunakan file production)
docker compose -f docker-compose.prod.yml up -d --build

# 3. Verifikasi
docker compose -f docker-compose.prod.yml ps
```

**Kenapa aman?**
- `git pull` update file template (`docker-compose.yml`, `backup-example.sh`)
- File production (`docker-compose.prod.yml`, `backup.sh`) tidak berubah
- Password tetap aman di file production!

---

## 9. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Container tidak start | `docker compose -f docker-compose.prod.yml logs app` |
| MySQL connection refused | Tunggu container mysql healthy |
| Port 8080 dipakai | Ubah port di `docker-compose.prod.yml` |
| Password salah | Edit `docker-compose.prod.yml` dan `backup.sh`, lalu restart |
| Backup gagal | Cek password di `backup.sh` sama dengan `docker-compose.prod.yml` |
| Storage permission error | Jalankan `chown -R www-data:www-data /var/www/html/storage/app/` |

---

## 📁 Struktur File

| File | Keterangan | Git |
|------|------------|-----|
| `docker-compose.yml` | Template dengan placeholder | ✅ Masuk |
| `docker-compose.prod.yml` | Production dengan password asli | ❌ Ignore |
| `docker/mysql/backup-example.sh` | Template dengan placeholder | ✅ Masuk |
| `docker/mysql/backup.sh` | Production dengan password asli | ❌ Ignore |

---

## 🔒 Security Checklist

- [ ] `docker-compose.prod.yml` sudah dibuat dan password sudah diganti
- [ ] `docker/mysql/backup.sh` sudah dibuat dan password sudah diganti
- [ ] `docker-compose.prod.yml` ada di `.gitignore`
- [ ] `docker/mysql/backup.sh` ada di `.gitignore`
- [ ] `APP_DEBUG=false` di `.env.docker.example`
- [ ] MySQL port hanya localhost (`127.0.0.1:3307`)
- [ ] Docker auto-start sudah di-enable
- [ ] Backup otomatis sudah di-setup

---

## 📊 Resource Usage

| Container | Memory Limit | CPU Limit |
|-----------|-------------|-----------|
| app (PHP-FPM) | 3 GB | 3.0 cores |
| nginx | 256 MB | 0.5 cores |
| mysql | 3 GB | 2.0 cores |

**Total:** ~6.25 GB RAM, ~5.5 cores

**Spesifikasi Server:**
- Intel i5 Gen 12 ✅
- RAM 16 GB ✅
