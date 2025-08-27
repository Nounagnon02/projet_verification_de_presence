#!/bin/bash
# deploy.sh

echo "🚀 Déploiement sur Render..."

# Vérifier les variables d'environnement
if [ -z "$RENDER_API_KEY" ]; then
    echo "❌ RENDER_API_KEY non définie"
    exit 1
fi

# Build l'image
docker build -t laravel-render .

# (Optionnel) Push vers un registry si nécessaire
# docker tag laravel-render your-registry/laravel-render
# docker push your-registry/laravel-render

echo "✅ Prêt pour le déploiement sur Render!"
echo "📋 Assurez-vous d'avoir configuré:"
echo "   - Les variables d'environnement dans Render Dashboard"
echo "   - La base de données externe"
echo "   - Le domaine personnalisé (optionnel)"
