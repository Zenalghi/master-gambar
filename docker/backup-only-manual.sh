#!/bin/bash
# =====================================================================
# Backup Manual Script untuk Master Gambar
# Backup database MySQL dan folder storage/app ke host (tanpa update kode)
#
# Jalankan manual:
#   bash docker/backup-only-manual.sh
# =====================================================================

set -e

# =====================================================================
# KONFIGURASI - SESUAIKAN DENGAN SERVER ANDA
# =====================================================================

# Folder backup di host (di luar container)
BACKUP_DIR="${BACKUP_DIR:-/mnt/data/backups}"

# Nama folder backup sesuai format yang diminta
FOLDER_NAME="$(date +%Y-%m-%d-%H:%M)-master-backup-manual"

# Load secrets for MySQL Password
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
if [ -f "${PROJECT_DIR}/.env.production" ]; then
    source "${PROJECT_DIR}/.env.production"
else
    echo -e "\033[1;33mPeringatan: file .env.production tidak ditemukan, password database mungkin kosong.\033[0m"
fi

# Path folder storage di dalam container
CONTAINER_STORAGE_PATH="/var/www/html/storage/app"

# Nama container
CONTAINER_NAME="master-gambar-app"

# Retention: hapus backup lebih dari X hari (default: 14 hari)
RETENTION_DAYS="${RETENTION_DAYS:-14}"

# =====================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "  Master Gambar - Storage Backup"
echo "  $(date)"
echo "=========================================="
echo ""

# Step 1: Cek container running
echo -e "${BLUE}[1/5] Cek container...${NC}"
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo -e "${RED}ERROR: Container '${CONTAINER_NAME}' tidak running!${NC}"
    echo "  -> Jalankan: docker compose up -d"
    exit 1
fi
echo -e "${GREEN}  Container running${NC}"
echo ""

# Step 2: Buat folder backup
echo -e "${BLUE}[2/5] Buat folder backup...${NC}"
FOLDER_BACKUP="${BACKUP_DIR}/${FOLDER_NAME}"
mkdir -p "${FOLDER_BACKUP}"
echo "  Backup folder: ${FOLDER_BACKUP}"
echo ""

# Step 3: Backup database MySQL
echo -e "${BLUE}[3/6] Backup database MySQL...${NC}"
if docker ps --format '{{.Names}}' | grep -q "master-gambar-mysql"; then
    DB_BACKUP_FILE="mysql-backup-manual-$(date +%Y-%m-%d-%H%M%S).sql"
    echo "  Backup database ke: ${FOLDER_BACKUP}/${DB_BACKUP_FILE}"
    
    docker exec master-gambar-mysql \
        mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" --all-databases \
        > "${FOLDER_BACKUP}/${DB_BACKUP_FILE}"
        
    if [ $? -eq 0 ]; then
        DB_SIZE=$(du -sh "${FOLDER_BACKUP}/${DB_BACKUP_FILE}" | cut -f1)
        echo -e "${GREEN}  ✓ Database backup selesai (${DB_SIZE})${NC}"
    else
        echo -e "${RED}  ✗ Database backup gagal! Pastikan MYSQL_ROOT_PASSWORD di .env.production sudah benar.${NC}"
    fi
else
    echo -e "${YELLOW}  ⚠ MySQL container tidak running, skip backup database${NC}"
fi
echo ""

# Step 4: Copy storage dari container ke host
echo -e "${BLUE}[4/6] Copy storage dari container...${NC}"
echo "  Source: ${CONTAINER_NAME}:${CONTAINER_STORAGE_PATH}"
echo "  Destination: ${FOLDER_BACKUP}"

# Cek apakah folder storage ada di container
if ! docker exec "${CONTAINER_NAME}" test -d "${CONTAINER_STORAGE_PATH}"; then
    echo -e "${RED}ERROR: Folder storage tidak ditemukan di container!${NC}"
    exit 1
fi

# Copy dengan progress
docker cp "${CONTAINER_NAME}:${CONTAINER_STORAGE_PATH}" "${FOLDER_BACKUP}"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}  Copy selesai${NC}"
else
    echo -e "${RED}ERROR: Copy gagal!${NC}"
    exit 1
fi
echo ""

# Step 5: Tampilkan info backup
echo -e "${BLUE}[5/6] Info backup...${NC}"
BACKUP_SIZE=$(du -sh "${FOLDER_BACKUP}" | cut -f1)
FILE_COUNT=$(find "${FOLDER_BACKUP}" -type f | wc -l)
echo "  Ukuran backup: ${BACKUP_SIZE}"
echo "  Jumlah file: ${FILE_COUNT}"
echo ""

# Step 6: Cleanup backup lama
echo -e "${BLUE}[6/6] Cleanup backup lama (${RETENTION_DAYS} hari)...${NC}"
DELETED=$(find "${BACKUP_DIR}" -maxdepth 1 -name "master-*" -type d -mtime +${RETENTION_DAYS} | wc -l)
find "${BACKUP_DIR}" -maxdepth 1 -name "master-*" -type d -mtime +${RETENTION_DAYS} -exec rm -rf {} \;
echo "  Backup dihapus: ${DELETED} folder"
echo ""

# Summary
echo "=========================================="
echo -e "${GREEN}  Backup Database & Storage Selesai!${NC}"
echo "=========================================="
echo ""
echo "  Lokasi: ${FOLDER_BACKUP}"
echo "  Ukuran: ${BACKUP_SIZE}"
echo "  File: ${FILE_COUNT}"
echo ""
echo "  Total backup di ${BACKUP_DIR}:"
ls -lh "${BACKUP_DIR}" | grep "^d" | wc -l
echo ""
echo "  Space tersedia:"
df -h "${BACKUP_DIR}" | tail -1
