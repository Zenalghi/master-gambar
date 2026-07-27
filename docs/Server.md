# 🔧 Server Maintenance - Master Gambar

Dokumen ini berisi **panduan operasional sehari-hari** untuk mengelola server:
monitoring, resource usage, troubleshooting, dan perintah-perintah yang sering digunakan.

> Untuk memahami **desain dan arsitektur** sistem, lihat: [`Architecture.md`](./Architecture.md)
> Untuk panduan **deploy dari nol**, lihat: [`Production.md`](./Production.md)

---

## 📋 Daftar Isi

1. [Konfigurasi Resource](#konfigurasi-resource)
2. [Monitoring](#monitoring)
3. [Perintah Maintenance](#perintah-maintenance)
4. [Troubleshooting](#troubleshooting)

---

## Konfigurasi Resource

### Docker Resource Limits (Production)

| Container | Memory Limit | CPU Limit | Memory Reservation | CPU Reservation |
|-----------|-------------|-----------|-------------------|-----------------| 
| master-gambar-app (PHP-FPM) | 3 GB | 3.0 cores | 1 GB | 1.5 cores |
| master-gambar-nginx | 256 MB | 0.5 cores | 128 MB | 0.25 cores |
| infra-mysql (Shared) | 3 GB | 2.0 cores | 1 GB | 1.0 core |
| infra-nginx-proxy-manager | — | — | — | — |

**Spesifikasi Server:** Intel i5 Gen 12, RAM 16 GB

### PHP Configuration (`docker/php/php.ini`)

| Setting | Value | Keterangan |
|---------|-------|------------|
| `memory_limit` | 2048M | Untuk PDF/Ghostscript processing |
| `upload_max_filesize` | 128M | Upload file besar |
| `post_max_size` | 128M | POST data limit |
| `max_execution_time` | 600 | 10 menit untuk proses PDF lama |

### MySQL Configuration (`infra/mysql/custom.cnf`)

| Setting | Value | Keterangan |
|---------|-------|------------|
| `innodb_buffer_pool_size` | 1280M | Buffer pool untuk query cepat |
| `max_connections` | 150 | Maksimal koneksi bersamaan |
| `innodb_io_capacity` | 2000 | I/O performance |

### Nginx Configuration (`docker/nginx/default.prod.conf`)

| Setting | Value | Keterangan |
|---------|-------|------------|
| `client_max_body_size` | 256M | Upload file besar |
| `proxy_read_timeout` | 600s | Timeout untuk proses lama |
| `fastcgi_read_timeout` | 600s | Timeout untuk PHP-FPM |

---

## Monitoring

### Cek Status Container

```bash
# Semua container (infra + app)
docker ps

# Hanya infra
cd /srv/workspace/infra && docker compose ps

# Hanya master-gambar
cd /srv/workspace/apps/master-gambar && docker compose -f docker-compose.prod.yml ps
```

### Resource Usage

```bash
docker stats
```

### Cek Logs

```bash
# Logs aplikasi
cd /srv/workspace/apps/master-gambar
docker compose -f docker-compose.prod.yml logs -f

# Logs MySQL (infra)
cd /srv/workspace/infra
docker compose logs -f mysql

# Logs NPM (infra)
docker compose logs -f nginx-proxy-manager
```

### Cek Healthcheck

```bash
docker inspect --format='{{json .State.Health.Status}}' master-gambar-app
docker inspect --format='{{json .State.Health.Status}}' infra-mysql
```

---

## Perintah Maintenance

### Restart Container

```bash
# Restart aplikasi saja
cd /srv/workspace/apps/master-gambar
docker compose -f docker-compose.prod.yml restart

# Restart infra (HATI-HATI: akan memengaruhi semua app!)
cd /srv/workspace/infra
docker compose restart mysql
```

### Rebuild & Redeploy Aplikasi

```bash
cd /srv/workspace/apps/master-gambar
bash docker/update-safe.sh
```

### Manual Backup

```bash
# Backup infra (all databases + NPM + SSL)
cd /srv/workspace/infra && bash backup/autobackup.sh

# Backup aplikasi (1 database + storage)
cd /srv/workspace/apps/master-gambar && bash docker/autobackup.sh
```

### Restore Database

```bash
# Restore dari file SQL ke MySQL infra
cat backup_file.sql | docker exec -i infra-mysql mysql -u root -p master_gambar_db
```

### Cleanup

```bash
# Hapus unused images
docker image prune -a

# Hapus unused volumes (HATI-HATI: pastikan tidak ada volume aktif!)
docker volume prune

# Hapus unused networks
docker network prune
```

### 🧹 Perawatan Berkala (Rebuild dari Nol - Setiap 6 Bulan Sekali)

Untuk alur kerja rutin harian/mingguan, sistem otomatis memanfaatkan memori *cache* agar proses update (CI/CD atau `update-safe.sh`) melesat dalam waktu 5-10 detik.  
Namun, **setiap 6 bulan sekali** (atau jika ingin membersihkan riwayat cache lama dan menyegarkan paket pemeliharaan Linux OS dari nol), jalankan perintah berikut secara manual di terminal server:

```bash
cd /srv/workspace/apps/master-gambar
docker compose -f docker-compose.prod.yml build --no-cache && docker compose -f docker-compose.prod.yml up -d
```
*(Proses ini akan memakan waktu sekitar 10-15 menit untuk mengunduh ulang seluruh kemasan dari awal).*

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Container restart loop | `docker compose -f docker-compose.prod.yml logs app` |
| MySQL connection refused | Pastikan `infra-mysql` sudah healthy: `docker ps` |
| Aplikasi tidak bisa diakses dari browser | Cek konfigurasi domain di NPM (port 81) |
| Permission denied saat menjalankan docker | `sudo usermod -aG docker $USER` lalu logout/login |
| Container tidak start setelah reboot | `sudo systemctl status docker-compose-apps` |
| Healthcheck gagal | `docker inspect master-gambar-app` |
| Disk penuh | `docker system prune -a` dan cek log rotation |
| Backup gagal | Cek password di `.env.production` dan container `infra-mysql` running |
| PDF processing crash (memory) | Naikkan `memory_limit` di `docker/php/php.ini` |
| Upload file gagal | Cek `upload_max_filesize` (php.ini) dan `client_max_body_size` (nginx) |
| Proses PDF timeout | Naikkan `max_execution_time` di `docker/php/php.ini` |

---

## File Konfigurasi Penting

| File | Lokasi | Fungsi |
|------|--------|--------|
| `docker/php/php.ini` | Aplikasi | Konfigurasi PHP (memory, upload, timeout) |
| `docker/nginx/default.prod.conf` | Aplikasi | Konfigurasi Nginx web server |
| `infra/mysql/custom.cnf` | Infra | Konfigurasi MySQL server |
| `.env.production` | Aplikasi | Credential database aplikasi |
| `infra/.env` | Infra | Credential root MySQL |

---

## Security Notes

1. **MySQL port hanya di-expose ke localhost (127.0.0.1).** Tidak terbuka untuk publik/LAN. Gunakan SSH Tunnel untuk akses HeidiSQL.
2. **`.env.production`** harus di-ignore oleh Git (sudah ada di `.gitignore`).
3. **`APP_DEBUG`** harus `false` di production.
4. **Log rotation** sudah dikonfigurasi di docker-compose untuk mencegah disk penuh.
5. **Gunakan password yang kuat** dan berbeda antara kredensial root di server Infra dengan `DB_PASSWORD` di `.env.production` aplikasi.
