#!/bin/bash
# docker/start.sh

echo "🚀 Démarrage de l'application Laravel..."

# Attendre que MySQL soit prêt
echo "⏳ Attente du démarrage de MySQL..."
while ! nc -z mysql 3306; do
  sleep 1
done

echo "✅ MySQL est démarré"

# Exécuter les migrations et seeders
php artisan migrate --force
php artisan db:seed --force

# Démarrer Apache
apache2-foreground
