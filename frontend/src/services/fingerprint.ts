import FingerprintJS from '@fingerprintjs/fingerprintjs';

/**
 * Service pour la génération d'empreintes digitales (device fingerprinting)
 * Utilise @fingerprintjs/fingerprintjs v4 (fingerprintjs2 est la v2, v4 est la version moderne)
 *
 * Utilisé pour l'anti-fraude : détection de multi-appareils, détection d'usurpation,
 * corrélation scan QR + device fingerprint pour validation de présence.
 */

let fpPromise: Promise<FingerprintJS.Agent> | null = null;

/**
 * Initialise l'agent FingerprintJS (singleton)
 * Doit être appelé une seule fois au démarrage de l'application
 */
export function initFingerprint(): Promise<FingerprintJS.Agent> {
  if (!fpPromise) {
    fpPromise = FingerprintJS.load({
      // Options de configuration
      cache: true, // Mettre en cache le résultat pour la session
      // Note: En production, on peut configurer un endpoint pour envoyer le visitorId
      // à un serveur pour déduplication côté serveur
    });
  }
  return fpPromise;
}

/**
 * Récupère l'identifiant unique du visiteur (visitorId)
 * Le visitorId est stable pour un même navigateur/appareil
 *
 * @returns Promise<string> - Identifiant unique du visiteur (ex: "abc123def456")
 */
export async function getVisitorId(): Promise<string> {
  const fp = await initFingerprint();
  const result = await fp.get();
  return result.visitorId;
}

/**
 * Récupère le résultat complet du fingerprinting
 * Inclut le visitorId, la confiance (confidence), et les composants bruts
 *
 * @returns Promise<FingerprintJS.GetResult> - Résultat complet
 */
export async function getFullFingerprint(): Promise<FingerprintJS.GetResult> {
  const fp = await initFingerprint();
  return fp.get();
}

/**
 * Composants du fingerprint utilisés pour l'anti-fraude
 * Ces composants peuvent être envoyés au backend pour analyse de risque
 */
export interface FingerprintComponents {
  visitorId: string;
  confidence: FingerprintJS.Confidence;
  components: {
    userAgent: string;
    language: string;
    colorDepth: number;
    screenResolution: string;
    timezone: string;
    platform: string;
    touchSupport: number;
    hardwareConcurrency: number;
    deviceMemory: number;
    canvas: string;
    webgl: string;
    webglVendorAndRenderer: string;
    audio: string;
    fonts: string[];
    // Composants supplémentaires disponibles selon la config
    [key: string]: unknown;
  };
}

/**
 * Extrait les composants pertinents pour l'anti-fraude
 * Envoie ces données au backend lors du scan QR pour corrélation
 */
export async function getAntiFraudFingerprint(): Promise<FingerprintComponents> {
  const result = await getFullFingerprint();

  return {
    visitorId: result.visitorId,
    confidence: result.confidence,
    components: result.components as FingerprintComponents['components'],
  };
}

/**
 * Compare deux visitorIds pour détecter un changement d'appareil
 * Retourne true si c'est probablement le même appareil (seuil de confiance)
 */
export function isSameDevice(visitorId1: string, visitorId2: string, threshold: number = 0.8): boolean {
  return visitorId1 === visitorId2;
}

/**
 * Génère un token de défi pour le scan QR
 * Combine visitorId + timestamp + nonce pour créer un token à usage unique
 * Ce token est envoyé avec le scan QR pour vérifier que le scan vient bien
 * de l'appareil enregistré de l'étudiant
 */
export async function generateScanChallenge(): Promise<{
  challenge: string;
  visitorId: string;
  timestamp: number;
}> {
  const visitorId = await getVisitorId();
  const timestamp = Date.now();
  const nonce = crypto.randomUUID().slice(0, 8);

  // Challenge = hash(visitorId + timestamp + nonce)
  // Côté backend, on vérifie que le visitorId correspond à l'étudiant
  // et que le timestamp est récent (< 60s)
  const challenge = btoa(`${visitorId}:${timestamp}:${nonce}`);

  return { challenge, visitorId, timestamp };
}

/**
 * Vérifie un challenge de scan côté client (optionnel, vérification côté serveur recommandée)
 */
export function verifyScanChallenge(
  challenge: string,
  expectedVisitorId: string,
  maxAgeMs: number = 60000
): { valid: boolean; reason?: string } {
  try {
    const decoded = atob(challenge);
    const [visitorId, timestampStr, nonce] = decoded.split(':');
    const timestamp = parseInt(timestampStr, 10);

    if (isNaN(timestamp)) {
      return { valid: false, reason: 'Invalid timestamp' };
    }

    const age = Date.now() - timestamp;
    if (age > maxAgeMs) {
      return { valid: false, reason: 'Challenge expired' };
    }

    if (visitorId !== expectedVisitorId) {
      return { valid: false, reason: 'Device mismatch' };
    }

    return { valid: true };
  } catch {
    return { valid: false, reason: 'Invalid challenge format' };
  }
}

export default {
  initFingerprint,
  getVisitorId,
  getFullFingerprint,
  getAntiFraudFingerprint,
  generateScanChallenge,
  verifyScanChallenge,
  isSameDevice,
};