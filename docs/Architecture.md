# 📐 Arsitektur Sistem - Master Gambar

Dokumen ini menjelaskan **desain arsitektur** proyek Master Gambar:
bagaimana komponen-komponen saling terhubung, bagaimana alur traffic berjalan,
dan bagaimana sistem dibangun agar siap menampung banyak aplikasi di masa depan.

> Untuk panduan operasional sehari-hari (monitoring, troubleshooting, resource),
> lihat: [`Server.md`](./Server.md)

---

## Konsep Multi-App Shared Infrastructure

Server ini memisahkan antara **layer Infrastruktur** dengan **layer Aplikasi**.

```
/srv/workspace/
├── infra/              ← Shared Infrastructure (MySQL, NPM, Backup)
├── apps/
│   └── master-gambar/  ← Application Service (PHP-FPM, Nginx internal)
└── logs/               ← Log output dari berbagai cron backup aplikasi
```

### Mengapa Dipisahkan?
- **Hemat RAM**: 1 MySQL melayani semua aplikasi, bukan 1 MySQL per app.
- **Mudah Diperluas**: Tambah app baru cukup sambungkan ke `rekayasa-network`.
- **Manajemen Terpusat**: Backup, proxy, dan SSL dikelola dari satu tempat.

---

## Komponen

### 1. Infra (`infra/docker-compose.yml`)
| Service | Image | Fungsi |
|---------|-------|--------|
| `infra-mysql` | mysql:9.7.0 | Database tunggal untuk semua app (port internal 3306) |
| `infra-nginx-proxy-manager` | jc21/nginx-proxy-manager | Reverse proxy + SSL + routing berdasarkan domain |

### 2. Aplikasi (`master-gambar/docker-compose.prod.yml`)
| Service | Image | Fungsi |
|---------|-------|--------|
| `master-gambar-app` | master-gambar:production | PHP-FPM, menjalankan kode Laravel |
| `master-gambar-nginx` | nginx:1.27-alpine | Web server internal, menerima request dari NPM |

---

## Docker Network

Semua komponen terhubung melalui Docker external network bernama `rekayasa-network`.

```
┌─ infra (docker-compose.yml) ──────────────────────────┐
│                                                        │
│  infra-mysql ◄──── rekayasa-network ────► infra-npm    │
│                         ▲                              │
└─────────────────────────┼──────────────────────────────┘
                          │
┌─ master-gambar ─────────┼──────────────────────────────┐
│                         │                              │
│  master-gambar-app ◄────┘                              │
│       ▲                                                │
│       │ (fastcgi :9000)                                │
│  master-gambar-nginx                                   │
│                                                        │
└────────────────────────────────────────────────────────┘
```

- `infra` **menciptakan** `rekayasa-network` saat `docker compose up -d`.
- `master-gambar` **menempel** ke `rekayasa-network` sebagai network *external*.

---

## Flow Traffic (Internet → Aplikasi)

```text
Internet / LAN
      ↓
(Port 80 / 443)
Nginx Proxy Manager (infra-nginx-proxy-manager)
      ↓
(Docker Network internal, rekayasa-network)
nginx container aplikasi (master-gambar-nginx, port 80)
      ↓
(FastCGI, port 9000)
php-fpm container aplikasi (master-gambar-app)
      ↓
(TCP, port 3306)
MySQL (infra-mysql)
```

> **Penting:** Nginx aplikasi **TIDAK** membuka port ke host. Semua traffic
> masuk melalui NPM di layer infra.

---

## Isolasi Database per Aplikasi

Setiap aplikasi memiliki database dan user **terpisah** di dalam satu MySQL server.

| Aplikasi | Database | User | Hak Akses |
|----------|----------|------|-----------|
| Master Gambar | `master_gambar_db` | `master_gambar_user` | Hanya `master_gambar_db` |
| *(Contoh: Inventory)* | `inventory_db` | `inventory_user` | Hanya `inventory_db` |
| *(Contoh: Absensi)* | `absensi_db` | `absensi_user` | Hanya `absensi_db` |

User `root` hanya digunakan oleh admin untuk manajemen via HeidiSQL (SSH Tunnel).

---

## Keamanan

1. **Port MySQL hanya di-expose ke localhost (127.0.0.1).** Tidak terbuka untuk LAN/Internet. Akses HeidiSQL via SSH Tunnel.
2. **Credential terpisah:**
   - `infra/.env` → `MYSQL_ROOT_PASSWORD` (hanya admin)
   - `master-gambar/.env.production` → `DB_USERNAME=master_gambar_user` (hanya app)
3. **Tidak ada phpMyAdmin** di dev maupun prod. Gunakan HeidiSQL/DBeaver.
4. `.env.production` di-ignore dari Git.

---

## Strategi Backup

| Jenis | Cakupan | Script | Cron |
|-------|---------|--------|------|
| Infra Backup | All databases + NPM config + SSL | `infra/backup/autobackup.sh` | `0 12 * * *` |
| App Backup | `master_gambar_db` + `storage/app/master/` | `master-gambar/docker/autobackup.sh` | `15 12 * * *` |

---

## Cara Menambah Aplikasi Baru

1. Clone/buat project baru (misal `inventory`).
2. Buat `docker-compose.prod.yml` yang menempel ke `rekayasa-network` (external).
3. Di `.env.production` app baru, set `DB_HOST=infra-mysql`, `DB_DATABASE=inventory_db`, `DB_USERNAME=inventory_user`.
4. Masuk ke MySQL sebagai root, buat database dan user baru:
   ```sql
   CREATE DATABASE inventory_db;
   CREATE USER 'inventory_user'@'%' IDENTIFIED BY 'PasswordKuat!';
   GRANT ALL PRIVILEGES ON inventory_db.* TO 'inventory_user'@'%';
   FLUSH PRIVILEGES;
   ```
5. Daftarkan domain/subdomain di Nginx Proxy Manager.
6. Tambahkan path baru di `infra/setup-autostart.sh` agar ikut menyala saat boot.
