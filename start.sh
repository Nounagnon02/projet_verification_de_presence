#!/bin/bash
set -e
cd /var/www/html

# Créer le fichier .env s'il n'existe pas
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env || echo "APP_NAME=Laravel" > .env
fi

# Attendre la base de données si nécessaire
if [ "$DB_CONNECTION" = "pgsql" ] && [ ! -z "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL..."
    while ! nc -z $DB_HOST ${DB_PORT:-5432} 2>/dev/null; do
        sleep 2
    done
    echo "PostgreSQL is ready!"
elif [ "$DB_CONNECTION" = "turso" ]; then
    echo "Using Turso HTTP database - no wait required"
fi

# Générer la clé d'application si nécessaire
if [ -z "$APP_KEY" ] || ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Caches et optimisations
php artisan config:clear
php artisan migrate --force
php artisan cache:clear || echo "Cache clear failed, continuing..."
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Vérifier les assets Vite
if [ -f public/build/manifest.json ]; then
    echo "✓ Vite assets found successfully"
else
    echo "WARNING: Vite manifest not found at public/build/manifest.json"
    ls -la public/build/ || echo "Build directory not found"
fi

echo "Application ready!"
apache2-foreground