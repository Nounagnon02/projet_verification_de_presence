/**
 * Calculs d'heures partagés par les sélecteurs d'horaires.
 *
 * Le pas de 15 minutes couvre les horaires réels : dans les séances
 * enregistrées, les heures tombent presque toutes sur :00 ou :15.
 */

export const PAS_MINUTES = 15;
export const DEBUT_JOURNEE = 6 * 60;
export const FIN_JOURNEE = 22 * 60;

/** « 08:15 » ou « 08:15:00 » -> 495. Chaîne vide ou invalide -> null. */
export function enMinutes(heure) {
  const m = /^(\d{1,2}):(\d{2})/.exec(String(heure ?? ''));
  return m ? Number(m[1]) * 60 + Number(m[2]) : null;
}

/** 495 -> « 08:15 ». */
export function enHeure(minutes) {
  const deux = (n) => String(n).padStart(2, '0');
  return `${deux(Math.floor(minutes / 60))}:${deux(minutes % 60)}`;
}

/**
 * Heure de fin à retenir quand l'heure de début change.
 *
 * On conserve la durée déjà choisie : déplacer un cours de deux heures de 8 h à
 * 14 h donne 14 h – 16 h, sans avoir à refaire la fin. Le résultat reste dans
 * la limite de durée et dans la journée ; faute de durée exploitable, deux
 * heures par défaut.
 */
export function finApresNouveauDebut({ ancienDebut, ancienneFin, nouveauDebut, limiteMinutes = null }) {
  const debut = enMinutes(nouveauDebut);
  if (debut === null) return ancienneFin;

  const a = enMinutes(ancienDebut);
  const f = enMinutes(ancienneFin);
  let duree = a !== null && f !== null && f > a ? f - a : 120;

  if (limiteMinutes !== null) duree = Math.min(duree, limiteMinutes);
  duree = Math.max(duree, PAS_MINUTES);

  return enHeure(Math.min(debut + duree, FIN_JOURNEE));
}
