/**
 * Rôles en clair. Le Profil affichait la valeur technique (« super_admin »,
 * « faculte_admin »).
 */
export const LIBELLES_ROLE = {
  super_admin: 'Super administrateur',
  faculte_admin: "Administrateur d'établissement",
  enseignant: 'Enseignant',
  responsable: 'Responsable de promotion',
  etudiant: 'Étudiant',
};

export function libelleRole(role) {
  if (!role) return 'Administrateur';
  return LIBELLES_ROLE[role] ?? role;
}
