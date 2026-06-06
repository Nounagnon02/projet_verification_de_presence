# UAC Présence — Frontend (React)

Interface d'administration du Système de Gestion de Présence UAC.

## Stack technique

- **Framework :** React 19
- **Router :** React Router 7
- **UI :** Tailwind CSS 3.4, React Icons, Lucide React
- **HTTP :** Axios avec intercepteurs
- **Build :** Vite 8

## Installation

```bash
npm install
npm run dev     # Développement (proxy API → localhost:8000)
npm run build   # Production (génère dist/)
```

## Configuration

Copier `.env.example` vers `.env` et ajuster :

```
VITE_API_URL=/api   # URL de l'API backend
```

## Structure du projet

```
src/
├── api/           # Configuration Axios
├── components/    # Composants réutilisables (UI, charts, layout)
├── context/       # Contextes React (Auth, Toast)
├── hooks/         # Hooks personnalisés (useApi, useDebounce)
├── pages/         # Pages de l'application
│   ├── auth/      # Connexion, erreurs
│   ├── attendance/ # Présences, scan QR, statistiques
│   ├── courses/   # Gestion des cours
│   ├── dashboard/ # Tableau de bord
│   ├── import/    # Import CSV/PDF + validation IA
│   ├── reports/   # Rapports et exports
│   ├── schedules/ # Emplois du temps
│   ├── settings/  # Paramètres
│   ├── students/  # Gestion des étudiants
│   ├── support/   # Support et tickets
│   ├── faq/       # FAQ
│   └── help/      # Centre d'aide
└── utils/         # Utilitaires
```

## Fonctionnalités

- Dashboard avec KPIs en temps réel
- Gestion des étudiants (individuel + CSV)
- Import IA des emplois du temps (Gemini)
- Validation de présence par QR Code dynamique
- Statistiques et rapports PDF/CSV
- Détection de fraude (device fingerprinting)
- Centre d'aide et support tickets
- Chat en direct
