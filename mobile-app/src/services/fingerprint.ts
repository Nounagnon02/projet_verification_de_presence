import { Platform } from 'react-native';
import * as Device from 'expo-device';
import * as Application from 'expo-application';
import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';
import { CONFIG } from '../constants/config';

let cachedFingerprint: string | null = null;
let cachedDeviceId: string | null = null;

/**
 * Identifiant propre à CET appareil.
 *
 * L'empreinte reposait sur Constants.installationId, qu'expo-constants a
 * supprimé : la valeur tombait toujours sur 'unknown-install', et il ne
 * restait que marque, modèle, version d'OS et build. Tous les téléphones d'un
 * même modèle produisaient la même empreinte : le serveur marquait leurs scans
 * « appareil partagé » et leur imposait un seul quota de scans à eux tous.
 *
 * Ordre de recherche :
 *   1. l'identifiant déjà retenu, conservé dans SecureStore — il ne change pas
 *      si la source plateforme devient momentanément indisponible ;
 *   2. Android : ANDROID_ID (propre à l'appareil, à l'utilisateur et à la clé
 *      de signature ; stable entre réinstallations) ;
 *      iOS : identifiant « vendor » (IDFV), qui peut valoir null juste après
 *      un redémarrage ;
 *   3. à défaut : un UUID aléatoire tiré une fois.
 *
 * Un échec de SecureStore n'empêche jamais le calcul : l'identifiant reste
 * alors en mémoire pour la session.
 */
async function getDeviceId(): Promise<string> {
  if (cachedDeviceId) return cachedDeviceId;

  try {
    const retenu = await SecureStore.getItemAsync(CONFIG.DEVICE_ID_KEY);
    if (retenu) {
      cachedDeviceId = retenu;
      return retenu;
    }
  } catch {
    // Lecture impossible : on recalcule depuis la plateforme.
  }

  const id = (await getPlatformDeviceId()) ?? `installation:${Crypto.randomUUID()}`;
  cachedDeviceId = id;

  try {
    await SecureStore.setItemAsync(CONFIG.DEVICE_ID_KEY, id);
  } catch {
    // Non persisté : l'identifiant vaut pour cette session seulement.
  }

  return id;
}

async function getPlatformDeviceId(): Promise<string | null> {
  try {
    if (Platform.OS === 'android') {
      const androidId = Application.getAndroidId();
      return androidId ? `android:${androidId}` : null;
    }
    if (Platform.OS === 'ios') {
      const idfv = await Application.getIosIdForVendorAsync();
      return idfv ? `ios:${idfv}` : null;
    }
  } catch {
    // Module natif absent ou en erreur : repli sur l'UUID d'installation.
  }
  return null;
}

/**
 * Empreinte de l'appareil envoyée au scan.
 *
 * Input haché (SHA256) :
 *   applicationId:identifiantAppareil:marque:modèle
 *
 * La version d'OS et le numéro de build n'y figurent plus : une mise à jour du
 * système ou de l'application changeait l'empreinte, et le même téléphone
 * apparaissait comme un nouvel appareil.
 *
 * Retourne les 32 premiers caractères du hash hexadécimal.
 * La valeur est mise en cache pour toute la durée de l'app.
 */
export async function getDeviceFingerprint(): Promise<string> {
  if (cachedFingerprint) return cachedFingerprint;

  const components = [
    Application.applicationId ?? 'unknown-app',
    await getDeviceId(),
    Device.brand ?? 'unknown-brand',
    Device.modelName ?? 'unknown-model',
  ];

  const input = components.join(':');
  const hash = await Crypto.digestStringAsync(
    Crypto.CryptoDigestAlgorithm.SHA256,
    input,
  );

  cachedFingerprint = hash.substring(0, 32);
  return cachedFingerprint;
}

/**
 * Le scan_challenge n'est plus calculé ici.
 *
 * Il l'était sous la forme sha256(fingerprint + ':' + APP_KEY), ce qui imposait
 * d'embarquer la clé de l'application dans le bundle — une clé décompilable
 * n'authentifie rien — et la valeur produite ne correspondait de toute façon
 * jamais à celle attendue par le serveur.
 *
 * Le défi est désormais émis par le serveur dans la réponse de
 * GET /presence/course-by-token/{token} et renvoyé tel quel au scan.
 * Voir src/hooks/useScan.ts.
 */

/**
 * Réinitialise les caches mémoire (utile pour les tests uniquement). Ce qui est
 * conservé dans SecureStore, lui, subsiste : c'est ce qui rend l'empreinte
 * stable d'un lancement à l'autre.
 */
export function resetFingerprintCache(): void {
  cachedFingerprint = null;
  cachedDeviceId = null;
}
