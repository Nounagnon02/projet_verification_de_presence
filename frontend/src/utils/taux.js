/**
 * Présentation d'un taux de présence, partagée par les rapports.
 *
 * Un taux absent (null) signifie « personne n'était attendu » : il s'affiche
 * « — » et ne prend pas de couleur, plutôt qu'un 0 % rouge trompeur.
 */

/**
 * Vert dès 80 %, orange dès 50 %, rouge en dessous ; aucune couleur sans taux.
 * Les variables suivent le thème sombre. L'ancien orange #F57F17 tombait à
 * 2,9:1 sur fond blanc, sous le seuil AA pour un taux écrit en texte.
 */
export const couleurTaux = (taux) => {
  if (taux === null || taux === undefined) return undefined;
  const statut = taux >= 80 ? 'succes' : taux >= 50 ? 'alerte' : 'erreur';
  return `rgb(var(--statut-${statut}))`;
};

/** Même seuils que couleurTaux, en classe Tailwind (pour un className). */
export const classeTaux = (taux) => {
  if (taux === null || taux === undefined) return 'text-on-surface-variant';
  return taux >= 80 ? 'text-statut-succes' : taux >= 50 ? 'text-statut-alerte' : 'text-statut-erreur';
};

/** « 16.4% », ou « — » quand il n'y a pas de taux. */
export const libelleTaux = (taux) => (taux === null || taux === undefined ? '—' : `${taux}%`);
