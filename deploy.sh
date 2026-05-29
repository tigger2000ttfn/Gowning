#!/usr/bin/env bash
#
# Force-deploy the GQS app on the E3 server.
# Pulls the latest main, overrides any local edits, rebuilds assets, restarts.
#
# Usage:  sudo bash deploy.sh
#
set -e
APP_DIR="/var/www/html/gowning"
cd "$APP_DIR"

echo "==> Trusting repo dir (avoids 'dubious ownership' pull failures)"
git config --global --add safe.directory "$APP_DIR" || true

echo "==> Fetching and FORCE-resetting to origin/main (discards local edits)"
git fetch origin main
git reset --hard origin/main

echo "==> Installing PHP dependencies"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

echo "==> Running migrations"
php artisan migrate --force

echo "==> Building front-end assets (theme + JS)"
npm install --silent
npm run build

echo "==> Publishing Filament assets"
php artisan filament:assets

echo "==> Fixing ownership + permissions"
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 storage bootstrap/cache

echo "==> Clearing caches"
php artisan optimize:clear

echo "==> Restarting Apache (full restart clears OPcache)"
systemctl restart apache2

echo ""
echo "==> Deploy complete. Verifying:"
git log --oneline -1
curl -k -s -o /dev/null -w "    landing: %{http_code}\n" https://matcastellas.com:8080/gowning/
curl -k -s -o /dev/null -w "    admin:   %{http_code}\n" https://matcastellas.com:8080/gowning/admin
echo "    (admin 200 or 302 = healthy)"
