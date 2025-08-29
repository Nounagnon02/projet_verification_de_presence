### Step 1: Node.js for frontend (Vite)
FROM node:18 AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

### Step 2: PHP for Laravel backend with PostgreSQL
FROM php:8.3-fpm

WORKDIR /var/www

# Installer les dépendances système pour PostgreSQL
RUN apt-get update && apt-get install -y \
    zip unzip curl git libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
    libpq-dev postgresql-client netcat-openbsd supervisor nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP (PostgreSQL support)
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier les fichiers du projet
COPY --chown=www-data:www-data . /var/www

# Copier les assets buildés depuis l'étape Node.js
COPY --from=node-builder /app/public/build /var/www/public/build

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Créer les répertoires nécessaires
RUN mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/bootstrap/cache

# Définir les permissions correctes
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Configuration Nginx
RUN echo 'server {\n\
    listen 8000;\n\
    server_name _;\n\
    root /var/www/public;\n\
    index index.php;\n\
\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
\n\
    location ~ \.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
    }\n\
\n\
    location ~ /\.ht {\n\
        deny all;\n\
    }\n\
}' > /etc/nginx/sites-available/default

# Configuration Supervisor
RUN echo '[supervisord]\n\
nodaemon=true\n\
user=root\n\
logfile=/var/log/supervisor/supervisord.log\n\
pidfile=/var/run/supervisord.pid\n\
\n\
[program:php-fpm]\n\
command=php-fpm\n\
user=root\n\
autostart=true\n\
autorestart=true\n\
redirect_stderr=true\n\
stdout_logfile=/var/log/supervisor/php-fpm.log\n\
\n\
[program:nginx]\n\
command=nginx -g "daemon off;"\n\
user=root\n\
autostart=true\n\
autorestart=true\n\
redirect_stderr=true\n\
stdout_logfile=/var/log/supervisor/nginx.log\n\
' > /etc/supervisor/conf.d/supervisord.conf

# Script de démarrage
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
echo "🚀 Starting Laravel application..."\n\
\n\
# Attendre que PostgreSQL soit disponible\n\
if [ ! -z "$DB_HOST" ] && [ ! -z "$DB_PORT" ]; then\n\
    echo "⏳ Waiting for PostgreSQL to be ready..."\n\
    while ! nc -z $DB_HOST $DB_PORT; do\n\
        sleep 2\n\
        echo "Still waiting for PostgreSQL on $DB_HOST:$DB_PORT..."\n\
    done\n\
    echo "✅ PostgreSQL is ready!"\n\
fi\n\
\n\
# Générer la clé d'\''application si nécessaire\n\
if [ -z "$APP_KEY" ]; then\n\
    php artisan key:generate --force\n\
fi\n\
\n\
# Optimiser Laravel pour la production\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Exécuter les migrations\n\
echo "📊 Running database migrations..."\n\
php artisan migrate --force\n\
\n\
# Créer les tables de sessions si nécessaire\n\
php artisan session:table 2>/dev/null || true\n\
php artisan migrate --force\n\
\n\
echo "🎉 Application ready!"\n\
\n\
# Démarrer supervisor (qui lance nginx + php-fpm)\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 8000

CMD ["/usr/local/bin/start.sh"]
