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
7. [Update Aplikasi](#7-update-aplikasi)
8. [Troubleshooting](#8-troubleshooting)

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
    │   └── php/
    ├── docker-compose.yml          # Template (placeholder)
    ├── docker-compose.prod.yml     # Production (gitignore)
    ├── .env.docker.example
    └── ...
```

---

## 3. Konfigurasi Production

### 3.1 Buat File Production

```bash
cd ~/laravel/master-gambar

# Copy template ke file production
cp docker-compose.yml docker-compose.prod.yml
```

### 3.2 Edit Password & APP_URL

```bash
nano docker-compose.prod.yml
```

**Cari dan GANTI bagian ini:**

| Baris | Placeholder | Ganti Dengan |
|-------|-------------|--------------|
| 6 | `GANTI_PASSWORD_ROOT_DI_SINI` | Password root MySQL Anda |
| 7 | `GANTI_PASSWORD_APP_DI_SINI` | Password app MySQL Anda |
| 25 | `http://GANTI_IP_SERVER:8080` | `http://192.168.100.17:8080` |

**Contoh hasil edit:**
```yaml
x-passwords:
  root_password: &root_pass PasswordRootKuat123!
  app_password: &app_pass PasswordAppKuat456!

# ...

environment:
  APP_URL: http://192.168.100.17:8080
```

**Simpan:** `Ctrl+O` → `Enter` → `Ctrl+X`

### 📌 Mengapa Harus Copy?

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  docker-compose.yml          docker-compose.prod.yml            │
│  ─────────────────           ──────────────────────            │
│  - Template (placeholder)    - Production (password asli)      │
│  - Masuk ke Git              - DI-IGNORE oleh Git              │
│  - Aman di-push              - Tidak akan ter-overwrite        │
│                                                                 │
│  Setiap git pull:                                               │
│  - docker-compose.yml berubah → OK (itu template)              │
│  - docker-compose.prod.yml tetap → Password aman!              │
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

## 7. Update Aplikasi


### ⚠️ Jangan Lupa Backup Sebelum Update

```bash
# Backup database
docker exec master-gambar-mysql sh /backup.sh

# Backup storage
bash docker/backup-storage.sh
```

### ✅ Cara Update (Password Aman!)

```bash
cd ~/laravel/master-gambar

# 1. Pull update (docker-compose.yml berubah, tapi tidak masalah)
git pull

# 2. Rebuild dan restart (gunakan file production)
docker compose -f docker-compose.prod.yml up -d --build

# 3. Verifikasi
docker compose -f docker-compose.prod.yml ps
```

**Kenapa aman?**
- `git pull` akan update `docker-compose.yml` (template)
- `docker-compose.prod.yml` tidak berubah (di-ignore Git)
- Password tetap aman!
---

## 8. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Container tidak start | `docker compose -f docker-compose.prod.yml logs app` |
| MySQL connection refused | Tunggu container mysql healthy |
| Port 8080 dipakai | Ubah port di `docker-compose.prod.yml` |
| Password salah | Edit `docker-compose.prod.yml`, lalu restart |

---

## 📁 Struktur File

| File | Keterangan | Git |
|------|------------|-----|
| `docker-compose.yml` | Template dengan placeholder | ✅ Masuk |
| `docker-compose.prod.yml` | Production dengan password asli | ❌ Ignore |
| `docker/mysql/backup.sh` | Baca password dari environment | ✅ Masuk |

---

## 🔒 Security Checklist

- [ ] `docker-compose.prod.yml` sudah dibuat dan password sudah diganti
- [ ] `docker-compose.prod.yml` ada di `.gitignore`
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

---

## 📝 Instruksi untuk Developer (Setup Gitignore)

File berikut harus di-ignore agar password tidak ter-push ke repo:

```bash
# Tambahkan ke .gitignore
echo "docker-compose.prod.yml" >> .gitignore
```

Kemudian update `docker-compose.yml` dengan placeholder:

```yaml
x-passwords:
  root_password: &root_pass GANTI_PASSWORD_ROOT_DI_SINI
  app_password: &app_pass GANTI_PASSWORD_APP_DI_SINI
```

Dan update `docker/mysql/backup.sh` untuk baca dari environment variable (sudah dilakukan).
