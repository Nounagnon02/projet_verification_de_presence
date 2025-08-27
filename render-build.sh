#!/bin/bash
# render-build.sh

echo "🔨 Building Laravel application for Render..."

# Vérifier que Docker est installé
if ! command -v docker &> /dev/null; then
    echo "❌ Docker n'est pas installé"
    exit 1
fi

# Build de l'image Docker
docker build -t laravel-render .

echo "✅ Build completed successfully!"
echo "📦 Image Docker créée: laravel-render"
