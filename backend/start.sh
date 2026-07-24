#!/bin/bash
set -e
cd /var/www/html

# Laravel lit les variables d'environnement directement depuis le container Render.
# On crée un .env minimal — les vraies valeurs viennent des env vars Render.
cat > .env << 'ENVEOF'
APP_NAME="Présence UAC"
APP_ENV=production
APP_DEBUG=false
ENVEOF

# Optimisations Laravel
php artisan config:clear
php artisan migrate --force --no-interaction

# Création des utilisateurs admin si inexistants (firstOrCreate = idempotent)
php artisan db:seed --class=AdminUserSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✓ Application ready!"
apache2-foreground
