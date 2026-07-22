#!/bin/bash
# =====================================================================
# Setup Development Environment - Master Gambar
# =====================================================================
# Script ini digunakan untuk menyiapkan .env jika belum ada.
# Jalankan SEKALI saja sebelum docker compose up -d --build
# =====================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"
ENV_EXAMPLE="$PROJECT_DIR/.env.example"

cd "$PROJECT_DIR"

if [ ! -f "$ENV_FILE" ]; then
    echo "-> .env not found, copying from .env.example..."
    cp "$ENV_EXAMPLE" "$ENV_FILE"
    echo "   APP_KEY will be auto-generated on first container start."
else
    echo "-> .env already exists, skipping."
fi

echo ""
echo "-> Setup complete. Run:"
echo "   docker compose up -d --build"