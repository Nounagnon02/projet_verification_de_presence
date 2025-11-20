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
elif [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Setting up SQLite database..."
    touch ${DB_DATABASE:-/var/www/html/storage/database.sqlite}
    chmod 664 ${DB_DATABASE:-/var/www/html/storage/database.sqlite}
fi

# Générer la clé d'application si nécessaire
if [ -z "$APP_KEY" ] || ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Caches et optimisations
php artisan config:clear

# Tentative de migration avec gestion d'erreur
echo "Attempting database migration..."
php artisan migrate --force || echo "Migration failed, continuing without database..."

php artisan cache:clear || echo "Cache clear failed, continuing..."
php artisan view:clear || echo "View clear failed, continuing..."
php artisan route:clear || echo "Route clear failed, continuing..."

# Cache seulement si les commandes précédentes ont réussi
php artisan config:cache || echo "Config cache failed, continuing..."
php artisan route:cache || echo "Route cache failed, continuing..."
php artisan view:cache || echo "View cache failed, continuing..."

# Vérifier les assets Vite
if [ -f public/build/manifest.json ]; then
    echo "✓ Vite assets found successfully"
else
    echo "WARNING: Vite manifest not found at public/build/manifest.json"
    ls -la public/build/ || echo "Build directory not found"
fi

echo "Application ready!"
apache2-foreground