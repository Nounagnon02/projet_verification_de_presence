# UAC Présence — Backend (Laravel)

Système de Gestion de Présence pour l'Université d'Abomey-Calavi.

## Stack technique

- **Framework :** Laravel 12 / PHP 8.3
- **Base de données :** PostgreSQL 16 (production) / MySQL (dev)
- **Cache/Queues :** Redis ou Database
- **IA :** Google Gemini API 2.5 Flash
- **QR Code :** SimpleSoftwareIO/QrCode
- **Auth :** Laravel Sanctum (tokens API stateful)

## Installation

```bash
cp .env.example .env
# Configurer la base de données dans .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Pour le développement

```bash
composer run dev
# Lance serveur + queue worker + Vite
```

## Tests

```bash
php artisan test
```

## Déploiement

Voir `deploy.sh` et `render.yaml` pour Render.com.  
Ou utiliser le Dockerfile inclus.

## Architecture

Le backend expose une API RESTful consommée par le frontend React SPA.
Authentification via Laravel Sanctum (sessions + tokens).
Traitements asynchrones (email, IA Gemini) via Laravel Queues.

## Variables d'environnement requises

- `GEMINI_API_KEY` — Clé API Google Gemini pour analyse PDF
- `MAIL_MAILER`/`MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` — Configuration SMTP
- `DB_CONNECTION`/`DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` — Base de données
- `APP_ENV=production` + `APP_DEBUG=false` en production
- `SESSION_DRIVER=database` + `SESSION_LIFETIME=120`
