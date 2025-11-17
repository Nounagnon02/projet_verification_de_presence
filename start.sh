#!/bin/bash
set -e

cd /var/www/html

echo "Starting application initialization..."

# Créer le fichier .env s'il n'existe pas
if [ ! -f .env ]; then
    echo "Creating .env file..."
    if [ -f .env.production ]; then
        cp .env.production .env
    elif [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "APP_NAME=Laravel" > .env
        echo "APP_ENV=production" >> .env
        echo "APP_DEBUG=false" >> .env
    fi
fi

# Attendre PostgreSQL si configuré
if [ ! -z "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL at $DB_HOST:${DB_PORT:-5432}..."
    timeout=60
    while ! nc -z $DB_HOST ${DB_PORT:-5432} 2>/dev/null; do
        sleep 2
        timeout=$((timeout-2))
        if [ $timeout -le 0 ]; then
            echo "ERROR: PostgreSQL connection timeout"
            exit 1
        fi
    done
    echo "✓ PostgreSQL is ready!"
fi

# Générer la clé d'application
if [ -z "$APP_KEY" ] || ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate --force || {
        echo "WARNING: Could not generate app key, continuing..."
    }
fi

# Nettoyer les caches
echo "Clearing caches..."
php artisan config:clear || echo "Config clear failed"
php artisan cache:clear || echo "Cache clear failed"
php artisan view:clear || echo "View clear failed"
php artisan route:clear || echo "Route clear failed"

# Exécuter les migrations
echo "Running migrations..."
php artisan migrate --force || {
    echo "WARNING: Migration failed, continuing..."
}

# Optimiser pour la production
echo "Optimizing for production..."
php artisan config:cache || echo "Config cache failed"
php artisan route:cache || echo "Route cache failed"
php artisan view:cache || echo "View cache failed"

# Vérifier les assets
if [ -f public/build/manifest.json ]; then
    echo "✓ Vite assets found"
else
    echo "WARNING: Vite manifest not found"
    ls -la public/build/ 2>/dev/null || echo "Build directory missing"
fi

# Définir les permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✓ Application ready!"
exec apache2-foreground