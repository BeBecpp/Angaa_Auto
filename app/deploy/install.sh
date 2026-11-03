#!/usr/bin/env bash
# First-time install on a Linux server (run from the repository root or app/):
#   bash app/deploy/install.sh
# Requires: PHP 8.4 (+ pdo_mysql or pdo_sqlite, mbstring, intl, gd, exif, fileinfo, zip, curl), Composer 2, Node 20+ with npm.
# It never seeds demo data. It stops before anything that needs a human decision (owner password, real business details).
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

need() { command -v "$1" >/dev/null 2>&1 || { echo "Missing: $1"; exit 1; }; }
need php; need composer; need npm

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "PHP 8.3+ required (8.4 recommended)"; exit 1; }
for ext in mbstring intl gd exif fileinfo zip curl openssl; do
  php -m | grep -qi "^$ext$" || { echo "Missing PHP extension: $ext"; exit 1; }
done

if [ ! -f .env ]; then
  cp .env.production.example .env
  echo ">> Created .env from .env.production.example — edit APP_URL and DB_* now, then re-run this script."
  exit 0
fi

grep -q '^APP_ENV=production' .env || echo "!! Warning: APP_ENV is not production in .env"

composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
  echo ">> APP_KEY generated. Back up .env securely — losing APP_KEY breaks sessions and encrypted data."
fi

if grep -q '^DB_CONNECTION=sqlite' .env; then
  [ -f database/database.sqlite ] || touch database/database.sqlite
fi

npm ci
npm run build

php artisan migrate --force
php artisan brand:derivatives
php artisan optimize
php artisan filament:optimize

mkdir -p storage/app/private/media
chmod -R ug+rwX storage bootstrap/cache

cat <<'NEXT'

Install finished. Next (human steps):
  1. php artisan app:owner-create            # owner login, password is prompted
  2. Web server root -> app/public           (deploy/nginx.conf.example)
  3. Queue worker service                    (deploy/angaa-queue.service)
  4. Cron: * * * * * php artisan schedule:run (deploy/angaa-crontab.example)
  5. /admin -> Тохиргоо: real phone, address, hours, services/prices, bays, staff
  6. php artisan app:readiness               # must be all PASS before public launch
NEXT
