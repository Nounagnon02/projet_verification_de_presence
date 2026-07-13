import { Platform } from 'react-native';

/**
 * URL de l'API backend Ngrok pour les tests.
 * À remplacer par la nouvelle URL si le tunnel est redémarré.
 */
const NGROK_API_URL = 'https://7a9a-156-0-212-164.ngrok-free.app';

/**
 * Détecte l'URL de l'API backend selon l'environnement.
 *
 * - Développement : utilise le tunnel Ngrok pour les tests mobiles
 * - Android émulateur : 10.0.2.2 pointe vers le host (localhost)
 * - iOS simulateur   : localhost fonctionne directement
 * - Production       : api.presence.uac.bj
 */
function getApiBaseUrl(): string {
  if (__DEV__) {
    // Émulateur Android : 10.0.2.2 pointe vers localhost
    if (Platform.OS === 'android') {
      return `http://10.0.2.2:8000/api`;
    }
    // Appareil physique ou simulateur iOS : utiliser le tunnel Ngrok
    return `${NGROK_API_URL}/api`;
  }
  return 'https://api.presence.uac.bj/api';
}

export const CONFIG = {
  /** URL de base de l'API (sans slash final) */
  API_URL: getApiBaseUrl(),

  /** Nom de l'application */
  APP_NAME: 'Présence UAC',

  /** Clé SecureStore pour le token Bearer */
  TOKEN_KEY: 'auth_token',

  /** Clé AsyncStorage pour le cache utilisateur */
  USER_KEY: 'auth_user',

  /** Timeout axios pour les requêtes de scan (ms) */
  SCAN_TIMEOUT: 15_000,

  /** Timeout axios standard (ms) */
  DEFAULT_TIMEOUT: 10_000,

  /** Précision GPS : HIGH pour une géolocalisation à ~10 mètres */
  GPS_HIGH_ACCURACY: true,

  /** Délai minimum entre deux scans QR consécutifs (ms) */
  QR_SCAN_COOLDOWN: 2_000,

  /** Âge maximum d'un scan_challenge (secondes) — doit matcher le backend */
  CHALLENGE_MAX_AGE_SEC: 60,

  /** Version de l'app */
  APP_VERSION: '1.0.0',
} as const;