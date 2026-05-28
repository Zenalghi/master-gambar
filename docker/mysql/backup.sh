#!/bin/bash
# =====================================================================
# MySQL Backup Script
# =====================================================================

set -e

# =====================================================================
# Load secrets from centralized file
# =====================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SECRETS_FILE="${SCRIPT_DIR}/../.env.secrets"

if [ -f "${SECRETS_FILE}" ]; then
    source "${SECRETS_FILE}"
fi

# =====================================================================
# KONFIGURASI - SESUAIKAN DENGAN SERVER ANDA
# =====================================================================

# Folder backup di dalam container
BACKUP_DIR="/backup"

# MySQL connection settings
MYSQL_HOST="localhost"
MYSQL_PORT="3306"
MYSQL_USER="root"

# Password loaded from .env.secrets (fallback to placeholder if not set)
MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD:-GANTI_PASSWORD_ROOT_DI_SINI}"

# Database settings
DATABASE="${MYSQL_DATABASE:-db_master}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

# =====================================================================

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${DATABASE}_${DATE}.sql.gz"

# Create backup directory if not exists
mkdir -p "${BACKUP_DIR}"

echo "=== MySQL Backup Started: ${DATE} ==="

# Run backup
mysqldump \
    --host="${MYSQL_HOST}" \
    --port="${MYSQL_PORT}" \
    --user="${MYSQL_USER}" \
    --password="${MYSQL_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    --databases "${DATABASE}" | gzip > "${BACKUP_FILE}"

# Check if backup was successful
if [ $? -eq 0 ] && [ -f "${BACKUP_FILE}" ]; then
    echo "Backup successful: ${BACKUP_FILE}"
    echo "Backup size: $(du -h "${BACKUP_FILE}" | cut -f1)"
else
    echo "ERROR: Backup failed!"
    exit 1
fi

# Cleanup old backups (keep last 7 days)
echo "Cleaning up backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -name "${DATABASE}_*.sql.gz" -mtime +${RETENTION_DAYS} -delete

# List remaining backups
echo "Current backups:"
ls -lh "${BACKUP_DIR}"

echo "=== MySQL Backup Completed ==="
