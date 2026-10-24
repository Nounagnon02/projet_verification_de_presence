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
présence (fenêtre de scan ancrée sur la fin du cours, durée de vie du QR Code,
durée maximale d'une séance, limites de débit anti-fraude) vivent dans la table
`parametres_globaux` (modèle `ParametreGlobal`, une seule ligne), modifiable
par le super administrateur depuis l'écran « Paramètres », et nulle part
ailleurs. Les traitements longs — analyse IA d'un PDF, envoi d'identifiants —
passent par la file d'attente.

## Variables d'environnement

Voir `.env.example`, qui les documente toutes. Les plus structurantes :

| Variable | Rôle |
|---|---|
| `DB_*`, `DB_SSLMODE` | PostgreSQL (Supabase en production) |
| `APP_ENV`, `APP_DEBUG` | `production` / `false` en service. Trois protections (HTTPS forcé, CSP stricte, URL en https) dépendent de cette seule valeur |
| `AI_PROVIDER`, `GEMINI_API_KEY`, `GEMINI_MODEL` | extraction des maquettes et emplois du temps |
| `MAIL_MAILER`, `GMAIL_*`, `MAIL_FROM_*` | envoi des identifiants par l'API Gmail (SMTP sortant bloqué sur Render, voir ci-dessous) |
| `SUPABASE_*`, `FILESYSTEM_DISK` | stockage des documents importés |
| `ALLOWED_ORIGINS`, `FRONTEND_URL` | CORS et liens des courriels |
| `SEED_ADMIN_PASSWORD`, `SEED_SUPERADMIN_PASSWORD` | comptes initiaux |
| `PRESENCE_*` | règles métier de la fenêtre de présence et du QR Code |

## E-mails (Gmail)

Render gratuit bloque le SMTP sortant (ports 25, 465, 587) : `smtp.gmail.com`
est injoignable. Le pilote `gmail` (`App\Mail\Transport\GmailApiTransport`)
passe donc par l'API Gmail en HTTPS, avec un jeton OAuth2 renouvelé à partir
d'un *refresh token*. Aucun mot de passe de compte n'est stocké.

Obtenir les trois valeurs `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET` et
`GMAIL_REFRESH_TOKEN` (une seule fois, avec le compte Gmail expéditeur) :

1. Console Google Cloud → nouveau projet → *API et services* → activer
   **Gmail API**.
2. *Écran de consentement OAuth* : type « Externe », ajouter le compte comme
   utilisateur test, ajouter la portée `https://www.googleapis.com/auth/gmail.send`,
   puis **publier l'application** (statut « En production »). En mode « Test »,
   le refresh token expire au bout de 7 jours.
3. *Identifiants* → *Créer des identifiants* → ID client OAuth, type
   « Application Web », avec l'URI de redirection
   `https://developers.google.com/oauthplayground`.
4. Sur <https://developers.google.com/oauthplayground> : roue dentée →
   « Use your own OAuth credentials » → coller l'ID et le secret → autoriser la
   portée `https://www.googleapis.com/auth/gmail.send` → *Exchange authorization
   code for tokens* → copier le **Refresh token**.
5. Dans Render : `MAIL_MAILER=gmail`, les trois variables `GMAIL_*` et
   `MAIL_FROM_ADDRESS` (l'adresse Gmail autorisée).

Plafond Gmail : environ 500 messages par jour pour un compte gratuit. Les
échecs d'envoi sont journalisés (`Erreur envoi email à …`) ; un refresh token
révoqué s'y reconnaît à `invalid_grant`.
