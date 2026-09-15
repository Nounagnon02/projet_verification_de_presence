/**
 * Types de séance et volumes d'un EC par type.
 *
 * Un EC n'avait qu'un volume : une séance de TD consommait les heures du cours
 * magistral. Les maquettes de l'UAC donnent les heures par type, et regroupent
 * parfois TP et TD dans une seule colonne — d'où la réserve TP/TD, où les TD et
 * les TP puisent une fois leur propre volume épuisé.
 */

export const TYPES_SEANCE = [
  { value: 'cm', label: 'Cours magistral (CM)' },
  { value: 'td', label: 'Travaux dirigés (TD)' },
  { value: 'tp', label: 'Travaux pratiques (TP)' },
  { value: 'evaluation', label: 'Évaluation' },
];

export const VOLUMES = [
  ['volume_cm', 'CM'],
  ['volume_td', 'TD'],
  ['volume_tp', 'TP'],
  ['volume_td_tp', 'TP/TD'],
];

/**
 * Heures restantes d'un EC pour un type de séance ; null quand rien ne borne
 * la séance (une évaluation ne consomme aucun volume). Un EC pas encore
 * ventilé n'a que son total.
 */
export function restantesPour(ec, type) {
  if (!ec || type === 'evaluation') return null;
  const parType = ec.heures_restantes_par_type;
  return Number(parType ? (parType[type] ?? 0) : (ec.heures_restantes ?? 0));
}

/** « CM 10h · TP/TD 15h = 25h », ou le total d'un EC encore à ventiler. */
export function libelleVolumes(ec) {
  if (!ec) return '';
  if (ec.volume_a_ventiler) return `${ec.volume_horaire ?? 0}h — à ventiler entre CM, TD et TP`;

  const parties = VOLUMES.filter(([champ]) => Number(ec[champ]) > 0).map(([champ, libelle]) => `${libelle} ${ec[champ]}h`);
  return parties.length > 0 ? `${parties.join(' · ')} = ${ec.volume_horaire}h` : `${ec.volume_horaire ?? 0}h`;
}
