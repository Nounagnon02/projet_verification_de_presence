import api from '../axios';

/**
 * Couche d'API pour les écrans d'import assisté par IA (/admin/import…) :
 * suivi d'une analyse en cours, puis validation des cours et des créneaux
 * d'emploi du temps qu'elle a extraits, avant écriture définitive.
 *
 * Porte aussi la reconnaissance et la création de salles à la volée pendant
 * la validation d'un emploi du temps importé (/admin/salles/reconnaitre,
 * /admin/salles/depuis-nom) : ces deux appels ne servent qu'à ce parcours.
 *
 * Porte enfin les deux lectures de l'emploi du temps type et les deux imports
 * de fichier (PDF, CSV) dont WeeklySchedulePage a besoin. Elles relèveraient
 * naturellement de src/api/resources/emploiDuTemps.js, mais ce module est
 * porté par une autre migration en cours cette même session : on évite de le
 * toucher pour ne pas entrer en conflit avec elle.
 */

export function recupererStatutAnalyseIa(id, signal) {
  return api.get(`/admin/import/analysis-status/${id}`, { signal }).then((r) => r.data);
}

export function validerCoursImportes(payload) {
  return api.post('/admin/import/validate-courses', payload).then((r) => r.data);
}

export function reconnaitreSalles(payload, signal) {
  return api.post('/admin/salles/reconnaitre', payload, { signal }).then((r) => r.data);
}

export function creerSalleDepuisNom(payload) {
  return api.post('/admin/salles/depuis-nom', payload).then((r) => r.data);
}

export function verifierImportEmploiDuTemps(payload) {
  return api.post('/admin/import/schedule/verifier', payload).then((r) => r.data);
}

export function confirmerImportEmploiDuTemps(payload) {
  return api.post('/admin/import/schedule/confirmer', payload).then((r) => r.data);
}

/** Créneaux de l'emploi du temps type, pour la grille hebdomadaire. */
export function listerCreneauxEmploiDuTemps(params, signal) {
  return api.get('/admin/emploi-du-temps', { params, signal }).then((r) => r.data);
}

/** Conflits déjà enregistrés en base (créneaux et séances), pour un rapport à la demande. */
export function listerConflitsEmploiDuTemps(params, signal) {
  return api.get('/admin/emploi-du-temps/conflits', { params, signal }).then((r) => r.data);
}

export function importerEmploiDuTempsCsv(formData) {
  return api.post('/admin/import/csv/schedule', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((r) => r.data);
}

export function importerEmploiDuTempsPdf(formData) {
  return api.post('/admin/import/schedule', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((r) => r.data);
}

/** Modèle CSV vierge : réponse complète (et non r.data), pour lire l'en-tête Content-Disposition. */
export function telechargerModeleCsv(type) {
  return api.get(`/admin/import/csv/template/${type}`, { responseType: 'blob' });
}
