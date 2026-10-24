// Chaque établissement n'a pas de logo la plupart du temps : plutôt qu'une
// icône générique répétée sur toutes les lignes (ce qui rendait la liste
// indiscernable d'une faculté à l'autre), on dérive un sceau — monogramme et
// teinte — depuis son code. Déterministe : le même code donne toujours le
// même sceau, sans appel réseau ni asset.
function hashCode(chaine) {
  let hash = 0;
  for (let i = 0; i < chaine.length; i += 1) {
    hash = (hash * 31 + chaine.charCodeAt(i)) >>> 0;
  }
  return hash;
}

export function monogrammeInstitution(code) {
  const nettoye = (code || '')
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '');
  return nettoye.slice(0, 2) || '--';
}

export function tonaliteInstitution(code) {
  const teinte = hashCode((code || '').toUpperCase()) % 360;
  return {
    fond: `hsl(${teinte}, 62%, 94%)`,
    texte: `hsl(${teinte}, 45%, 32%)`,
  };
}
