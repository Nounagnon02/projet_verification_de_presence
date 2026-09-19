import api from '../axios';

/**
 * Couche d'API pour les pages de rapports (/admin/reports…, /admin/ues,
 * /admin/filieres/{id}, /admin/presence/stats). Les exports (Excel/CSV/PDF)
 * gardent `responseType: 'blob'` et renvoient la réponse complète — et non
 * juste `r.data` comme les autres fonctions — pour que l'appelant puisse lire
 * l'en-tête Content-Disposition (nom de fichier choisi par le serveur).
 */

export function rapportSemestre(anneeId, signal) {
  return api.get(`/admin/reports/semester/${anneeId}`, { signal }).then((r) => r.data);
}

export function rapportFiliereStats(params, signal) {
  return api.get('/admin/reports/filiere-stats', { params, signal }).then((r) => r.data);
}

export function rapportComparaisonSemestres(params, signal) {
  return api.get('/admin/reports/semester-comparison', { params, signal }).then((r) => r.data);
}

export function rapportAnneeStats(signal) {
  return api.get('/admin/reports/annee-stats', { signal }).then((r) => r.data);
}

export function obtenirFiliere(id, signal) {
  return api.get(`/admin/filieres/${id}`, { signal }).then((r) => r.data);
}

export function rapportDepartement(id, signal) {
  return api.get(`/admin/reports/department/${id}`, { signal }).then((r) => r.data);
}

export function listerUes(params, signal) {
  return api.get('/admin/ues', { params, signal }).then((r) => r.data);
}

export function rapportFiltre(params, signal) {
  return api.get('/admin/reports/filtered', { params, signal }).then((r) => r.data);
}

export function rapportEtudiantsAbsents(params, signal) {
  return api.get('/admin/reports/etudiants-absents', { params, signal }).then((r) => r.data);
}

export function rapportPresenceStats(signal) {
  return api.get('/admin/presence/stats', { signal }).then((r) => r.data);
}

/** Rapport de filière en PDF. */
export function exporterRapportDepartementPdf(id) {
  return api.get(`/admin/reports/department/${id}`, { params: { format: 'pdf' }, responseType: 'blob' });
}

/** Liste des présences filtrée, en CSV. */
export function exporterPresencesCsv(params) {
  return api.get('/admin/reports/excel/export', { params, responseType: 'blob' });
}

/** Étudiants les plus absents, en CSV. */
export function exporterEtudiantsAbsentsCsv(params) {
  return api.get('/admin/reports/etudiants-absents', { params: { ...params, format: 'csv' }, responseType: 'blob' });
}
