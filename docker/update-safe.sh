#!/bin/bash
# =====================================================================
# Safe Update Script untuk Master Gambar
# Mengupdate aplikasi TANPA menghilangkan data MySQL dan storage
#
# Jalankan:
#   cd ~/laravel/master-gambar
#   bash docker/update-safe.sh
# =====================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "  Master Gambar - Safe Update"
echo "  $(date)"
echo "=========================================="
echo ""

# Check if running in project directory
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}ERROR: Jalankan script ini di direktori project master-gambar${NC}"
    exit 1
fi

# =====================================================================
# Detect docker-compose file (prefer production if exists)
# =====================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Gunakan docker-compose.prod.yml jika ada, jika tidak gunakan docker-compose.yml
if [ -f "${PROJECT_DIR}/docker-compose.prod.yml" ]; then
    COMPOSE_FILE="${PROJECT_DIR}/docker-compose.prod.yml"
    echo -e "${GREEN}  ✓ Using production config: docker-compose.prod.yml${NC}"
else
    COMPOSE_FILE="${PROJECT_DIR}/docker-compose.yml"
    echo -e "${YELLOW}  ⚠ Production config not found, using template: docker-compose.yml${NC}"
fi

# Docker compose command with correct file
COMPOSE_CMD="docker compose -f ${COMPOSE_FILE}"

# =====================================================================
# Load secrets from centralized file
# =====================================================================
if [ -f "${SCRIPT_DIR}/.env.secrets" ]; then
    source "${SCRIPT_DIR}/.env.secrets"
    echo -e "${GREEN}  ✓ Loaded secrets from .env.secrets${NC}"
else
    echo -e "${YELLOW}  ⚠ .env.secrets not found, using environment variables${NC}"
    echo -e "${YELLOW}    Create with: cp docker/.env.secrets.example docker/.env.secrets${NC}"
fi

# =====================================================================
# STEP 1: Backup Database
# =====================================================================
echo -e "${BLUE}[1/8] Backup database MySQL...${NC}"

# Konfigurasi backup folder
BACKUP_DIR="${BACKUP_DIR:-/mnt/data/backups}"
FOLDER_NAME="$(date +%Y-%m-%d-%H:%M)-master-backup"
FOLDER_BACKUP="${BACKUP_DIR}/${FOLDER_NAME}"

mkdir -p "${FOLDER_BACKUP}"

if docker ps --format '{{.Names}}' | grep -q "master-gambar-mysql"; then

    DB_BACKUP_FILE="mysql-backup-$(date +%F-%H%M%S).sql"

    echo "  Backup database ke: ${FOLDER_BACKUP}/${DB_BACKUP_FILE}"

    # Export database dari container langsung ke host
    docker exec master-gambar-mysql \
        mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" --all-databases \
        > "${FOLDER_BACKUP}/${DB_BACKUP_FILE}"

    if [ $? -eq 0 ]; then
        DB_SIZE=$(du -sh "${FOLDER_BACKUP}/${DB_BACKUP_FILE}" | cut -f1)
        echo -e "${GREEN}  ✓ Database backup selesai (${DB_SIZE})${NC}"
    else
        echo -e "${RED}  ✗ Database backup gagal!${NC}"
    fi

else
    echo -e "${YELLOW}  ⚠ MySQL container tidak running, skip backup${NC}"
fi

echo ""

# =====================================================================
# STEP 2: Backup Storage (file PDF, PNG, ZIP)
# =====================================================================
echo -e "${BLUE}[2/8] Backup storage (file PDF/PNG/ZIP)...${NC}"
if docker ps --format '{{.Names}}' | grep -q "master-gambar-app"; then

    echo "  Backup folder: ${FOLDER_BACKUP}"

    # Copy storage dari container ke host
    docker cp master-gambar-app:/var/www/html/storage/app "${FOLDER_BACKUP}"

    if [ $? -eq 0 ]; then
        BACKUP_SIZE=$(du -sh "${FOLDER_BACKUP}" | cut -f1)
        echo -e "${GREEN}  ✓ Storage backup selesai (${BACKUP_SIZE})${NC}"
    else
        echo -e "${RED}  ✗ Storage backup gagal!${NC}"
    fi

else
    echo -e "${YELLOW}  ⚠ App container tidak running, skip storage backup${NC}"
fi

echo ""

# =====================================================================
# STEP 3: Pull latest code
# =====================================================================
echo -e "${BLUE}[3/8] Pull latest code dari Git...${NC}"
git pull
echo -e "${GREEN}  ✓ Code updated${NC}"
echo ""

# =====================================================================
# STEP 4: Stop containers (DATA TIDAK HILANG!)
# =====================================================================
echo -e "${BLUE}[4/8] Stop containers...${NC}"
echo -e "${YELLOW}  Note: Volume data (mysql-data, storage-data) TIDAK akan terhapus${NC}"
${COMPOSE_CMD} stop
echo -e "${GREEN}  ✓ Containers stopped${NC}"
echo ""

# =====================================================================
# STEP 5: Rebuild images
# =====================================================================
echo -e "${BLUE}[5/8] Rebuild Docker images...${NC}"
${COMPOSE_CMD} build --no-cache
echo -e "${GREEN}  ✓ Images rebuilt${NC}"
echo ""

# =====================================================================
# STEP 6: Start containers
# =====================================================================
echo -e "${BLUE}[6/8] Start containers...${NC}"
${COMPOSE_CMD} up -d
echo -e "${GREEN}  ✓ Containers started${NC}"
echo ""

# =====================================================================
# STEP 7: Backup Database (Setelah Update)
# =====================================================================
echo -e "${BLUE}[7/8] Backup database MySQL (Setelah Update)...${NC}"

if docker ps --format '{{.Names}}' | grep -q "master-gambar-mysql"; then
    echo "  Tunggu MySQL siap..."
    sleep 10
    
    DB_BACKUP_UPDATED_FILE="mysql-backup-updated-$(date +%Y-%m-%d-%H%M%S).sql"
    
    echo "  Backup database (updated) ke: ${FOLDER_BACKUP}/${DB_BACKUP_UPDATED_FILE}"
    
    # Export database dari container langsung ke host
    docker exec master-gambar-mysql \
        mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" --all-databases \
        > "${FOLDER_BACKUP}/${DB_BACKUP_UPDATED_FILE}"

    if [ $? -eq 0 ]; then
        DB_UPDATED_SIZE=$(du -sh "${FOLDER_BACKUP}/${DB_BACKUP_UPDATED_FILE}" | cut -f1)
        echo -e "${GREEN}  ✓ Database backup (updated) selesai (${DB_UPDATED_SIZE})${NC}"
    else
        echo -e "${RED}  ✗ Database backup (updated) gagal!${NC}"
    fi

else
    echo -e "${YELLOW}  ⚠ MySQL container tidak running, skip backup updated${NC}"
fi

echo ""

# =====================================================================
# STEP 8: Verifikasi
# =====================================================================
echo -e "${BLUE}[8/8] Verifikasi...${NC}"
sleep 5
${COMPOSE_CMD} ps

echo ""
echo "=========================================="
echo -e "${GREEN}  Update Selesai!${NC}"
echo "=========================================="
echo ""
echo "Verifikasi tambahan:"
echo "  - Cek logs: ${COMPOSE_CMD} logs -f"
echo "  - Cek health: bash docker/healthcheck.sh"
echo "  - Test aplikasi: curl -I http://localhost:8080"
echo ""
echo -e "${YELLOW}Data yang AMAN (tidak terhapus):${NC}"
echo "  ✓ MySQL database (volume: mysql-data)"
echo "  ✓ File storage (volume: storage-data)"
echo "  ✓ Backup database (volume: mysql-backup)"
echo "  ✓ Backup storage (folder: /mnt/data/backups/)"
echo "  ✓ Shared code (volume: app-code)"
