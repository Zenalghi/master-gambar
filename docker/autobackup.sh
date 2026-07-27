#!/bin/bash
# =====================================================================
# Backup Aplikasi - Master Gambar
# =====================================================================
# Script ini membackup:
# 1. Database master_gambar_db (1 database saja, dari MySQL infra)
# 2. Storage aplikasi (file PDF, gambar, dll)
#
# Struktur output:
#   /mnt/data/backups/<TANGGAL>-master-autobackup/
#   ├── master_gambar_db-<TANGGAL>.sql
#   └── app/
#       └── master/
# =====================================================================

set -e

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="${PROJECT_DIR}/.env.production"

# Load .env.production
if [ -f "${ENV_FILE}" ]; then
    source "${ENV_FILE}"
else
    echo "ERROR: File .env.production tidak ditemukan!"
    exit 1
fi

# Konfigurasi backup lokasi
BACKUP_DIR="${BACKUP_DIR:-/mnt/data/backups}"
DATE_TAG="$(date +%Y-%m-%d-%H%M)"
FOLDER_NAME="${DATE_TAG}-master-autobackup"
FOLDER_BACKUP="${BACKUP_DIR}/${FOLDER_NAME}"

DB_NAME="${DB_DATABASE:-master_gambar_db}"
DB_USER="${DB_USERNAME:-master_gambar_user}"
DB_PASS="${DB_PASSWORD}"

echo "=========================================="
echo "  Master Gambar - Auto Backup (App & DB)"
echo "  $(date)"
echo "=========================================="
echo ""

mkdir -p "${FOLDER_BACKUP}"

# =====================================================================
# 1. Backup Database (Khusus database aplikasi ini)
# =====================================================================
echo "[1/2] Backup database MySQL (${DB_NAME})..."
MYSQL_CONTAINER="infra-mysql"

if docker ps --format '{{.Names}}' | grep -q "${MYSQL_CONTAINER}"; then
    DB_BACKUP_FILE="${DB_NAME}-$(date +%F-%H%M%S).sql"
    
    echo "  Backup database ke: ${FOLDER_BACKUP}/${DB_BACKUP_FILE}"
    
    docker exec -e MYSQL_PWD="${DB_PASS}" "${MYSQL_CONTAINER}" \
        mysqldump -u "${DB_USER}" --no-tablespaces --skip-masking-policies --skip-add-drop-masking-policy --set-gtid-purged=OFF --single-transaction --routines --events \
        "${DB_NAME}" > "${FOLDER_BACKUP}/${DB_BACKUP_FILE}"
    
    if [ $? -eq 0 ]; then
        DB_SIZE=$(du -sh "${FOLDER_BACKUP}/${DB_BACKUP_FILE}" | cut -f1)
        echo "  ✓ Database backup selesai (${DB_SIZE})"
    else
        echo "  ✗ Database backup gagal!"
    fi
else
    echo "  ⚠ MySQL container (${MYSQL_CONTAINER}) tidak running, skip backup"
fi

echo ""

# =====================================================================
# 2. Backup Storage
# =====================================================================
echo "[2/2] Backup storage (file PDF/PNG/ZIP)..."
APP_CONTAINER="master-gambar-app"

if docker ps -a --format '{{.Names}}' | grep -q "${APP_CONTAINER}"; then
    echo "  Backup folder ke: ${FOLDER_BACKUP}"
    
    # Akan mengkopi seluruh isian storage/app ke dalam ${FOLDER_BACKUP}/app
    docker cp "${APP_CONTAINER}:/var/www/html/storage/app" "${FOLDER_BACKUP}"
    
    if [ $? -eq 0 ]; then
        BACKUP_SIZE=$(du -sh "${FOLDER_BACKUP}/app" | cut -f1)
        echo "  ✓ Storage backup selesai (${BACKUP_SIZE})"
    else
        echo "  ✗ Storage backup gagal!"
    fi
else
    echo "  ⚠ App container (${APP_CONTAINER}) tidak ditemukan, skip storage backup"
fi

echo ""

# =====================================================================
# Cleanup old backups (keep last 7 days)
# =====================================================================
RETENTION_DAYS="${RETENTION_DAYS:-7}"
echo "Cleanup backup lama (retention: ${RETENTION_DAYS} days)..."
find "${BACKUP_DIR}" -maxdepth 1 -name "*-master-autobackup" -type d -mtime +${RETENTION_DAYS} -exec rm -rf {} + 2>/dev/null || true
echo "  ✓ Cleanup selesai"

echo ""
echo "=========================================="
echo "  Backup Selesai!"
echo "  Lokasi: ${FOLDER_BACKUP}"
echo "=========================================="
echo " "
echo " "
