import api from '../axios';

/**
 * Écritures sur les filières et leurs programmes (/admin/filieres,
 * /admin/programmes…). La lecture des filières est déjà couverte par
 * listerFilieres() de reference.js ; celle des programmes n'a pas d'autre
 * foyer, elle vit ici avec leurs écritures.
 */

export function listerProgrammes(signal) {
  return api.get('/admin/programmes', { signal }).then((r) => r.data);
}

export function creerFiliere(payload) {
  return api.post('/admin/filieres', payload).then((r) => r.data);
}

export function modifierFiliere(id, payload) {
  return api.put(`/admin/filieres/${id}`, payload).then((r) => r.data);
}

export function supprimerFiliere(id) {
  return api.delete(`/admin/filieres/${id}`).then((r) => r.data);
}

export function renommerProgramme(id, payload) {
  return api.put(`/admin/programmes/${id}`, payload).then((r) => r.data);
}
