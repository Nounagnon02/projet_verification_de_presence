import FingerprintJS, { type Agent, type Confidence, type GetResult } from '@fingerprintjs/fingerprintjs';

/**
 * Service pour la génération d'empreintes digitales (device fingerprinting)
 * Utilise @fingerprintjs/fingerprintjs v4 (fingerprintjs2 est la v2, v4 est la version moderne)
 *
 * Utilisé pour l'anti-fraude : détection de multi-appareils, détection d'usurpation,
 * corrélation scan QR + device fingerprint pour validation de présence.
 */

let fpPromise: Promise<Agent> | null = null;

/**
 * Initialise l'agent FingerprintJS (singleton)
 * Doit être appelé une seule fois au démarrage de l'application
 */
export function initFingerprint(): Promise<Agent> {
  if (!fpPromise) {
    // Pas d'option « cache » : elle n'existe pas dans LoadOptions (v5) et était
    // ignorée sans erreur. La mise en cache, c'est ce singleton.
    fpPromise = FingerprintJS.load();
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
 * @returns Promise<GetResult> - Résultat complet
 */
export async function getFullFingerprint(): Promise<GetResult> {
  const fp = await initFingerprint();
  return fp.get();
}

/**
 * Composants du fingerprint utilisés pour l'anti-fraude
 * Ces composants peuvent être envoyés au backend pour analyse de risque
 */
export interface FingerprintComponents {
  visitorId: string;
  confidence: Confidence;
  // Le type réel de la bibliothèque. Il était décrit à la main (userAgent,
  // webgl, webglVendorAndRenderer... en chaînes) : des champs qui n'existent pas
  // dans la v5, masqués par un « as » que rien ne vérifiait — personne ne les
  // lit, ce qui a empêché de le voir.
  components: GetResult['components'];
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
    components: result.components,
  };
}

/**
 * Compare deux visitorIds pour détecter un changement d'appareil : égalité
 * stricte. Un paramètre « threshold » (seuil de confiance) était accepté sans
 * jamais être lu.
 */
export function isSameDevice(visitorId1: string, visitorId2: string): boolean {
  return visitorId1 === visitorId2;
}

/**
 * Le scan_challenge n'est PLUS calculé ici.
 *
 * Il l'a été un temps, sous la forme sha256(visitorId + ':' + APP_KEY), ce qui
 * supposait de publier la clé de l'application dans le bundle : un secret servi
 * à tous les navigateurs n'authentifie rien, et la valeur obtenue ne
 * correspondait de toute façon jamais à celle attendue par le serveur.
 *
 * Le défi est désormais émis par le serveur dans la réponse de
 * GET /presence/course-by-token/{token} ; le client le renvoie tel quel.
 * Ce module ne fournit plus que l'empreinte d'appareil, qui sert à la détection
 * d'appareil partagé côté serveur.
 */

export default {
  initFingerprint,
  getVisitorId,
  getFullFingerprint,
  getAntiFraudFingerprint,
  isSameDevice,
};