import api from '../axios';

/**
 * Maquette pédagogique : UE et EC (/admin/ues, /admin/ecs…). La lecture des
 * filières et des EC est déjà couverte par reference.js (listerFilieres,
 * listerEcs) ; la liste des UE, elle, n'a pas d'autre foyer.
 */

export function listerUes(params, signal) {
  return api.get('/admin/ues', { params, signal }).then((r) => r.data);
}

export function creerUe(payload) {
  return api.post('/admin/ues', payload).then((r) => r.data);
}

export function modifierUe(id, payload) {
  return api.put(`/admin/ues/${id}`, payload).then((r) => r.data);
}

export function supprimerUe(id) {
  return api.delete(`/admin/ues/${id}`).then((r) => r.data);
}

export function creerEc(payload) {
  return api.post('/admin/ecs', payload).then((r) => r.data);
}

export function modifierEc(id, payload) {
  return api.put(`/admin/ecs/${id}`, payload).then((r) => r.data);
}

export function supprimerEc(id) {
  return api.delete(`/admin/ecs/${id}`).then((r) => r.data);
}

/** Import PDF : analyse par IA, validée ensuite ligne par ligne. */
export function importerMaquettePdf(formData) {
  return api.post('/admin/import/courses', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((r) => r.data);
}

/** Import CSV : appliqué directement, sans étape de validation. */
export function importerMaquetteCsv(formData) {
  return api.post('/admin/import/csv/courses', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((r) => r.data);
}
