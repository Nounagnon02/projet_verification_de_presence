# Cahier des Charges - Projet de Vérification de Présence

## 1. Présentation du Projet
Le projet consiste en une application web (PWA) de suivi de présence automatisée et manuelle. L'objectif principal est de simplifier l'enregistrement des présences via des technologies modernes (QR Codes) tout en motivant les participants par un système de récompenses (gamification).

## 2. Objectifs du Projet
- **Automatisation** : Réduire le temps passé à l'appel manuel grâce au scan de QR Codes.
- **Transparence** : Permettre aux membres et aux administrateurs de suivre l'historique de présence en temps réel.
- **Engagement** : Encourager la régularité via un système de points, de badges et de récompenses.
- **Analyse** : Fournir des outils de visualisation (Heatmaps, statistiques avancées) pour identifier les tendances d'assiduité.

## 3. Analyse des Acteurs
### 3.1 Administrateur / Manager (User)
- Gère la liste des membres.
- Génère et rafraîchit les QR Codes de présence.
- Marque manuellement les présences.
- Analyse les statistiques et exporte les données (via Calendrier ou Tableaux).
- Configure les alertes pour détecter les anomalies (absences répétées).
- Gère le catalogue de récompenses et valide les échanges de points.

### 3.2 Membre (Member)
- Scanne les QR Codes pour marquer sa présence.
- Consulte son historique de présence.
- Reçoit des points et des badges en fonction de son assiduité.
- Échange ses points contre des récompenses.

## 4. Spécifications Fonctionnelles
### 4.1 Gestion de la Présence
- **Scan QR Code** : Système de code dynamique (rafraîchissable) pour éviter les fraudes.
- **Enregistrement de Localisation** : Capture optionnelle des coordonnées GPS lors du scan pour vérification.
- **Ajout Manuel** : Interface rapide pour marquer plusieurs membres à la fois.
- **Vérification de Dispositif** : Sécurité pour s'assurer que le scan provient d'un appareil unique.

### 4.2 Gamification & Récompenses
- **Système de Points** : Attribution de points pour chaque présence.
- **Badges** : Obtention automatique de titres (ex: "Le plus ponctuel", "Série de 10") via des règles logiques.
- **Catalogue de Récompenses** : Liste de prix virtuels ou réels déblocables.

### 4.3 Statistiques et Rapports
- **Tableau de Bord** : Vue d'ensemble des présences du jour.
- **Heatmap** : Visualisation des pics d'affluence par heure et par jour.
- **Stats Avancées** : Comparaison de périodes, taux d'assiduité moyen par groupe.

### 4.4 Monitoring et Alertes
- **Détection d'Anomalies** : Alertes automatiques en cas d'absence prolongée ou suspecte.
- **Notifications** : Système d'alerte pour les administrateurs.

## 5. Spécifications Techniques
- **Framework** : Laravel 11 (PHP 8.2+).
- **Base de données** : PostgreSQL (Production) / SQLite (Développement).
- **Frontend** : Blade Templates + Vanilla CSS + Vite + Tailwind CSS.
- **PWA (Progressive Web App)** : Support du mode hors-ligne via Service Workers.
- **Internationalisation (i18n)** : Support multi-langues.
- **Sécurité** : Chiffrement AES-256, conformité RGPD (gestion du consentement).

## 6. Design et Expérience Utilisateur (UX/UI)
- **Esthétique Premium** : Utilisation d'une palette de couleurs harmonieuse, mode sombre/clair.
- **Responsive Design** : Optimisation pour mobiles (priorité au scan) et tablettes/ordinateurs (priorité à la gestion).
- **Interactivité** : Animations fluides, transitions pour les badges obtenus.

## 7. Plan de Déploiement
- **Hébergement** : Render (prêt pour déploiement avec `render.yaml`).
- **CI/CD** : Script de déploiement automatique via `deploy.sh`.
