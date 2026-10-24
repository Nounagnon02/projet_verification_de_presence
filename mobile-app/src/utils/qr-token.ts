const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
const JETON_NU = new RegExp(`^${UUID}$`, 'i');
const JETON_DANS_URL = new RegExp(`[?&]token=(${UUID})(?:[&#]|$)`, 'i');

/**
 * Jeton de séance contenu dans un QR Code, ou null s'il n'en contient pas.
 *
 * Le QR affiché par le web encode l'URL de la page de validation
 * (« …/attendance/validate?token=<uuid> »), pas le jeton seul. Poster cette URL
 * entière à /presence/scan était refusé (« token » doit être un UUID) : aucune
 * présence ne pouvait être validée depuis l'application. Le jeton nu reste
 * accepté. On lit l'URL par expression régulière plutôt que par `new URL` :
 * `searchParams` n'est pas fiable dans le moteur JavaScript de React Native.
 */
export function extraireJetonQr(contenu: string): string | null {
  const texte = contenu.trim();

  if (JETON_NU.test(texte)) {
    return texte.toLowerCase();
  }

  const dansUrl = JETON_DANS_URL.exec(texte);

  return dansUrl ? dansUrl[1].toLowerCase() : null;
}
