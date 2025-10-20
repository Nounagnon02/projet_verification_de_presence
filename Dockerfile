### Step 1: Node.js for frontend (Vite)
FROM node:18 AS node-builder
WORKDIR /app

# Copier les fichiers de configuration
COPY package*.json ./
COPY postcss.config.js ./
COPY tailwind.config.js ./
COPY vite.config.js ./

# Installer les dépendances
RUN npm install

# Copier les sources
COPY resources/ ./resources/
COPY public/ ./public/

# Build des assets
RUN npm run build

# Vérifier que le build a réussi
RUN ls -la public/build/ && cat public/build/manifest.json

### Step 2: PHP for Laravel backend with PostgreSQL
FROM php:8.3-apache

WORKDIR /var/www/html

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    zip unzip curl git libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
    libpq-dev postgresql-client netcat-openbsd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

# Configuration Apache
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Copier tous les fichiers du projet
COPY . /var/www/html/

# Supprimer l'ancien build s'il existe et copier le nouveau
RUN rm -rf /var/www/html/public/build
COPY --from=node-builder /app/public/build /var/www/html/public/build

# Vérifier que les assets sont présents
RUN ls -la /var/www/html/public/build/ && \
    cat /var/www/html/public/build/manifest.json

# Créer le fichier .env s'il n'existe pas
RUN if [ ! -f /var/www/html/.env ]; then \
        echo "Creating .env file from environment variables" && \
        echo "APP_NAME=Laravel" > /var/www/html/.env && \
        echo "APP_ENV=production" >> /var/www/html/.env && \
        echo "APP_DEBUG=false" >> /var/www/html/.env && \
        echo "APP_URL=https://projet-verification-de-presence-3.onrender.com" >> /var/www/html/.env && \
        echo "LOG_CHANNEL=single" >> /var/www/html/.env && \
        echo "LOG_LEVEL=error" >> /var/www/html/.env && \
        echo "DB_CONNECTION=pgsql" >> /var/www/html/.env && \
        echo "SESSION_DRIVER=database" >> /var/www/html/.env && \
        echo "CACHE_STORE=database" >> /var/www/html/.env && \
        echo "QUEUE_CONNECTION=database" >> /var/www/html/.env; \
    fi

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Script de démarrage
RUN echo '#!/bin/bash\n\
set -e\n\
cd /var/www/html\n\
\n\
# Attendre PostgreSQL\n\
if [ ! -z "$DB_HOST" ]; then\n\
    echo "Waiting for PostgreSQL..."\n\
    while ! nc -z $DB_HOST $DB_PORT 2>/dev/null; do\n\
        sleep 2\n\
    done\n\
    echo "PostgreSQL is ready!"\n\
fi\n\
\n\
# Générer la clé si nécessaire\n\
if [ -z "$APP_KEY" ] || ! grep -q "APP_KEY=base64:" .env; then\n\
    php artisan key:generate --force\n\
fi\n\
\n\
# Nettoyer la configuration seulement\n\
php artisan config:clear\n\
\n\
# Migrations AVANT les opérations de cache\n\
php artisan migrate --force\n\
\n\
# Maintenant on peut nettoyer le cache\n\
php artisan cache:clear || echo "Cache clear failed, continuing..."\n\
php artisan view:clear\n\
php artisan route:clear\n\
\n\
# Cache pour production\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Vérifier les assets\n\
if [ ! -f public/build/manifest.json ]; then\n\
    echo "ERROR: Vite manifest not found!"\n\
    ls -la public/build/ || echo "Build directory not found"\n\
else\n\
    echo "Assets found successfully"\n\
fi\n\
\n\
echo "Application ready!"\n\
\n\
# Démarrer Apache\n\
apache2-foreground\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]

