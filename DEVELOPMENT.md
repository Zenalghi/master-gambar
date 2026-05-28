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

HANYA SATU file yang perlu kamu buat sendiri:

| File | Cara Buat | Keterangan |
|------|-----------|------------|
| `docker/.env.dev` | `cp docker/.env.dev.example docker/.env.dev` | Password & konfigurasi lokal |

File-file berikut SUDAH ADA di repo (tinggal pull, tidak perlu buat):

- `docker-compose.dev.yml` — Docker compose untuk development
- `docker/php/Dockerfile.dev` — Dockerfile dengan Xdebug
- `docker/php/docker-entrypoint.dev.sh` — Entrypoint otomatis
- `docker/.env.dev.example` — Template env (referensi)

Cukup jalankan:
```bash
cp docker/.env.dev.example docker/.env.dev
nano docker/.env.dev   # edit password saja
```

---

## 4. Setup Development

### 4.1 Clone Repository

```bash
cd ~/laravel
git clone https://github.com/Zenalghi/master-gambar.git master-gambar-dev
cd master-gambar-dev
```

### 4.2 Buat File Development

Hanya satu langkah — copy template env dan edit password:

```bash
cp docker/.env.dev.example docker/.env.dev
nano docker/.env.dev    # Ganti CHANGE_ME_* dengan password pilihanmu
```

### 4.3 Build & Jalankan

```bash
cd ~/laravel/master-gambar-dev

# Build dan jalankan development environment
docker compose -f docker-compose.dev.yml up -d --build
```

**Selesai.** Entrypoint otomatis akan:
- Generate `APP_KEY` jika belum ada
- Menunggu MySQL siap
- Menjalankan `migrate --force`
- Clear cache untuk development

### 4.4 Verifikasi

```bash
# Cek status container
docker compose -f docker-compose.dev.yml ps

# Test aplikasi
curl -I http://localhost:8081

# Akses di browser
# http://192.168.100.173:8081
```

Jika container `app-dev` restart terus, cek logs:
```bash
docker compose -f docker-compose.dev.yml logs app-dev
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
# Start development environment (auto: APP_KEY + migrate + cache clear)
docker compose -f docker-compose.dev.yml up -d

# Lihat logs
docker compose -f docker-compose.dev.yml logs -f app-dev

# Run artisan commands
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

**Catatan:** Entrypoint otomatis menjalankan `migrate --force` setiap container start. Jadi migration otomatis terdeteksi saat `docker compose up -d`.

Jika kamu pull code baru dan ada migration baru, cukup restart:
```bash
docker compose -f docker-compose.dev.yml up -d --build
```

Atau untuk restart cepat (tanpa rebuild):
```bash
docker compose -f docker-compose.dev.yml restart app-dev
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
│   ├── docker-compose.dev.yml      # SUDAH ADA di repo
│   ├── docker/
│   │   ├── .env.dev.example        # SUDAH ADA (template)
│   │   ├── .env.dev                # BUAT SENDIRI (gitignore)
│   │   └── php/
│   │       ├── Dockerfile.dev      # SUDAH ADA di repo
│   │       └── docker-entrypoint.dev.sh  # SUDAH ADA di repo
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
- Jangan pernah push `docker/.env.dev` ke Git (file lain sudah aman)

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
