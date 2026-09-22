#!/bin/bash
# MGE-PMS Production Deploy Script
# Run via cPanel Terminal after git pull:
#   bash deploy.sh

set -e

# Resolve a real CLI binary. `which php` on this host returns the CGI one, which drops
# artisan's arguments: every command below would print the command list and exit 0, and the
# deploy would report success without migrating. bin/php-cli.sh checks PHP_SAPI and aborts
# rather than hand back a binary that fails silently.
PHP=$(bash "$(dirname "$0")/bin/php-cli.sh") || exit 1
echo "Using PHP: $PHP ($($PHP -r 'echo PHP_VERSION." ".PHP_SAPI;'))"

echo "→ Clearing cached config..."
$PHP artisan config:clear
$PHP artisan cache:clear
$PHP artisan route:clear
$PHP artisan view:clear

echo "→ Running migrations..."
$PHP artisan migrate --force

# migrate can exit 0 without having run anything if its arguments were dropped. Ask the
# database what it actually has, and stop before optimize() caches a broken app.
if $PHP artisan migrate:status 2>/dev/null | grep -q "Pending"; then
    echo "ERROR: migrations are still pending after 'migrate --force'." >&2
    $PHP artisan migrate:status | grep "Pending" >&2
    exit 1
fi

echo "→ Linking storage..."
$PHP artisan storage:link --force 2>/dev/null || true

echo "→ Optimizing..."
$PHP artisan optimize

echo "✓ Deploy complete. App URL: https://app.mge-eng.com"
