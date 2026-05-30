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
# If composer.json declares packages not in the lock file, sync the lock first.
if ! COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader 2>/dev/null; then
    echo "==> Lock file out of date or install failed; running composer update for declared packages"
    COMPOSER_ALLOW_SUPERUSER=1 composer update --no-dev --optimize-autoloader
fi

echo "==> Running migrations"
php artisan migrate --force

echo "==> Building front-end assets (theme + JS)"
# clear stale build output so a failed build can't silently serve old CSS
rm -rf public/build
npm install --no-audit --no-fund
npm run build || { echo "!!! npm run build FAILED - see errors above"; exit 1; }

echo "==> Publishing Filament assets"
php artisan filament:assets

# verify the theme actually compiled (sanity check for a known token)
if grep -riq "1c1c21" public/build/assets/*.css 2>/dev/null; then
    echo "==> Theme CSS compiled OK (token found)"
else
    echo "!!! WARNING: theme token NOT found in compiled CSS - build may be stale"
fi

echo "==> Fixing ownership + permissions"
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 storage bootstrap/cache

echo "==> Clearing caches"
php artisan optimize:clear

echo "==> Restarting Apache (full restart clears OPcache)"
systemctl restart apache2

echo "==> Ensuring the Laravel scheduler cron is installed (drives backups + automation)"
CRON_LINE="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
if ! crontab -l 2>/dev/null | grep -Fq "artisan schedule:run"; then
    ( crontab -l 2>/dev/null; echo "$CRON_LINE" ) | crontab -
    echo "    cron installed"
else
    echo "    cron already present"
fi

echo "==> Checking pg_dump availability (needed by spatie/laravel-backup)"
if command -v pg_dump >/dev/null 2>&1; then
    echo "    pg_dump found: $(pg_dump --version 2>/dev/null | head -1)"
else
    echo "    !!! pg_dump NOT found - DB backups will fail until you install the Postgres client:"
    echo "        sudo apt-get install -y postgresql-client"
fi

echo ""
echo "==> Deploy complete. Verifying:"
git log --oneline -1
curl -k -s -o /dev/null -w "    landing: %{http_code}\n" https://matcastellas.com:8080/gowning/
curl -k -s -o /dev/null -w "    admin:   %{http_code}\n" https://matcastellas.com:8080/gowning/admin
echo "    (admin 200 or 302 = healthy)"
