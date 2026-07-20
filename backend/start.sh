#!/bin/bash
set -e
cd /var/www/html

# Générer le .env depuis les variables d'environnement Render
cat > .env <<EOF
APP_NAME="${APP_NAME:-Présence UAC}"
APP_ENV=production
APP_DEBUG=false
APP_URL=${APP_URL}
APP_KEY=${APP_KEY}

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

LOG_CHANNEL=stderr
LOG_LEVEL=${LOG_LEVEL:-error}

DB_CONNECTION=pgsql
DB_HOST=aws-0-eu-west-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.kvgzlngijxrjjdvashph
DB_PASSWORD=Mesetudeskp12@
DB_SSLMODE=require

FILESYSTEM_DISK=supabase
SUPABASE_URL=${SUPABASE_URL}
SUPABASE_KEY=${SUPABASE_KEY}
SUPABASE_SECRET=${SUPABASE_SECRET}
SUPABASE_BUCKET=${SUPABASE_BUCKET:-presence-uac}

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

ALLOWED_ORIGINS=${ALLOWED_ORIGINS}
FRONTEND_URL=${FRONTEND_URL}

MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-noreply@presence.uac.bj}
MAIL_FROM_NAME="${APP_NAME:-Présence UAC}"

GEMINI_API_KEY=${GEMINI_API_KEY}
EOF

# Optimisations Laravel
php artisan config:clear
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✓ Application ready!"
apache2-foreground
