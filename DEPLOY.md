# 📚 Dokumentasi Teknis - Master Gambar

Dokumentasi teknis untuk maintenance dan troubleshooting aplikasi Master Gambar.

> **Untuk panduan deploy production, lihat:** [`PRODUCTION_DEPLOY.md`](./PRODUCTION_DEPLOY.md)

---

## 📋 Daftar Isi

1. [Arsitektur Sistem](#arsitektur-sistem)
2. [Konfigurasi Resource](#konfigurasi-resource)
3. [Backup & Restore](#backup--restore)
4. [Maintenance](#maintenance)
5. [Troubleshooting](#troubleshooting)
6. [File Konfigurasi](#file-konfigurasi)

---

## Arsitektur Sistem

```
                    ┌─────────────────────────────────────┐
                    │           Server 192.168.100.17     │
                    │                                     │
   Browser ──────►  │  :8080  ┌─────────┐  :9000          │
                    │ ──────► │  nginx  │ ──────►         │
                    │         └─────────┘                 │
                    │                        ┌─────────┐  │
                    │                        │   app   │  │
                    │                        │ PHP-FPM │  │
                    │                        └────┬────┘  │
                    │                             │       │
                    │                          :3306      │
                    │                             ▼       │
                    │                        ┌─────────┐  │
                    │                        │  mysql  │  │
                    │                        └─────────┘  │
                    │                                     │
                    └─────────────────────────────────────┘
```

### Services

| Service | Image | Port | Container Name |
|---------|-------|------|----------------|
| nginx | nginx:1.27-alpine | 8080 | master-gambar-nginx |
| app | master-gambar:production | 9000 | master-gambar-app |
| mysql | mysql:9.7.0 | 3307 (localhost only) | master-gambar-mysql |

### Volumes

| Volume | Isi | Lokasi di Container |
|--------|-----|---------------------|
| `mysql-data` | Database MySQL | `/var/lib/mysql` |
| `app-shared` | Shared code untuk nginx | `/app-shared` |
| `storage-data` | File PDF/PNG/ZIP | `/var/www/html/storage/app` |
| `mysql-backup` | Backup database | `/backup` |

---

## Konfigurasi Resource

### Docker Resource Limits

| Container | Memory Limit | CPU Limit | Memory Reservation | CPU Reservation |
|-----------|-------------|-----------|-------------------|-----------------|
| app (PHP-FPM) | 3 GB | 3.0 cores | 1 GB | 1.5 cores |
| nginx | 256 MB | 0.5 cores | 128 MB | 0.25 cores |
| mysql | 3 GB | 2.0 cores | 1.5 GB | 1.0 core |

**Total yang dibutuhkan:** ~6.25 GB RAM, ~5.5 cores

### PHP Configuration (`docker/php/php.ini`)

| Setting | Value | Keterangan |
|---------|-------|------------|
| `memory_limit` | 2048M | Untuk PDF/Ghostscript processing |
| `upload_max_filesize` | 128M | Upload file besar |
| `post_max_size` | 128M | POST data limit |
| `max_execution_time` | 600 | 10 menit untuk proses PDF lama |

### MySQL Configuration (`docker/mysql/custom.cnf`)

| Setting | Value | Keterangan |
|---------|-------|------------|
| `innodb_buffer_pool_size` | 1280M | Buffer pool untuk query cepat |
| `max_connections` | 150 | Maksimal koneksi bersamaan |
| `innodb_io_capacity` | 2000 | I/O performance |

### Nginx Configuration (`docker/nginx/default.conf`)

| Setting | Value | Keterangan |
|---------|-------|------------|
| `client_max_body_size` | 256M | Upload file besar |
| `proxy_read_timeout` | 600s | Timeout untuk proses lama |
| `fastcgi_read_timeout` | 600s | Timeout untuk PHP-FPM |

---

## Backup & Restore

### Backup Schedule

| Backup | Waktu | Script | Retensi |
|--------|-------|--------|---------|
| Database MySQL | 12:00 siang | `docker exec master-gambar-mysql sh /backup.sh` | 7 hari |
| Storage (PDF/PNG/ZIP) | 12:30 siang | `bash docker/backup-storage.sh` | 14 hari |

### Manual Backup

```bash
# Backup database
docker exec master-gambar-mysql sh /backup.sh

# Backup storage
bash docker/healthcheck.sh
```

### Restore Database

```bash
# List backup files
docker exec master-gambar-mysql ls -lh /backup

# Restore dari file backup
docker exec -i master-gambar-mysql mysql -u root -p db_master < backup_file.sql
```

### Restore Storage

```bash
# Copy backup kembali ke container
docker cp ~/laravel/backups/master-2026-05-27-1200/app master-gambar-app:/var/www/html/storage/
```

---

## Maintenance

### Update Aplikasi

```bash
# Gunakan script update-safe (backup otomatis)
bash docker/update-safe.sh
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

### Monitoring

```bash
# Resource usage
docker stats

# Healthcheck
bash docker/healthcheck.sh

# Logs
docker compose logs -f
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

## Troubleshooting

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
| PDF processing crash (memory) | Naikkan `memory_limit` di `docker/php/php.ini` |
| Upload file gagal | Cek `upload_max_filesize` dan `client_max_body_size` |
| Proses PDF timeout | Naikkan `max_execution_time` di `docker/php/php.ini` |

---

## File Konfigurasi

| File | Isi | Di-edit? |
|------|-----|----------|
| `docker-compose.yml` | Service definition, password, resource limits | Ya (password & APP_URL) |
| `.env.docker.example` | Environment variables, password | Ya (password & APP_URL) |
| `docker/php/php.ini` | PHP configuration | Opsional |
| `docker/mysql/custom.cnf` | MySQL configuration | Opsional |
| `docker/nginx/default.conf` | Nginx configuration | Opsional |
| `docker/mysql/backup.sh` | Database backup script | Tidak |
| `docker/backup-storage.sh` | Storage backup script | Tidak |
| `docker/healthcheck.sh` | Healthcheck script | Tidak |
| `docker/setup-autostart.sh` | Auto-start setup | Jalankan sekali |
| `docker/update-safe.sh` | Safe update script | Jalankan saat update |

---

## 🔒 Security Notes

1. **Password MySQL** harus diganti dari default di:
   - `docker-compose.yml` (baris 6-7)

2. **MySQL port** hanya bisa diakses dari localhost (`127.0.0.1:3307`)

3. **APP_DEBUG** harus `false` di production

4. **Log rotation** sudah dikonfigurasi untuk mencegah disk penuh
