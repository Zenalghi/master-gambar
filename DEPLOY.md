# Deploy Checklist - Master Gambar

## Requirement Server
- Ubuntu 22.04+ / Debian 12
- Docker + Docker Compose
- Git
- RAM minimal 2 GB

## Langkah Deploy

### 1. Install Docker
```
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sh
sudo systemctl enable docker
sudo usermod -aG docker $USER
newgrp docker
```

### 2. Clone Repo
```
git clone <REPO_URL> master-gambar
cd master-gambar
```

### 3. Setup Environment
```
cp .env.docker.example .env.docker
nano .env.docker
```
Ubah:
- `APP_URL` = http://<IP_SERVER>:8080
- `MYSQL_ROOT_PASSWORD` = password root MySQL
- `MYSQL_PASSWORD` = password user app MySQL

### 4. Build dan Jalankan
```
docker compose up -d --build
```

### 5. Cek Status
```
docker compose ps
```
Semua container harus Up dan Healthy.

### 6. Verifikasi
```
curl -I http://localhost:8080
```
Harus return HTTP 200.

## File Config

| File | Isi | Di-edit? |
|------|-----|----------|
| `.env.docker` | APP_URL, MySQL password, port | Ya |
| `.env` | Docker Compose variables | Tidak (auto) |
| `docker-compose.yml` | Service definition | Tidak |

## Update Aplikasi
```
git pull
docker compose up -d --build
```

## Troubleshooting
| Masalah | Solusi |
|---------|--------|
| Container restart loop | docker compose logs app |
| MySQL connection refused | Tunggu container mysql healthy dulu |
| Port 8080 dipakai | Ubah ports di docker-compose.yml |
| Permission denied | sudo usermod -aG docker $USER lalu reconnect |
