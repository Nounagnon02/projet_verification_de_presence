# Tests automatisés — Présence UAC

Tests Playwright + Chrome pour l'API backend (Render) et le frontend (Vercel).

## Structure

```
tests/
├── api/                  # Tests API backend
│   ├── health.spec.ts    # Santé du service
│   ├── auth.spec.ts      # Auth admin + étudiant mobile
│   ├── dashboard.spec.ts # Stats & tableaux de bord
│   ├── students.spec.ts  # CRUD étudiants
│   ├── presence.spec.ts  # Présences, scan, historique
│   └── ues-ecs.spec.ts   # UE / EC
├── frontend/             # Tests navigateur
│   └── login.spec.ts     # Login et dashboard
├── helpers/
│   ├── api.ts            # Utilitaires API
│   └── auth-shared.ts    # Token partagé (évite rate limiting)
├── scripts/
│   └── run-tests.sh      # Lanceur de tests
├── .env.test             # Variables d'environnement
├── playwright.config.ts  # Configuration Playwright
└── package.json
```

## Utilisation

```bash
cd tests

# Installation
npm install
npx playwright install chromium

# Tester la production (Render + Vercel)
./scripts/run-tests.sh prod          # Toute l'API + Frontend
./scripts/run-tests.sh prod api      # API uniquement
./scripts/run-tests.sh prod health   # Santé uniquement
./scripts/run-tests.sh prod auth     # Auth uniquement

# Tester en local (serveurs de dev)
./scripts/run-tests.sh local

# Voir le rapport HTML
npm run report
```

## Environnements

Les URLs et credentials sont dans `.env.test`. Modifiez-les selon votre environnement.

| Variable | Prod | Local |
|----------|------|-------|
| API URL | https://presence-uac-api.onrender.com/api | http://localhost:8000/api |
| Frontend | https://presence-uac.vercel.app | http://localhost:5173 |
| Admin | admin@presence.uac.bj / admin123 | idem |

## Notes

- Le token admin est partagé entre tous les tests (fichier `.admin-token.json`)
- Rate limiting : 1 login admin/min (throttle:login)
- Les tests étudiants sont skip si aucun étudiant n'existe en DB
