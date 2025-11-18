#!/bin/bash

# Attendre que la base de données soit prête
echo "Attente de la base de données..."
sleep 10

# Nettoyer les caches
echo "Nettoyage des caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Exécuter les migrations
echo "Exécution des migrations..."
php artisan migrate --force

# Créer les tables de cache et sessions si elles n'existent pas
echo "Création des tables système..."
php artisan queue:table --quiet || true
php artisan session:table --quiet || true
php artisan cache:table --quiet || true

# Exécuter les nouvelles migrations
php artisan migrate --force

# Optimiser l'application
echo "Optimisation de l'application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Démarrer le serveur
echo "Démarrage du serveur..."
php artisan serve --host=0.0.0.0 --port=$PORT