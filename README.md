# SGP-UAC — Système de Gestion de Présence

Vérification de présence étudiante par QR Code dynamique, pour l'Université
d'Abomey-Calavi. Un administrateur de faculté importe ses cours et son emploi du
temps, l'application génère un QR Code qui tourne chaque minute pendant la
fenêtre de présence, et l'étudiant le scanne depuis son téléphone.

## Ce que contient le dépôt

| Dossier | Rôle | Pile |
|---|---|---|
| `backend/` | API REST, règles métier, import IA, rapports | Laravel 12, PHP 8.3, PostgreSQL |
| `frontend/` | Interface d'administration | React 19, Vite, Tailwind |
| `mobile-app/` | Application étudiante (scan) | Expo / React Native, TypeScript |
| `tests/` | Campagnes transverses : bout en bout, charge, sécurité, IA | Playwright, k6, OWASP ZAP |
| `docs/PLAN_DE_TESTS.md` | Stratégie de test et état mesuré | — |

Les autres fichiers de `docs/` ne sont pas versionnés : ils relèvent du mémoire.

## Démarrer en local

Prérequis : PHP 8.3, Composer, Node 20, Docker (pour la base de test).

```bash
# Backend
cd backend
cp .env.example .env && php artisan key:generate
make db-up                      # PostgreSQL de test, conteneur jetable
php artisan migrate --force
php artisan db:seed             # jeu de démonstration
php artisan serve               # http://127.0.0.1:8000

# Frontend (autre terminal)
cd frontend && npm ci && npm run dev    # http://127.0.0.1:5173

# Mobile (autre terminal)
cd mobile-app && npm ci --legacy-peer-deps && npm start
```

Les comptes d'administration ne sont jamais créés avec un mot de passe par
défaut : renseignez `SEED_ADMIN_PASSWORD` et `SEED_SUPERADMIN_PASSWORD` avant de
semer, sinon un mot de passe aléatoire est tiré et il faut passer par « mot de
passe oublié ».

## Tests

```bash
cd backend  && make test              # PHPUnit (exige le conteneur Postgres)
cd frontend && npm run test:coverage  # Vitest, seuils en cliquet
cd mobile-app && npm run test:ci      # Jest
cd tests    && ./scripts/run-tests.sh local   # Playwright, cible LOCALE
```

⚠️ `run-tests.sh prod` vise la **production** et écrit dedans (création et
suppression d'étudiants). À n'utiliser qu'en connaissance de cause.

Ne lancez pas Vitest, `npm run build` et la suite PHPUnit en même temps : la
machine tombe en OOM et emporte les serveurs de développement.

Campagnes lourdes, toutes documentées avec leur pile de mesure :
[IA](tests/ia/RESULTATS.md), [charge](tests/load/RESULTATS.md),
[sécurité](tests/security/RESULTATS.md).

## Intégration continue

| Fichier | Déclenchement | Contenu |
|---|---|---|
| `.github/workflows/ci.yml` | chaque poussée | lint, types, 3 suites de tests, audit des dépendances, rejeu IA |
| `.github/workflows/e2e.yml` | pull request | Playwright sur une pile locale |
| `.github/workflows/securite-zap.yml` | manuel | passe OWASP ZAP |
| `.github/workflows/charge-k6.yml` | manuel | campagne de charge LOAD-01 |

## Déploiement

L'API tourne sur Render (Docker, `backend/Dockerfile`), l'interface sur Vercel,
la base et le stockage sur Supabase. `render.yaml` décrit les services et les
variables ; les secrets ne sont jamais dans le dépôt et se renseignent dans le
tableau de bord Render (`sync: false`).

Le plan gratuit de Render endort le service après quinze minutes : la première
requête de la journée peut demander une cinquantaine de secondes. Une tâche cron
réveille l'application chaque minute et draine la file d'attente, faute de
worker permanent.

## Sécurité

- Les secrets ne sont jamais versionnés. Toute valeur qui a été committée est à
  considérer comme compromise et doit être régénérée, la retirer ne suffit pas.
- Le scan de présence exige un jeton étudiant ; la géolocalisation et le réseau
  sont vérifiés côté serveur, jamais sur la foi du client.
- Le cloisonnement entre facultés est vérifié par un test paramétré qui parcourt
  toutes les routes d'administration.
