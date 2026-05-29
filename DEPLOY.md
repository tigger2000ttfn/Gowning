# Deploy / Update - MATC Gowning Qualification System

The application lives in this repo. The E3 server **pulls the code and sets up the
environment** - nothing is built on the server except PHP dependencies (`composer install`)
and the database schema (`php artisan migrate`).

- Served at: `https://matcastellas.com:8080/gowning/`  (Apache subfolder, like /MATCit and /service)
- Server path: `/var/www/html/gowning/`
- Stack: PHP 8.3 + Laravel 13 + Filament 5, PostgreSQL on AWS RDS

---

## One-time database setup (RDS)

Already done once; skip if `gowning_e3` exists. Run from anywhere with psql:

```bash
PGPASSWORD='<postgres-admin-pw>' psql \
  -h mapawusedb100.c1hy7hul4jqv.us-east-1.rds.amazonaws.com \
  -U postgres -d postgres <<'SQL'
CREATE DATABASE gowning_e3;
CREATE ROLE gowning_user WITH LOGIN PASSWORD 'Gowning_E3_2026!';
GRANT ALL PRIVILEGES ON DATABASE gowning_e3 TO gowning_user;
SQL

PGPASSWORD='<postgres-admin-pw>' psql \
  -h mapawusedb100.c1hy7hul4jqv.us-east-1.rds.amazonaws.com \
  -U postgres -d gowning_e3 <<'SQL'
GRANT CREATE ON SCHEMA public TO gowning_user;
GRANT ALL ON SCHEMA public TO gowning_user;
ALTER SCHEMA public OWNER TO gowning_user;
SQL
```

---

## First-time install

```bash
# 1. PostgreSQL PHP driver (skip if already present)
sudo apt update && sudo apt install -y php8.3-pgsql
php -m | grep -i pgsql        # expect: pgsql, pdo_pgsql

# 2. Clone the app into place (sudo: www-data cannot write /var/www/html)
cd /var/www/html
sudo rm -rf gowning
sudo git clone https://github.com/tigger2000ttfn/Gowning.git gowning
cd gowning

# 3. Install PHP dependencies (regenerates vendor/, pulls Laravel + Filament)
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# 4. Environment file
sudo cp .env.example .env
#    Set in .env:
#      APP_KEY            (filled by key:generate below)
#      DB_PASSWORD=Gowning_E3_2026!
#      ADMIN_EMAIL / ADMIN_PASSWORD   (used once by db:seed)
#      APP_URL=https://matcastellas.com:8080/gowning
sudo nano .env

# 5. App key, schema, seed admin
sudo php artisan key:generate
sudo php artisan migrate --force
sudo php artisan db:seed --force

# 6. Filament assets + storage link
sudo php artisan filament:assets
sudo php artisan storage:link

# 7. Permissions: hand everything to the web user
sudo chown -R www-data:www-data /var/www/html/gowning
sudo chmod -R 775 storage bootstrap/cache

# 8. Clear caches and restart Apache (restart, not reload - see note below)
sudo php artisan optimize:clear
sudo systemctl restart apache2
```

### Apache alias (one-time, in the :8080 vhost)

Add inside the existing `<VirtualHost *:8080>` in
`/etc/apache2/sites-available/000-default.conf` (where /MATCit and /service live):

```apache
    # MATC Gowning Qualification System
    Alias /gowning /var/www/html/gowning/public
    <Directory /var/www/html/gowning/public>
        DirectoryIndex index.php
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
```

```bash
sudo apache2ctl configtest && sudo systemctl restart apache2
```

`AllowOverride All` is required so the app's `public/.htaccess` (which sets
`RewriteBase /gowning/`) is honored. Without it the app will redirect-loop.

---

## Verify

```bash
# 200 (landing) and 200/302 (admin login) = healthy
curl -k -s -o /dev/null -w "landing %{http_code}\n" https://matcastellas.com:8080/gowning/
curl -k -s -o /dev/null -w "admin   %{http_code}\n" https://matcastellas.com:8080/gowning/admin
```

- Landing page: `https://matcastellas.com:8080/gowning/`
- Admin login:  `https://matcastellas.com:8080/gowning/admin`  (log in with ADMIN_EMAIL / ADMIN_PASSWORD)

---

## Updating later (pull new commits)

```bash
cd /var/www/html/gowning
sudo git pull
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo chown -R www-data:www-data /var/www/html/gowning
sudo php artisan optimize:clear
sudo systemctl restart apache2     # restart, not reload (see note)
```

---

## Notes learned in commissioning (read if something breaks)

1. **Restart, not reload.** mod_php holds compiled classes in OPcache. After any code
   change, `systemctl reload apache2` is NOT enough - the old class stays cached and you
   get stale behavior. Always `systemctl restart apache2` (or reset OPcache).

2. **Subfolder rewrite.** `public/.htaccess` sets `RewriteBase /gowning/`. This is required
   for a subdirectory install; without it the trailing-slash rule loops and Apache returns
   "Request exceeded the limit of 10 internal redirects" (HTTP 500).

3. **Filament panel config.** `app/Providers/Filament/AdminPanelProvider.php` must include
   both `->default()` (so Filament has a default panel) and `->login()` (so the `login`
   route exists). Both are committed in this repo; do not remove them.

4. **Debugging server errors.** Laravel errors -> `storage/logs/laravel.log`. Errors that
   never reach Laravel (rewrite loops, PHP fatals) -> `/var/log/apache2/error.log`. Check
   both. Temporarily set `APP_DEBUG=true` in `.env` + `php artisan config:clear` to see the
   full trace in the browser, then set it back to `false` (this system targets validation).
