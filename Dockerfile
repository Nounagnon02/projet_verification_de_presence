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
    libpq-dev postgresql-client  # ← Ajouter PostgreSQL

# Installer les extensions PHP (ajouter pgsql)
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www
COPY --chown=www-data:www-data . /var/www

# Copy only built frontend assets (from Vite)
COPY --from=node-builder /app/public/build /var/www/public/build

RUN composer install --no-dev --optimize-autoloader

# Créer .env avec configuration PostgreSQL
RUN cp .env.example .env && \
    echo "DB_CONNECTION=pgsql" >> .env && \
    echo "DB_HOST=postgres" >> .env && \
    echo "DB_PORT=5432" >> .env && \
    echo "DB_DATABASE=laravel" >> .env && \
    echo "DB_USERNAME=laravel_user" >> .env && \
    echo "DB_PASSWORD=password" >> .env

RUN php artisan key:generate

EXPOSE 8000

# Script de démarrage avec attente PostgreSQL
COPY docker/wait-for-postgres.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/wait-for-postgres.sh

CMD ["/usr/local/bin/wait-for-postgres.sh", "php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
