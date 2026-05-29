# Deploy / Update on the E3 Server

The application lives in this repo. The server only **pulls and sets up the environment** -
nothing is built on the server except dependencies (`composer install`) and the database.

## First-time install

Run on the E3 server. `www-data` cannot write to `/var/www/html`, so we clone with `sudo`
and hand ownership to `www-data` at the end.

```bash
# 1. Clone the app into place
cd /var/www/html
sudo rm -rf gowning
sudo git clone https://github.com/tigger2000ttfn/Gowning.git gowning
cd gowning

# 2. Install PHP dependencies (regenerates vendor/)
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# 3. Environment file
sudo cp .env.example .env
#    Edit .env and set:
#      DB_PASSWORD=Gowning_E3_2026!
#      ADMIN_PASSWORD=<choose a strong password>
#      APP_URL scheme (https/http) to match how /MATCit and /service work on 8080
sudo nano .env

# 4. App key + schema + admin user
sudo php artisan key:generate
sudo php artisan migrate --force
sudo php artisan db:seed --force        # creates the System Admin from .env

# 5. Admin panel scaffold + assets
sudo php artisan filament:install --panels   # accept default panel id "admin"
sudo php artisan storage:link

# 6. Hand everything to the web user
sudo chown -R www-data:www-data /var/www/html/gowning
sudo chmod -R 775 storage bootstrap/cache
sudo php artisan optimize:clear
```

## Apache alias (one-time)

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
sudo apache2ctl configtest && sudo systemctl reload apache2
```

## Verify

- Landing page: `https://matcastellas.com:8080/gowning/`
- Admin login:  `https://matcastellas.com:8080/gowning/admin`  (log in with ADMIN_EMAIL / ADMIN_PASSWORD)

If the admin page loads unstyled, that is the known subdirectory asset path issue - report it.

## Updating later (pull new commits)

```bash
cd /var/www/html/gowning
sudo git pull
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo chown -R www-data:www-data /var/www/html/gowning
sudo php artisan optimize:clear
sudo systemctl reload apache2
```
