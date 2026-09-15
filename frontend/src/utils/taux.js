/**
 * Présentation d'un taux de présence, partagée par les rapports.
 *
 * Un taux absent (null) signifie « personne n'était attendu » : il s'affiche
 * « — » et ne prend pas de couleur, plutôt qu'un 0 % rouge trompeur.
 */

/** Vert dès 80 %, orange dès 50 %, rouge en dessous ; aucune couleur sans taux. */
export const couleurTaux = (taux) => {
  if (taux === null || taux === undefined) return undefined;
  return taux >= 80 ? '#2E7D32' : taux >= 50 ? '#F57F17' : '#C62828';
};

/** « 16.4% », ou « — » quand il n'y a pas de taux. */
export const libelleTaux = (taux) => (taux === null || taux === undefined ? '—' : `${taux}%`);
