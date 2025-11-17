# Stage 1: Build Node.js assets
FROM node:20-alpine AS node-builder

WORKDIR /app

# Copier tous les fichiers de configuration nécessaires
COPY package*.json ./
COPY vite.config.js ./
COPY postcss.config.js ./
COPY tailwind.config.js ./

# Installer les dépendances Node.js
RUN npm ci

# Copier le reste des fichiers nécessaires
COPY resources ./resources
COPY public ./public

# Build des assets avec Vite pour production
RUN npm run build

# Copier le manifest depuis .vite/ vers la racine (Vite 7+)
RUN if [ -f public/build/.vite/manifest.json ]; then \
        echo "Copying Vite manifest from .vite subdirectory..."; \
        cp public/build/.vite/manifest.json public/build/manifest.json; \
    fi && \
    if [ ! -f public/build/manifest.json ]; then \
        echo "ERROR: Vite build failed - manifest.json not found!"; \
        ls -la public/build/ || echo "Build directory missing"; \
        exit 1; \
    fi

# Stage 2: PHP Application
FROM php:8.3-apache

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration Apache
RUN a2enmod rewrite headers
COPY <<EOF /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

WORKDIR /var/www/html

# Copier les fichiers de l'application
COPY --chown=www-data:www-data . .

# Créer le fichier .env à partir de .env.example
RUN if [ -f .env.example ]; then cp .env.example .env; else echo "APP_NAME=Laravel" > .env; fi

# Copier les assets buildés depuis le stage Node.js
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Script de démarrage
RUN echo '#!/bin/bash\n\
set -e\n\
cd /var/www/html\n\
\n\
# Créer le fichier .env s'\''il n'\''existe pas\n\
if [ ! -f .env ]; then\n\
    echo "Creating .env file from .env.example..."\n\
    cp .env.example .env || echo "APP_NAME=Laravel" > .env\n\
fi\n\
\n\
# Attendre PostgreSQL\n\
if [ ! -z "$DB_HOST" ]; then\n\
    echo "Waiting for PostgreSQL..."\n\
    while ! nc -z $DB_HOST ${DB_PORT:-5432} 2>/dev/null; do\n\
        sleep 2\n\
    done\n\
    echo "PostgreSQL is ready!"\n\
fi\n\
\n\
# Générer la clé d'\''application si nécessaire\n\
if [ -z "$APP_KEY" ] || ! grep -q "APP_KEY=base64:" .env; then\n\
    echo "Generating application key..."\n\
    php artisan key:generate --force\n\
fi\n\
\n\
# Caches et optimisations\n\
php artisan config:clear\n\
php artisan migrate --force\n\
php artisan cache:clear || echo "Cache clear failed, continuing..."\n\
php artisan view:clear\n\
php artisan route:clear\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Vérifier les assets Vite\n\
if [ -f public/build/manifest.json ]; then\n\
    echo "✓ Vite assets found successfully"\n\
else\n\
    echo "WARNING: Vite manifest not found at public/build/manifest.json"\n\
    ls -la public/build/ || echo "Build directory not found"\n\
fi\n\
\n\
echo "Application ready!"\n\
apache2-foreground\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
