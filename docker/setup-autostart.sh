#!/bin/bash
# =====================================================================
# Setup Auto-Start untuk Master Gambar
# Jalankan script ini SEKALI di server untuk mengkonfigurasi:
# 1. Docker auto-start saat boot
# 2. Backup database cron job (jam 12 siang)
# 3. Backup storage cron job (jam 12:30 siang)
# 4. Log rotation
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

# =====================================================================
# 1. Enable Docker auto-start
# =====================================================================
echo -e "${BLUE}[1/5] Mengaktifkan Docker auto-start...${NC}"
if systemctl enable docker >/dev/null 2>&1; then
    echo -e "${GREEN}  ✓ Docker auto-start enabled${NC}"
else
    echo -e "${RED}  ✗ FAILED${NC}"
fi
echo ""

# =====================================================================
# 2. Start Docker if not running
# =====================================================================
echo -e "${BLUE}[2/5] Memulai Docker service...${NC}"
if systemctl start docker >/dev/null 2>&1; then
    echo -e "${GREEN}  ✓ Docker running${NC}"
else
    echo -e "${RED}  ✗ FAILED${NC}"
fi
echo ""

# =====================================================================
# 3. Setup backup database cron job (jam 12:00 siang)
# =====================================================================
echo -e "${BLUE}[3/5] Mengatur backup database (jam 12:00 siang)...${NC}"
CRON_DB="0 12 * * * cd ${PROJECT_DIR} && docker exec master-gambar-mysql sh /backup.sh >> /var/log/master-gambar-db-backup.log 2>&1"
(sudo -u "${CURRENT_USER}" crontab -l 2>/dev/null | grep -v "master-gambar-db-backup"; echo "$CRON_DB") | sudo -u "${CURRENT_USER}" crontab -
echo -e "${GREEN}  ✓ Backup database: setiap jam 12:00 siang${NC}"
echo ""

# =====================================================================
# 4. Setup backup storage cron job (jam 12:30 siang)
# =====================================================================
echo -e "${BLUE}[4/5] Mengatur backup storage (jam 12:30 siang)...${NC}"
CRON_STORAGE="30 12 * * * cd ${PROJECT_DIR} && bash docker/backup-storage.sh >> /var/log/master-gambar-storage-backup.log 2>&1"
(sudo -u "${CURRENT_USER}" crontab -l 2>/dev/null | grep -v "master-gambar-storage-backup"; echo "$CRON_STORAGE") | sudo -u "${CURRENT_USER}" crontab -
echo -e "${GREEN}  ✓ Backup storage: setiap jam 12:30 siang${NC}"
echo ""

# =====================================================================
# 5. Setup log rotation for Docker
# =====================================================================
echo -e "${BLUE}[5/5] Mengatur log rotation...${NC}"
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
# Summary
# =====================================================================
echo "=========================================="
echo -e "${GREEN}  Setup Selesai!${NC}"
echo "=========================================="
echo ""
echo "Cron jobs yang sudah diatur:"
echo "  • Backup database:  setiap jam 12:00 siang"
echo "  • Backup storage:   setiap jam 12:30 siang"
echo ""
echo "Verifikasi:"
echo "  1. Docker auto-start: systemctl is-enabled docker"
echo "  2. Docker status:     systemctl status docker"
echo "  3. Cron jobs:         crontab -l"
echo "  4. Log rotation:      cat /etc/logrotate.d/master-gambar"
echo ""
echo "Untuk menjalankan aplikasi:"
echo "  docker compose up -d --build"
echo ""
echo "Untuk memeriksa status:"
echo "  docker compose ps"
echo "  bash docker/healthcheck.sh"
echo ""
echo "Untuk backup manual:"
echo "  bash docker/backup-storage.sh"
