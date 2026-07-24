#!/bin/bash
# =====================================================================
# Setup Auto-Start & Cron - Master Gambar
# =====================================================================
# Script ini akan:
# 1. Menambahkan cron job untuk autobackup master-gambar
# 
# Jalankan SEKALI saja: sudo bash setup-app-autostart.sh
# =====================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== Setup Auto-Start & Cron (Master Gambar) ===${NC}"
echo ""

# Pastikan folder logs tersedia
mkdir -p /srv/workspace/logs

# =====================================================================
# 1. Setup Cron Backup Otomatis
# =====================================================================
echo -e "${BLUE}[1/1] Mengatur Cron Backup harian (12:15 Siang)...${NC}"
if ! crontab -l 2>/dev/null | grep -q "master-gambar/docker/autobackup.sh"; then
    (crontab -l 2>/dev/null; echo "15 12 * * * cd /srv/workspace/apps/master-gambar && bash docker/autobackup.sh >> /srv/workspace/logs/2-master-gambar.log 2>&1") | crontab -
    echo -e "${GREEN}  ✓ Cron backup master-gambar berhasil ditambahkan${NC}"
    echo -e "${GREEN}  ✓ Log akan disimpan di: /srv/workspace/logs/2-master-gambar.log${NC}"
else
    echo -e "${YELLOW}  - Cron backup master-gambar sudah ada (skip)${NC}"
fi
echo ""

echo -e "${BLUE}=== Setup Selesai ===${NC}"
echo "Catatan:"
echo "Container aplikasi secara otomatis berjalan bersama docker service (rekayasa-infra)."
echo "Cron autobackup berjalan setiap jam 12:15 siang."
