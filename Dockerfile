### Step 1: Node.js for frontend (Vite)
FROM node:18 AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

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

# Configuration Apache pour Render (PORT dynamique)
RUN echo 'Listen ${PORT}\n\
<VirtualHost *:${PORT}>\n\
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

# Copier les assets buildés
COPY --from=node-builder /app/public/build /var/www/html/public/build

# Créer le fichier .env à partir des variables d'environnement
RUN echo "APP_NAME=Laravel\n\
APP_ENV=production\n\
APP_DEBUG=false\n\
APP_URL=https://projet-verification-de-presence-3.onrender.com\n\
LOG_CHANNEL=stderr\n\
LOG_LEVEL=error\n\
DB_CONNECTION=pgsql\n\
SESSION_DRIVER=database\n\
CACHE_STORE=database\n\
QUEUE_CONNECTION=database" > /var/www/html/.env

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Script de démarrage adapté pour Render
RUN echo '#!/bin/bash\n\
set -e\n\
cd /var/www/html\n\
\n\
# Attendre PostgreSQL si DB_HOST est défini\n\
if [ ! -z "$DB_HOST" ]; then\n\
    echo "Waiting for PostgreSQL..."\n\
    while ! nc -z $DB_HOST $DB_PORT 2>/dev/null; do\n\
        sleep 2\n\
    done\n\
    echo "PostgreSQL is ready!"\n\
fi\n\
\n\
# Générer la clé application si absente\n\
if [ -z "$(grep \"APP_KEY=base64:\" .env)" ]; then\n\
    php artisan key:generate --force\n\
    echo "Application key generated!"\n\
fi\n\
\n\
# Exécuter les migrations\n\
php artisan migrate --force\n\
\n\
# Nettoyer les caches\n\
php artisan config:clear\n\
php artisan cache:clear\n\
php artisan view:clear\n\
\n\
# Créer les caches pour production\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
echo "Application is ready!"\n\
\n\
# Démarrer Apache sur le port dynamique de Render\n\
exec apache2-foreground\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Render utilise le port via variable d'environnement
EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]
