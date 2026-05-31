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

echo "==> Clearing view + optimize caches EARLY (so blade/view changes apply even if a later step fails)"
php artisan view:clear || true
php artisan optimize:clear || true

echo "==> Installing PHP dependencies"
# If composer.json declares packages not in the lock file, sync the lock first.
if ! COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader 2>/dev/null; then
    echo "==> Lock file out of date or install failed; running composer update for declared packages"
    COMPOSER_ALLOW_SUPERUSER=1 composer update --no-dev --optimize-autoloader
fi

echo "==> Running migrations"
php artisan migrate --force

echo "==> Ensuring storage symlink (for media library uploads / public files)"
php artisan storage:link 2>/dev/null || true

echo "==> Building front-end assets (theme + JS)"
# Back up the current working build so a failed build never leaves the site with a
# missing Vite manifest (which 500s every Filament page). Only swap in the new build
# once it succeeds AND produced a manifest.
if [ -d public/build ]; then
    rm -rf public/build.bak
    cp -r public/build public/build.bak
fi
rm -rf public/build
npm install --no-audit --no-fund
if npm run build && [ -f public/build/manifest.json ]; then
    echo "==> Build succeeded (manifest present)"
    rm -rf public/build.bak
else
    echo "!!! npm run build FAILED or produced no manifest - restoring previous build"
    rm -rf public/build
    if [ -d public/build.bak ]; then
        mv public/build.bak public/build
        echo "!!! Restored previous build; site stays up but assets are stale. Fix the build error above."
    else
        echo "!!! No previous build to restore - Filament pages will 500 until the build succeeds."
    fi
    # don't hard-exit: still fix perms/caches below so the restored build is served correctly
fi

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
php artisan view:clear || true

echo "==> Restarting PHP-FPM (if present) + Apache to flush OPcache"
# Apache restart flushes OPcache for mod_php; if PHP runs under FPM, Apache restart
# alone does NOT flush FPM's OPcache, so restart any installed php*-fpm too.
for svc in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
    systemctl restart "$svc" 2>/dev/null && echo "    restarted $svc" || true
done
systemctl restart apache2
echo "==> Deploy complete. Deployed commit:"
git log --oneline -1

echo "==> Ensuring the Laravel scheduler cron is installed (drives the automation chain)"
CRON_LINE="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
if ! crontab -l 2>/dev/null | grep -Fq "artisan schedule:run"; then
    ( crontab -l 2>/dev/null; echo "$CRON_LINE" ) | crontab -
    echo "    cron installed"
else
    echo "    cron already present"
fi

echo ""
echo "==> Deploy complete. Verifying:"
git log --oneline -1
curl -k -s -o /dev/null -w "    landing: %{http_code}\n" https://matcastellas.com:8080/gowning/
curl -k -s -o /dev/null -w "    admin:   %{http_code}\n" https://matcastellas.com:8080/gowning/admin
echo "    (admin 200 or 302 = healthy)"
