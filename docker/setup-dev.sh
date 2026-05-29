#!/bin/bash
# =====================================================================
# Setup Development Environment - Master Gambar
# =====================================================================
# Script ini otomatis membuat docker/.env.dev.secrets kalau belum ada.
# Jalankan SEKALI saja sebelum docker compose up -d --build
# =====================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_SECRETS="$PROJECT_DIR/docker/.env.dev.secrets"
ENV_SECRETS_EXAMPLE="$PROJECT_DIR/docker/.env.dev.secrets.example"

cd "$PROJECT_DIR"

# 1. Create .env.dev.secrets from example if not exists
if [ ! -f "$ENV_SECRETS" ]; then
    echo "-> docker/.env.dev.secrets not found, creating from template..."
    cp "$ENV_SECRETS_EXAMPLE" "$ENV_SECRETS"

    # Generate random passwords
    DEV_PASS="dev_$(openssl rand -hex 8 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"
    ROOT_PASS="root_$(openssl rand -hex 8 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"

    # Replace placeholders
    sed -i "s|GANTI_PASSWORD_ROOT_DI_SINI|${ROOT_PASS}|g" "$ENV_SECRETS"
    sed -i "s|GANTI_PASSWORD_DEV_DI_SINI|${DEV_PASS}|g" "$ENV_SECRETS"

    echo "-> docker/.env.dev.secrets created with random passwords."
else
    echo "-> docker/.env.dev.secrets already exists, skipping."
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