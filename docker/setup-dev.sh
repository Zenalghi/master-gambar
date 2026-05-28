#!/bin/bash
# =====================================================================
# Setup Development Environment - Master Gambar
# =====================================================================
# Script ini otomatis membuat docker/.env.dev kalau belum ada.
# Jalankan SEKALI saja sebelum docker compose up -d --build
# =====================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_DEV="$PROJECT_DIR/docker/.env.dev"
ENV_EXAMPLE="$PROJECT_DIR/docker/.env.dev.example"

cd "$PROJECT_DIR"

# 1. Create .env.dev from example if not exists
if [ ! -f "$ENV_DEV" ]; then
    echo "-> docker/.env.dev not found, creating from template..."
    cp "$ENV_EXAMPLE" "$ENV_DEV"

    # Generate random passwords
    DEV_PASS="dev_$(openssl rand -hex 8 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"
    ROOT_PASS="root_$(openssl rand -hex 8 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"

    # Replace placeholders
    sed -i "s|CHANGE_ME_DEV_PASSWORD|${DEV_PASS}|g" "$ENV_DEV"
    sed -i "s|CHANGE_ME_ROOT_PASSWORD|${ROOT_PASS}|g" "$ENV_DEV"

    echo "-> docker/.env.dev created with random passwords."
    echo "   You can edit it: nano docker/.env.dev"
else
    echo "-> docker/.env.dev already exists, skipping."
fi

# 2. Create .env from .env.docker.example if not exists (for app)
if [ ! -f "$PROJECT_DIR/.env" ]; then
    if [ -f "$PROJECT_DIR/.env.docker.example" ]; then
        echo "-> .env not found, copying from .env.docker.example..."
        cp "$PROJECT_DIR/.env.docker.example" "$PROJECT_DIR/.env"
        echo "   APP_KEY will be auto-generated on first container start."
    fi
fi

echo ""
echo "-> Setup complete. Run:"
echo "   docker compose -f docker-compose.dev.yml up -d --build"
