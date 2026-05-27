# Deploy Checklist - Master Gambar

## Requirement Server
- Ubuntu 22.04+ / Debian 12
- Docker + Docker Compose
- Git
- RAM minimal 4 GB (untuk 30-50 users)
- Storage minimal 20 GB

## Langkah Deploy

### 1. Install Docker
```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sh
sudo systemctl enable docker
sudo usermod -aG docker $USER
newgrp docker
```

### 2. Clone Repo
```bash
git clone <REPO_URL> master-gambar
cd master-gambar
```

### 3. Setup Environment
```bash
cp .env.docker.example .env.docker
nano .env.docker
```
Ubah:
- `APP_URL` = http://<IP_SERVER>:8080
- `MYSQL_ROOT_PASSWORD` = password root MySQL
- `MYSQL_PASSWORD` = password user app MySQL

### 4. Build dan Jalankan
```bash
docker compose up -d --build
```

### 5. Setup Auto-Start & Backup (PENTING!)
```bash
sudo bash docker/setup-autostart.sh
```
Script ini akan:
- Mengaktifkan Docker auto-start saat boot
- Mengatur backup otomatis setiap jam 2 pagi
- Mengatur log rotation

### 6. Cek Status
```bash
docker compose ps
```
Semua container harus Up dan Healthy.

### 7. Verifikasi
```bash
curl -I http://localhost:8080
```
Harus return HTTP 200.

### 8. Healthcheck
```bash
bash docker/healthcheck.sh
```
Menampilkan status lengkap semua container dan resource usage.

---

## 🔄 Auto-Restart Policy

Semua container sudah dikonfigurasi dengan `restart: always`, artinya:

| Kondisi | Perilaku |
|---------|----------|
| Container crash | Otomatis restart |
| Docker daemon restart | Otomatis start |
| Server reboot | Otomatis start saat Docker aktif |
| Healthcheck gagal | Otomatis restart |

**Pastikan Docker service aktif saat boot:**
```bash
sudo systemctl enable docker
```

---

## 💾 Backup Database

### Setup Cron Job (Backup otomatis setiap jam 2 pagi)
```bash
crontab -e
```
Tambahkan:
```cron
# Backup MySQL setiap jam 2 pagi
0 2 * * * docker exec master-gambar-mysql sh /backup.sh
```

### Manual Backup
```bash
docker exec master-gambar-mysql sh /backup.sh
```

### Restore Backup
```bash
# List backup files
docker exec master-gambar-mysql ls -lh /backup

# Restore dari file backup
docker exec -i master-gambar-mysql mysql -u root -p db_master < backup_file.sql
```

### Retention Policy
- Backup disimpan di volume `mysql-backup`
- Backup otomatis dihapus setelah 7 hari
- Pastikan volume backup memiliki cukup space

---

## 📊 Resource Limits

| Container | Memory Limit | CPU Limit | Memory Reservation | CPU Reservation |
|-----------|-------------|-----------|-------------------|-----------------|
| app (PHP-FPM) | 1 GB | 2.0 cores | 512 MB | 1.0 core |
| nginx | 256 MB | 0.5 cores | 128 MB | 0.25 cores |
| mysql | 2 GB | 2.0 cores | 1 GB | 1.0 core |

**Total yang dibutuhkan:** ~3.25 GB RAM, ~4.5 cores

### Monitoring Resource Usage
```bash
docker stats
```

---

## 🔒 Security Hardening

| Item | Status | Keterangan |
|------|--------|------------|
| MySQL port | ✅ Aman | Hanya bisa diakses dari localhost (`127.0.0.1:3307`) |
| Resource limits | ✅ Aktif | Mencegah container habis resource |
| Logging rotation | ✅ Aktif | Max 20MB per file, 5 files per container |
| Healthcheck | ✅ Aktif | Semua container termonitor |

---

## 📝 Logging

### Melihat Logs
```bash
# Semua container
docker compose logs -f

# Container tertentu
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f mysql
```

### Log Rotation
- Driver: `json-file`
- Max size: 10-20 MB per file
- Max files: 3-5 files per container
- Total log per container: max 100 MB

---

## 🔧 Maintenance

### Update Aplikasi
```bash
git pull
docker compose up -d --build
```

### Restart Container
```bash
# Restart semua
docker compose restart

# Restart tertentu
docker compose restart app
```

### Rebuild Container
```bash
docker compose up -d --build --force-recreate
```

### Cleanup
```bash
# Hapus unused images
docker image prune -a

# Hapus unused volumes (HATI-HATI)
docker volume prune

# Hapus unused networks
docker network prune
```

---

## 🚨 Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Container restart loop | `docker compose logs app` |
| MySQL connection refused | Tunggu container mysql healthy dulu |
| Port 8080 dipakai | Ubah ports di docker-compose.yml |
| Permission denied | `sudo usermod -aG docker $USER` lalu reconnect |
| Container tidak start setelah reboot | `sudo systemctl enable docker` |
| Healthcheck gagal | `docker inspect master-gambar-app` |
| Disk penuh | `docker system prune -a` dan cek log rotation |
| Backup gagal | Cek volume `mysql-backup` dan space |

---

## 📁 File Config

| File | Isi | Di-edit? |
|------|-----|----------|
| `.env.docker` | APP_URL, MySQL password, port | Ya |
| `.env` | Docker Compose variables | Tidak (auto) |
| `docker-compose.yml` | Service definition | Tidak |
| `docker/mysql/custom.cnf` | MySQL configuration | Opsional |
| `docker/mysql/backup.sh` | Backup script | Tidak |
| `docker/healthcheck.sh` | Healthcheck script | Tidak |
| `docker/setup-autostart.sh` | Auto-start setup | Jalankan sekali |
