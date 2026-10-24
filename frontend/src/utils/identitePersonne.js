// Avatar généré pour un compte sans photo : initiales du nom et teinte
// dérivée de l'email (stable même si la personne change de nom), sans asset
// ni appel réseau. Logique volontairement distincte de identiteInstitution
// (initiales de deux mots, pas les deux premiers caractères d'un code).

function hashCode(chaine) {
  let hash = 0;
  for (let i = 0; i < chaine.length; i += 1) {
    hash = (hash * 31 + chaine.charCodeAt(i)) >>> 0;
  }
  return hash;
}

export function initialesPersonne(nom) {
  const mots = (nom || '').trim().split(/\s+/).filter(Boolean);
  if (mots.length === 0) return '?';
  if (mots.length === 1) return mots[0].slice(0, 2).toUpperCase();
  return (mots[0][0] + mots[mots.length - 1][0]).toUpperCase();
}

export function tonalitePersonne(cle) {
  const teinte = hashCode((cle || '').toLowerCase()) % 360;
  return {
    fond: `hsl(${teinte}, 62%, 94%)`,
    texte: `hsl(${teinte}, 45%, 32%)`,
  };
}
