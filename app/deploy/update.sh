#!/usr/bin/env bash
# Update an existing installation after `git pull`:
#   git pull && bash app/deploy/update.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan brand:derivatives
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan queue:restart

echo "Updated. Run: php artisan app:readiness"
