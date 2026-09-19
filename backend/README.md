# UAC Présences — Backend (Laravel)

API du Système de Gestion de Présence de l'Université d'Abomey-Calavi.

## Pile technique

- **Cadre :** Laravel 12 / PHP 8.3
- **Base de données :** PostgreSQL 15 partout — production (Supabase),
  développement et tests. MySQL n'est plus utilisé nulle part.
- **File d'attente et cache :** la file utilise le pilote `database`, drainée
  chaque minute par le planificateur (`routes/console.php`) ; le cache utilise
  `file`, car le limiteur de débit le sollicite à chaque requête et la base est à
  ~600 ms d'aller-retour. Pas de Redis ni de worker permanent : le plan gratuit
  de Render n'en offre pas.
- **IA :** fournisseur interchangeable (`AI_PROVIDER` : gemini, groq,
  openrouter). Le modèle se règle par `GEMINI_MODEL`, `GROQ_MODEL`,
  `OPENROUTER_MODEL` — jamais dans le code : Google a retiré
  `gemini-2.0-flash` du jour au lendemain et tout l'import IA s'est arrêté.
- **QR Code :** SimpleSoftwareIO/QrCode
- **Authentification :** Laravel Sanctum, jetons Bearer à capacités
  (`admin` / `etudiant`)

## Installation

```bash
cp .env.example .env
php artisan key:generate
make db-up                 # PostgreSQL 15 jetable sur 127.0.0.1:55434
php artisan migrate --force
php artisan db:seed        # jeu de démonstration
php artisan serve
```

Les comptes d'administration ne reçoivent jamais de mot de passe par défaut :
renseignez `SEED_ADMIN_PASSWORD` et `SEED_SUPERADMIN_PASSWORD` avant de semer,
sinon un mot de passe aléatoire est tiré et le compte doit passer par « mot de
passe oublié ».

## Tests

```bash
make test              # démarre la base de test au besoin, puis php artisan test
make lint              # syntaxe PHP de app/, database/, routes/, tests/
make phpstan           # analyse statique, niveau 5 : seuls les NOUVEAUX constats échouent
./vendor/bin/pint --test   # style
```

La suite exige une base dédiée (`.env.testing`). Un garde applicatif refuse de
la lancer contre la base déclarée dans `.env` : les migrations y passeraient.

## Déploiement

`render.yaml` décrit les services (API, worker cron) et le `Dockerfile` construit
l'image. Les secrets sont marqués `sync: false` et se saisissent dans le tableau
de bord Render : rien de sensible n'entre dans le dépôt.

**La production reste sous Apache/mod_php** (choix du 2026-09-19). `laravel/octane`
a été mesuré (facteur 4 sur la latence sous charge, voir `tests/load/RESULTATS.md`),
mais c'est une dépendance de **développement** : elle sert à rejouer cette campagne
et n'entre pas dans l'image (`composer install --no-dev`).

Corollaire : tout paquet que le code applicatif utilise doit être dans `require`,
jamais dans `require-dev`. Les tests tournent avec les dépendances de
développement et ne voient pas ce manque : `symfony/yaml`, tiré seulement par
`laravel/sail`, faisait répondre 500 à `/api/docs/json` en production
(`SurfacePubliqueTest` verrouille ce cas).

## Architecture

API REST consommée par le SPA React et l'application mobile Expo. Les règles de
présence vivent dans `config/presence.php` (fenêtre de scan ancrée sur la fin du
cours, durée de vie du QR Code, durée maximale d'une séance) et nulle part
ailleurs. Les traitements longs — analyse IA d'un PDF, envoi d'identifiants —
passent par la file d'attente.

## Variables d'environnement

Voir `.env.example`, qui les documente toutes. Les plus structurantes :

| Variable | Rôle |
|---|---|
| `DB_*`, `DB_SSLMODE` | PostgreSQL (Supabase en production) |
| `APP_ENV`, `APP_DEBUG` | `production` / `false` en service. Trois protections (HTTPS forcé, CSP stricte, URL en https) dépendent de cette seule valeur |
| `AI_PROVIDER`, `GEMINI_API_KEY`, `GEMINI_MODEL` | extraction des maquettes et emplois du temps |
| `MAIL_MAILER`, `RESEND_KEY`, `MAIL_FROM_*` | envoi des identifiants (SMTP sortant bloqué sur Render, d'où l'API HTTP Resend) |
| `SUPABASE_*`, `FILESYSTEM_DISK` | stockage des documents importés |
| `ALLOWED_ORIGINS`, `FRONTEND_URL` | CORS et liens des courriels |
| `SEED_ADMIN_PASSWORD`, `SEED_SUPERADMIN_PASSWORD` | comptes initiaux |
| `PRESENCE_*` | règles métier de la fenêtre de présence et du QR Code |
