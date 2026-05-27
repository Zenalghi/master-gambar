#!/bin/bash
# =====================================================================
# Setup Auto-Start untuk Master Gambar
# Jalankan script ini SEKALI di server untuk mengkonfigurasi:
# 1. Docker auto-start saat boot
# 2. Backup cron job
# 3. Log rotation
# =====================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# 1. Enable Docker auto-start
echo -n "[1/4] Mengaktifkan Docker auto-start... "
if systemctl enable docker >/dev/null 2>&1; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}FAILED${NC}"
fi

# 2. Start Docker if not running
echo -n "[2/4] Memulai Docker service... "
if systemctl start docker >/dev/null 2>&1; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}FAILED${NC}"
fi

# 3. Setup backup cron job
echo -n "[3/4] Mengatur backup cron job... "
CRON_JOB="0 2 * * * cd $(pwd) && docker exec master-gambar-mysql sh /backup.sh >> /var/log/master-gambar-backup.log 2>&1"
(crontab -l 2>/dev/null | grep -v "master-gambar-backup"; echo "$CRON_JOB") | crontab -
echo -e "${GREEN}OK${NC}"
echo "      Backup akan berjalan setiap jam 2 pagi"

# 4. Setup log rotation for Docker
echo -n "[4/4] Mengatur log rotation... "
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
echo -e "${GREEN}OK${NC}"

echo ""
echo "=========================================="
echo "  Setup Selesai!"
echo "=========================================="
echo ""
echo "Verifikasi:"
echo "  1. Docker auto-start: systemctl is-enabled docker"
echo "  2. Docker status:     systemctl status docker"
echo "  3. Cron job:          crontab -l"
echo "  4. Log rotation:      cat /etc/logrotate.d/master-gambar"
echo ""
echo "Untuk menjalankan aplikasi:"
echo "  docker compose up -d --build"
echo ""
echo "Untuk memeriksa status:"
echo "  docker compose ps"
echo "  bash docker/healthcheck.sh"
