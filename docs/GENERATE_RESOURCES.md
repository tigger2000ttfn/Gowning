# Generating the Filament resources (run on the E3 server)

Filament's scaffolder writes resource classes in the exact syntax of your installed v5
version. Generate the CRUD shells with these commands, then pull the refinements I push
to `develop` (theming, qualification logic, validation, e-signature fields).

```bash
cd /var/www/html/gowning
sudo git checkout develop
sudo git pull
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# Generate resources into the panel's namespace (app/Filament/Admin/Resources).
# --generate inspects the model + columns to pre-fill form and table.
sudo php artisan make:filament-resource Personnel --generate --no-interaction
sudo php artisan make:filament-resource Qualification --generate --no-interaction
sudo php artisan make:filament-resource QualificationRun --generate --no-interaction
sudo php artisan make:filament-resource RunSlot --generate --no-interaction
sudo php artisan make:filament-resource Reservation --generate --no-interaction
sudo php artisan make:filament-resource ClassCompletion --generate --no-interaction
sudo php artisan make:filament-resource ImportBatch --generate --no-interaction

# Commit the generated shells back so I can refine them in the repo.
sudo -u www-data git add app/Filament
sudo -u www-data git commit -m "Generate Filament resource shells for all models"
sudo -u www-data git push origin develop

sudo php artisan optimize:clear
sudo systemctl restart apache2
```

After this, the sidebar shows all seven resources (grouped) and you can already browse and
edit records. Tell me it's pushed and I'll refine each resource: status badges driven by the
qualification engine, run recording with electronic-signature fields, the slot/reservation
approval flow, and the CSV importer.

Note: if `make:filament-resource` asks whether the resource is for a specific panel, choose
`admin`. If it asks about the model namespace, the models live in `App\Models`.
