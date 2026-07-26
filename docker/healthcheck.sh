#!/bin/bash
# =====================================================================
# Healthcheck Script untuk Master Gambar
# Jalankan di server untuk memverifikasi semua container sehat
# =====================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "  Master Gambar - Healthcheck Report"
echo "  $(date)"
echo "=========================================="
echo ""

# Check Docker service
echo -n "Docker Service: "
if systemctl is-active --quiet docker 2>/dev/null; then
    echo -e "${GREEN}RUNNING${NC}"
else
    echo -e "${RED}NOT RUNNING${NC}"
    echo "  -> Jalankan: sudo systemctl start docker"
fi

# Check Docker enabled at boot
echo -n "Docker Auto-Start: "
if systemctl is-enabled --quiet docker 2>/dev/null; then
    echo -e "${GREEN}ENABLED${NC}"
else
    echo -e "${YELLOW}DISABLED${NC}"
    echo "  -> Jalankan: sudo systemctl enable docker"
fi

echo ""
echo "------------------------------------------"
echo "  Container Status"
echo "------------------------------------------"

# Check each container
CONTAINERS=("master-gambar-app" "master-gambar-nginx" "infra-mysql")

for container in "${CONTAINERS[@]}"; do
    echo -n "$container: "

    if docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
        STATUS=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null)
        HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null || echo "N/A")
        RESTART=$(docker inspect --format='{{.HostConfig.RestartPolicy.Name}}' "$container" 2>/dev/null)

        if [ "$STATUS" = "running" ]; then
            echo -e "${GREEN}UP${NC} (health: $HEALTH, restart: $RESTART)"
        else
            echo -e "${RED}$STATUS${NC}"
        fi
    else
        echo -e "${RED}NOT FOUND${NC}"
    fi
done

echo ""
echo "------------------------------------------"
echo "  Resource Usage"
echo "------------------------------------------"
docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}\t{{.BlockIO}}" 2>/dev/null || echo "Tidak bisa membaca stats"

echo ""
echo "------------------------------------------"
echo "  Volume Status"
echo "------------------------------------------"
docker volume ls --filter name=master-gambar --format "table {{.Name}}\t{{.Driver}}\t{{.Scope}}" 2>/dev/null || echo "Tidak bisa membaca volumes"

echo ""
echo "------------------------------------------"
echo "  Network Status"
echo "------------------------------------------"
docker network inspect rekayasa-network --format '{{.Name}}: {{.Driver}} ({{.IPAM.Config}})' 2>/dev/null || echo "Network tidak ditemukan"

echo ""
echo "=========================================="
echo "  Healthcheck Selesai"
echo "=========================================="
