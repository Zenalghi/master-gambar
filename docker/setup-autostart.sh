#!/bin/bash
# =====================================================================
# Setup Auto-Start untuk Master Gambar
# Jalankan script ini SEKALI di server untuk mengkonfigurasi:
# 1. Docker auto-start saat boot
# 2. Auto-backup database & storage ke ~/laravel/backups (jam 12 siang)
# 3. Log rotation
# =====================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "  Master Gambar - Setup Auto-Start"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Jalankan script ini sebagai root (sudo)${NC}"
    echo "  -> sudo bash docker/setup-autostart.sh"
    exit 1
fi

# Get current user (non-root) for crontab
CURRENT_USER="${SUDO_USER:-$USER}"
PROJECT_DIR="$(pwd)"

# Get home directory properly (works with sudo)
HOME_DIR=$(eval echo "~${CURRENT_USER}")
BACKUP_DIR="${HOME_DIR}/laravel/backups"

# =====================================================================
# 1. Enable Docker auto-start
# =====================================================================
echo -e "${BLUE}[1/4] Mengaktifkan Docker auto-start...${NC}"
if systemctl enable docker >/dev/null 2>&1; then
    echo -e "${GREEN}  ✓ Docker auto-start enabled${NC}"
else
    echo -e "${RED}  ✗ FAILED${NC}"
fi
echo ""

# =====================================================================
# 2. Start Docker if not running
# =====================================================================
echo -e "${BLUE}[2/4] Memulai Docker service...${NC}"
if systemctl start docker >/dev/null 2>&1; then
    echo -e "${GREEN}  ✓ Docker running${NC}"
else
    echo -e "${RED}  ✗ FAILED${NC}"
fi
echo ""

# =====================================================================
# 3. Create autobackup script
# =====================================================================
echo -e "${BLUE}[3/4] Membuat script autobackup...${NC}"

# Create backup directory
mkdir -p "${BACKUP_DIR}"

# Create autobackup script with proper path handling
cat > "${PROJECT_DIR}/docker/autobackup.sh" << SCRIPT_EOF
#!/bin/bash
# =====================================================================
# Auto Backup Script untuk Master Gambar
# Backup database dan storage ke ~/laravel/backups
# =====================================================================

set -e

# Load secrets from centralized file
SCRIPT_DIR="\$(cd "\$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="\$(dirname "\$SCRIPT_DIR")"

if [ -f "\${SCRIPT_DIR}/.env.secrets" ]; then
    source "\${SCRIPT_DIR}/.env.secrets"
fi

# Konfigurasi backup - gunakan path absolut dari home directory
# Resolve ~ ke path absolut untuk menghindari masalah di container
HOME_DIR=\$(eval echo "~\\\${USER}")
BACKUP_DIR="\${BACKUP_DIR:-\${HOME_DIR}/laravel/backups}"
FOLDER_NAME="auto-backup-\$(date +%F-%H%M)"
FOLDER_BACKUP="\${BACKUP_DIR}/\${FOLDER_NAME}"

echo "=========================================="
echo "  Master Gambar - Auto Backup"
echo "  \$(date)"
echo "=========================================="
echo ""

mkdir -p "\${FOLDER_BACKUP}"

# =====================================================================
# Backup Database
# =====================================================================
echo "[1/2] Backup database MySQL..."

if docker ps --format '{{.Names}}' | grep -q "master-gambar-mysql"; then
    DB_BACKUP_FILE="mysql-backup-\$(date +%F-%H%M%S).sql"
    
    echo "  Backup database ke: \${FOLDER_BACKUP}/\${DB_BACKUP_FILE}"
    
    docker exec master-gambar-mysql \\
        mysqldump -u root -p"\${MYSQL_ROOT_PASSWORD}" --all-databases \\
        > "\${FOLDER_BACKUP}/\${DB_BACKUP_FILE}"
    
    if [ \$? -eq 0 ]; then
        DB_SIZE=\$(du -sh "\${FOLDER_BACKUP}/\${DB_BACKUP_FILE}" | cut -f1)
        echo "  ✓ Database backup selesai (\${DB_SIZE})"
    else
        echo "  ✗ Database backup gagal!"
    fi
else
    echo "  ⚠ MySQL container tidak running, skip backup"
fi

echo ""

# =====================================================================
# Backup Storage
# =====================================================================
echo "[2/2] Backup storage (file PDF/PNG/ZIP)..."

if docker ps --format '{{.Names}}' | grep -q "master-gambar-app"; then
    echo "  Backup folder: \${FOLDER_BACKUP}"
    
    docker cp master-gambar-app:/var/www/html/storage/app "\${FOLDER_BACKUP}"
    
    if [ \$? -eq 0 ]; then
        BACKUP_SIZE=\$(du -sh "\${FOLDER_BACKUP}" | cut -f1)
        echo "  ✓ Storage backup selesai (\${BACKUP_SIZE})"
    else
        echo "  ✗ Storage backup gagal!"
    fi
else
    echo "  ⚠ App container tidak running, skip storage backup"
fi

echo ""

# =====================================================================
# Cleanup old backups (keep last 7 days)
# =====================================================================
echo "Cleanup backup lama (retention: \${RETENTION_DAYS:-7} days)..."
find "\${BACKUP_DIR}" -name "auto-backup-*" -type d -mtime +\${RETENTION_DAYS:-7} -exec rm -rf {} + 2>/dev/null || true
echo "  ✓ Cleanup selesai"

echo ""
echo "=========================================="
echo "  Backup Selesai!"
echo "  Lokasi: \${FOLDER_BACKUP}"
echo "=========================================="
SCRIPT_EOF

chmod +x "${PROJECT_DIR}/docker/autobackup.sh"
echo -e "${GREEN}  ✓ Script autobackup dibuat: docker/autobackup.sh${NC}"
echo ""

# =====================================================================
# 4. Setup log rotation for Docker
# =====================================================================
echo -e "${BLUE}[4/4] Mengatur log rotation...${NC}"
cat > /etc/logrotate.d/master-gambar << 'EOF'
/var/lib/docker/containers/*/*.log {
    rotate 5
    daily
    compress
    size=10M
    missingok
    delaycompress
    copytruncate
}
EOF
echo -e "${GREEN}  ✓ Log rotation configured${NC}"
echo ""

# =====================================================================
# Setup cron job for autobackup (jam 12:00 siang)
# =====================================================================
echo -e "${BLUE}[*] Mengatur cron job autobackup...${NC}"
CRON_AUTOBACKUP="0 12 * * * cd ${PROJECT_DIR} && bash docker/autobackup.sh >> /var/log/master-gambar-autobackup.log 2>&1"
(sudo -u "${CURRENT_USER}" crontab -l 2>/dev/null | grep -v "master-gambar-autobackup"; echo "$CRON_AUTOBACKUP") | sudo -u "${CURRENT_USER}" crontab -
echo -e "${GREEN}  ✓ Auto-backup: setiap jam 12:00 siang${NC}"
echo -e "${GREEN}  • Backup database: MySQL dump${NC}"
echo -e "${GREEN}  • Backup storage:  File PDF/PNG/ZIP${NC}"
echo -e "${GREEN}  • Lokasi:         ${BACKUP_DIR}${NC}"
echo -e "${GREEN}  • Retention:      7 hari (otomatis cleanup)${NC}"
echo ""

# =====================================================================
# Summary
# =====================================================================
echo "=========================================="
echo -e "${GREEN}  Setup Selesai!${NC}"
echo "=========================================="
echo ""
echo "Cron jobs yang sudah diatur:"
echo "  • Auto-backup: setiap jam 12:00 siang"
echo "    - Database backup (MySQL dump)"
echo "    - Storage backup (file PDF/PNG/ZIP)"
echo "    - Lokasi: ${BACKUP_DIR}"
echo "    - Retention: 7 hari (otomatis cleanup)"
echo ""
echo "Verifikasi:"
echo "  1. Docker auto-start: systemctl is-enabled docker"
echo "  2. Docker status:     systemctl status docker"
echo "  3. Cron jobs:         crontab -l"
echo "  4. Log rotation:      cat /etc/logrotate.d/master-gambar"
echo "  5. Autobackup script: cat docker/autobackup.sh"
echo ""
echo "Untuk menjalankan aplikasi:"
echo "  docker compose up -d --build"
echo ""
echo "Untuk memeriksa status:"
echo "  docker compose ps"
echo "  bash docker/healthcheck.sh"
echo ""
echo "Untuk backup manual:"
echo "  bash docker/autobackup.sh"
echo ""
echo -e "${YELLOW}Catatan Penting:${NC}"
echo "  Pastikan file docker/.env.secrets sudah dikonfigurasi:"
echo "    cp docker/.env.secrets.example docker/.env.secrets"
echo "    nano docker/.env.secrets  # Edit password MySQL"
echo ""
echo "  Backup otomatis disimpan di:"
echo "    ${BACKUP_DIR}/auto-backup-YYYY-MM-DD-HHMM/"
echo "    ├── mysql-backup-YYYY-MM-DD-HHMMSS.sql"
echo "    └── app/ (storage files)"
