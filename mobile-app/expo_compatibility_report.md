# Rapport de Compatibilité Expo — Présence UAC

**Date :** 2026-07-12
**Projet :** mobile-app (Présence UAC)

---

## 1. Cause du problème

| Métrique | Valeur |
|----------|--------|
| Expo SDK | **57.0.4** |
| React Native | **0.86.0** |
| Expo Go requis | **57.x** |
| Android min SDK | **API 24 (Android 7.0)** |

**Problème :** Le téléphone de l'utilisateur ne peut pas mettre à jour Expo Go depuis le Play Store (Android trop ancien pour la dernière version d'Expo Go). Le projet utilise Expo SDK 57 qui nécessite Expo Go 57.x, lequel n'est pas disponible sur l'Android du téléphone.

---

## 2. Solution choisie

### Solution B : Development Build via EAS

Un **Development Build** est une application Android standalone qui contient le moteur d'Expo compilé en natif. Contrairement à Expo Go :

- ✅ **Ne nécessite pas Expo Go** — l'app s'installe directement sur le téléphone
- ✅ **Compatible avec n'importe quelle version d'Android** supportée par React Native 0.86
- ✅ **Tous les modules natifs fonctionnent** (caméra, GPS, WiFi, SecureStore)
- ✅ **Mise à jour du code instantanée** via `npx expo start` + connexion au serveur de dev

### Pourquoi pas la Solution A (Downgrade) ?

Un downgrade d'Expo SDK casserait :
- `expo-router` (nécessite SDK 57)
- `react-native-reanimated` 4.5.0
- `nativewind` v4
- Les dépendances `~57.0.x` toutes liées au SDK 57

### Pourquoi pas la Solution C (Build direct) ?

Aucun Android SDK / Gradle / ADB n'est installé sur la machine de développement. L'installation nécessiterait ~10 Go de téléchargement.

---

## 3. Modifications effectuées

### Fichiers modifiés

| Fichier | Modification |
|---------|-------------|
| `package.json` | Ajout de `expo-dev-client` en devDependencies, ajout des scripts `dev:android` et `build:apk`, ajout de `react-native-svg` et `react-native-worklets` (peer deps), correction de `@react-native-async-storage/async-storage` vers 2.2.0, suppression de `eas-cli` des dépendances |
| `app.json` | Ajout du plugin `expo-dev-client` |
| `eas.json` | Ajout de la config `development` avec `buildType: apk` et `distribution: internal` |

### Paquets installés / modifiés

| Paquet | Version | Action |
|--------|---------|--------|
| `expo-dev-client` | ~57.0.0 | Installé |
| `react-native-svg` | 15.15.4 | Installé (peer dep manquante) |
| `react-native-worklets` | 0.10.0 | Installé (peer dep manquante) |
| `@react-native-async-storage/async-storage` | 2.2.0 | Downgradé (compatibilité SDK 57) |
| `eas-cli` | — | Déplacé hors du projet (installation globale) |

### Commandes exécutées

```bash
# 1. Installation des outils
npm install expo-dev-client@~57.0.0

# 2. Correction des dépendances
npx expo install @react-native-async-storage/async-storage@2.2.0
npx expo install react-native-svg react-native-worklets

# 3. Nettoyage et réinstallation
rm -rf node_modules .expo
npm cache clean --force
npm install

# 4. Vérifications
npx expo-doctor@latest        # 20/20 checks pass ✅
npx tsc --noEmit               # Pas d'erreurs ✅
npx expo export --platform android  # Bundle 5.1MB généré ✅
```

---

## 4. Procédure pour lancer l'application sur le téléphone

### Prérequis

1. **Compte Expo** (gratuit) : [expo.dev/signup](https://expo.dev/signup)
2. **Téléphone Android** avec débogage USB activé (optionnel) ou fichier APK

### Option A : Development Build (recommandé pour le développement)

```bash
# 1. Se connecter à Expo
npx eas login

# 2. Créer le projet sur EAS (première fois seulement)
npx eas project:init

# 3. Builder le Development Build APK
npm run dev:android

# 4. Télécharger l'APK depuis le lien fourni par EAS
#    (ou scanner le QR code dans le terminal)

# 5. Installer l'APK sur le téléphone

# 6. Lancer le serveur de développement
npx expo start

# 7. Ouvrir l'application installée sur le téléphone
#    Elle se connectera automatiquement au serveur de dev
```

### Option B : APK standalone (plus simple)

```bash
# 1. Se connecter à Expo
npx eas login

# 2. Builder un APK standalone
npm run build:apk

# 3. Télécharger l'APK depuis le lien fourni par EAS

# 4. Installer l'APK sur le téléphone
#    L'application fonctionne de manière autonome
```

### Option C : Connexion WiFi directe (si le téléphone est sur le même réseau)

```bash
# 1. Le serveur de développement affiche un QR code
npx expo start

# 2. Scanner le QR code avec l'app "Expo" installée
#    (si Expo Go ne fonctionne pas, utiliser le Dev Build)
```

---

## 5. Notes complémentaires

- **URL de l'API** : Configurée dans `src/constants/config.ts` (Ngrok tunnel actif : `https://7a9a-156-0-212-164.ngrok-free.app`)
- **Mise à jour FRONTEND_URL** : Le fichier `.env` du backend contient l'URL du frontend Ngrok — à mettre à jour si le tunnel est redémarré
- **Volume** : Le bundle Android fait 5.1 MB (taille normale)
- **NativeWind** : Configuré avec `presets: [require('nativewind/preset')]` dans `tailwind.config.js`
