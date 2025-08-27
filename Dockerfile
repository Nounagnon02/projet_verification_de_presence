### Step 1: Node.js for frontend (Vite)
FROM node:18 AS node-builder

WORKDIR /app
COPY . .

RUN npm install && npm run build

### Step 2: PHP for Laravel backend with PostgreSQL
FROM php:8.3-fpm

WORKDIR /var/www

# Installer les dépendances pour PostgreSQL
RUN apt-get update && apt-get install -y \
    zip unzip curl git libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
    libpq-dev

# Installer les extensions PHP
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www
COPY --chown=www-data:www-data . /var/www

# Copy only built frontend assets (from Vite)
COPY --from=node-builder /app/public/build /var/www/public/build

RUN composer install --no-dev --optimize-autoloader

# NE PAS configurer les variables DB ici - elles viendront de Render
RUN cp .env.example .env

RUN php artisan key:generate

EXPOSE 8000

# Script de démarrage simple
RUN echo '#!/bin/sh\n\
\n\
# Attendre un peu que la DB soit disponible (Render gère l\'ordre)\n\
sleep 5\n\
\n\
# Exécuter les migrations\n\
php artisan migrate --force\n\
\n\
# Démarrer l\'application\n\
exec php artisan serve --host=0.0.0.0 --port=8000\n\
' > /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
