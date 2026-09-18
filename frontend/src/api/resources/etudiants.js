import api from '../axios';

/**
 * Couche d'API pour la gestion administrative des étudiants
 * (/admin/students…). Distincte de src/api/etudiant.js, qui porte la
 * connexion de l'ÉTUDIANT lui-même sur la page de scan.
 *
 * Regroupe les appels ici, plutôt que dans la page, pour que StudentManagementPage
 * ne mélange plus la forme des requêtes HTTP avec l'état de l'écran — et pour
 * que ces fonctions servent de queryFn/mutationFn à TanStack Query.
 */

export function listerEtudiants(params, signal) {
  return api.get('/admin/students', { params, signal }).then((r) => r.data);
}

export function listerGroupes(filiereId, anneeId) {
  return api.get('/admin/groupes', { params: { filiere_id: filiereId, annee_id: anneeId } }).then((r) => r.data);
}

export function creerEtudiant(payload) {
  return api.post('/admin/students', payload).then((r) => r.data);
}

export function modifierEtudiant(id, payload) {
  return api.put(`/admin/students/${id}`, payload).then((r) => r.data);
}

export function modifierGroupesEtudiant(id, { td, tp }) {
  return api.put(`/admin/students/${id}/groupes`, { td: td || null, tp: tp || null }).then((r) => r.data);
}

export function supprimerEtudiant(id) {
  return api.delete(`/admin/students/${id}`).then((r) => r.data);
}

export function promouvoirEtudiants(payload) {
  return api.post('/admin/students/promote', payload).then((r) => r.data);
}

export function importerEtudiantsCsv(formData, onUploadProgress) {
  return api.post('/admin/import/students', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress,
  }).then((r) => r.data);
}
