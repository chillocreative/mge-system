#!/bin/bash
# MGE-PMS Production Deploy Script
# Run via cPanel Terminal after git pull:
#   bash deploy.sh

set -e

PHP=$(which php8.3 2>/dev/null || which php8.2 2>/dev/null || which php 2>/dev/null)
echo "Using PHP: $PHP ($($PHP -r 'echo PHP_VERSION;'))"

echo "→ Clearing cached config..."
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan route:clear
$PHP artisan view:clear

echo "→ Running migrations..."
$PHP artisan migrate --force

echo "→ Linking storage..."
$PHP artisan storage:link --force 2>/dev/null || true

echo "→ Optimizing..."
$PHP artisan optimize

echo "✓ Deploy complete. App URL: https://app.mge-eng.com"
