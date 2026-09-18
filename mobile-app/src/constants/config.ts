import Constants from 'expo-constants';
import { Platform } from 'react-native';

/**
 * URL de l'API backend Render (production), conservée comme valeur de repli.
 * En développement sur appareil physique, on vise le backend Render directement.
 */
const RENDER_API_URL = 'https://presence-uac-api.onrender.com';

/** Repli si app.json ne fournit rien : le comportement d'avant P3.6. */
const API_URL_REPLI = `${RENDER_API_URL}/api`;
const API_URL_DEV_ANDROID_REPLI = 'http://10.0.2.2:8000/api';

/**
 * P3.6 — l'URL de l'API était écrite en dur ici : changer de backend (recette,
 * autre établissement) imposait de modifier le code et de reconstruire l'APK.
 * Elle est désormais déclarée dans app.json, sous « expo.extra », et lue à
 * l'exécution via expo-constants. La valeur historique ne sert plus que de
 * repli : une clé absente, vide ou d'un autre type ne doit pas empêcher
 * l'application de démarrer.
 */
function lireUrlDeAppJson(cle: string): string | undefined {
  const extra = Constants.expoConfig?.extra as Record<string, unknown> | undefined;
  const valeur = extra?.[cle];
  return typeof valeur === 'string' && valeur.trim() !== '' ? valeur.trim() : undefined;
}

function getApiBaseUrl(): string {
  // Émulateur Android uniquement : 10.0.2.2 pointe vers le localhost de la
  // machine hôte, que « localhost » ne peut pas atteindre depuis l'émulateur.
  if (__DEV__ && Platform.OS === 'android') {
    return lireUrlDeAppJson('apiUrlDevAndroid') ?? API_URL_DEV_ANDROID_REPLI;
  }
  return lireUrlDeAppJson('apiUrl') ?? API_URL_REPLI;
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

  /**
   * Clé SecureStore de l'identifiant d'appareil retenu pour l'empreinte.
   * Distincte du jeton : la déconnexion ne doit pas changer l'identité de
   * l'appareil.
   */
  DEVICE_ID_KEY: 'device_id',

  /**
   * Timeout axios pour les requêtes de scan (ms).
   * Le scan est l'action la plus sensible : mieux vaut attendre que rejeter
   * une présence valide parce que le backend a mis du temps à répondre.
   */
  SCAN_TIMEOUT: 30_000,

  /**
   * Timeout axios standard (ms).
   * Le backend tourne sur le plan gratuit Render : il s'endort après 15 min
   * d'inactivité et met plusieurs dizaines de secondes à redémarrer. Un
   * timeout de 10 s faisait échouer toutes les requêtes au premier
   * lancement de la journée.
   */
  DEFAULT_TIMEOUT: 30_000,

  /** Précision GPS : HIGH pour une géolocalisation à ~10 mètres */
  GPS_HIGH_ACCURACY: true,

  /** Délai minimum entre deux scans QR consécutifs (ms) */
  QR_SCAN_COOLDOWN: 2_000,

  /** Version de l'app */
  APP_VERSION: '1.0.0',
} as const;