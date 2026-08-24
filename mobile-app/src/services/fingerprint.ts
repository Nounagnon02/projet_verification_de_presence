import * as Device from 'expo-device';
import * as Application from 'expo-application';
import * as Crypto from 'expo-crypto';
import Constants from 'expo-constants';

let cachedFingerprint: string | null = null;

/**
 * Génère une empreinte d'appareil unique basée sur les caractéristiques
 * matérielles et logicielles de l'appareil.
 *
 * Input haché (SHA256) :
 *   installationId:appId:brand:modelName:osVersion:nativeBuildVersion
 *
 * Retourne les 32 premiers caractères du hash hexadécimal.
 * La valeur est mise en cache pour toute la durée de l'app.
 */
export async function getDeviceFingerprint(): Promise<string> {
  if (cachedFingerprint) return cachedFingerprint;

  const components = [
    Constants.installationId ?? 'unknown-install',
    Application.applicationId ?? 'unknown-app',
    Device.brand ?? 'unknown-brand',
    Device.modelName ?? 'unknown-model',
    Device.osVersion ?? 'unknown-os',
    Application.nativeBuildVersion ?? 'unknown-build',
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
 * Réinitialise le cache du fingerprint (utile pour les tests uniquement).
 */
export function resetFingerprintCache(): void {
  cachedFingerprint = null;
}