#!/bin/bash

# Deployment script for Laravel on Render

echo "Starting deployment..."

# Generate APP_KEY if not exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear all caches
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Try database migration with fallback
echo "Attempting database migration..."
if php artisan migrate --force; then
    echo "Database migration successful"
else
    echo "Database migration failed, continuing without database..."
fi

# Cache configuration for production
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment completed!"