import { Platform } from 'react-native';

/**
 * URL de l'API backend Render (production).
 * En développement sur appareil physique, utiliser le backend Render directement.
 */
const RENDER_API_URL = 'https://presence-uac-api.onrender.com';

function getApiBaseUrl(): string {
  if (__DEV__) {
    // Émulateur Android uniquement : 10.0.2.2 pointe vers localhost
    if (Platform.OS === 'android') {
      return 'http://10.0.2.2:8000/api';
    }
    // Appareil physique iOS ou Android réel : utiliser le backend Render
    return `${RENDER_API_URL}/api`;
  }
  return `${RENDER_API_URL}/api`;
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