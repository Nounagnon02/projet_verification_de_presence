#!/bin/bash
set -e
cd /var/www/html

# Créer le .env minimal (les vraies valeurs viennent des env vars Render)
cat > .env << 'ENVEOF'
APP_NAME="Présence UAC"
APP_ENV=production
APP_DEBUG=false
ENVEOF

php artisan config:clear
php artisan config:cache

echo "✓ Worker ready, starting queue..."
php artisan queue:work database --queue=gemini-import,default --sleep=3 --tries=3 --max-time=3600 --memory=256
