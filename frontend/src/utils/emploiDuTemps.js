/** Jours de la semaine, indexés comme jour_semaine (1 = lundi … 7 = dimanche). */
export const JOURS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

/** « 08:30 » -> 510 minutes. */
export const enMinutes = (heure) => {
  const [h, m] = String(heure || '0:0').split(':');
  return (Number(h) || 0) * 60 + (Number(m) || 0);
};

/** 510 -> « 08:30 ». */
export const enHeure = (minutes) => {
  const m = Math.max(0, Math.min(24 * 60 - 1, Math.round(minutes)));
  return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
};

/**
 * Place les créneaux d'un même jour en colonnes. Ceux qui se chevauchent —
 * deux groupes de TD à la même heure, ou un conflit — se rangent côte à côte
 * au lieu de se recouvrir, ce qui cachait l'un des deux.
 *
 * @param {Array<{debut: number, fin: number}>} creneaux  bornes en minutes
 * @returns {Array<object>} chaque créneau, avec sa colonne (voie) et le nombre de colonnes de son paquet (voies)
 */
export function disposer(creneaux) {
  const tries = [...creneaux].sort((a, b) => a.debut - b.debut || a.fin - b.fin);
  const resultat = [];
  let paquet = [];
  let finPaquet = -1;

  const ranger = () => {
    const voies = [];
    const places = paquet.map((c) => {
      let voie = voies.findIndex((fin) => fin <= c.debut);
      if (voie === -1) {
        voie = voies.length;
        voies.push(c.fin);
      } else {
        voies[voie] = c.fin;
      }
      return { ...c, voie };
    });
    resultat.push(...places.map((c) => ({ ...c, voies: voies.length })));
  };

  for (const c of tries) {
    if (paquet.length && c.debut >= finPaquet) {
      ranger();
      paquet = [];
      finPaquet = -1;
    }
    paquet.push(c);
    finPaquet = Math.max(finPaquet, c.fin);
  }

  if (paquet.length) ranger();

  return resultat;
}
