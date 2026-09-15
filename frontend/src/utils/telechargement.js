/**
 * Téléchargement des exports.
 *
 * Le serveur nomme chaque fichier d'après les filtres appliqués
 * (« historique_IM-L1_S1_du-2026-09-01_au-2026-09-14_export-2026-09-14.xlsx »).
 * Les écrans imposaient leur propre nom, du genre « presences_1726300000000.csv »,
 * qui ne disait rien du contenu.
 */

/** Nom annoncé par l'en-tête Content-Disposition, ou le repli donné. */
export function nomFichierServeur(entetes, repli) {
  const valeur = entetes?.['content-disposition'] || '';

  // La forme encodée (RFC 5987) d'abord : elle seule garde les accents.
  const encode = /filename\*=(?:UTF-8'')?([^;]+)/i.exec(valeur);
  if (encode) {
    try {
      return decodeURIComponent(encode[1].trim().replace(/^"|"$/g, '')) || repli;
    } catch {
      // Encodage illisible : on tente la forme simple.
    }
  }

  const simple = /filename="?([^";]+)"?/i.exec(valeur);
  return simple?.[1]?.trim() || repli;
}

/** Enregistre des données binaires sous le nom donné. */
export function enregistrer(donnees, nom) {
  const url = URL.createObjectURL(new Blob([donnees]));
  const lien = document.createElement('a');
  lien.href = url;
  lien.download = nom;
  document.body.appendChild(lien);
  lien.click();
  lien.remove();
  URL.revokeObjectURL(url);
}
