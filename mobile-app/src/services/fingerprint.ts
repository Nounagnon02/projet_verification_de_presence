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
 * Génère un scan_challenge pour l'anti-fraude.
 *
 * Format du payload (avant base64) :
 *   fingerprintPrefix16:timestamp:nonceHex8
 *
 * Le backend vérifiera :
 *   - Que le préfixe du fingerprint correspond
 *   - Que le timestamp a moins de 60 secondes
 */
export async function createScanChallenge(): Promise<string> {
  const fp = await getDeviceFingerprint();
  const prefix = fp.substring(0, 16);
  const timestamp = Date.now();
  const nonce = Array.from({ length: 8 }, () =>
    Math.floor(Math.random() * 16).toString(16),
  ).join('');

  const payload = `${prefix}:${timestamp}:${nonce}`;
  // Hermes (React Native 0.86+) supporte btoa/atob
  return btoa(payload);
}

/**
 * Vérifie la validité locale d'un scan_challenge :
 * 1. Peut être décodé en base64
 * 2. Contient prefix:timestamp[:nonce]
 * 3. Le timestamp est dans la fenêtre maxAgeSec
 * 4. Le préfixe du fingerprint correspond à l'appareil actuel
 */
export async function verifyScanChallenge(
  challenge: string,
  maxAgeSec: number = 60,
): Promise<{ valid: boolean; ageSec: number }> {
  try {
    const decoded = atob(challenge);
    const [prefix, timestampStr] = decoded.split(':');
    if (!prefix || !timestampStr) {
      return { valid: false, ageSec: -1 };
    }

    const ageSec = (Date.now() - parseInt(timestampStr, 10)) / 1000;
    if (ageSec < 0 || ageSec > maxAgeSec) {
      return { valid: false, ageSec };
    }

    const fp = await getDeviceFingerprint();
    return { valid: fp.startsWith(prefix), ageSec };
  } catch {
    return { valid: false, ageSec: -1 };
  }
}

/**
 * Réinitialise le cache du fingerprint (utile pour les tests uniquement).
 */
export function resetFingerprintCache(): void {
  cachedFingerprint = null;
}