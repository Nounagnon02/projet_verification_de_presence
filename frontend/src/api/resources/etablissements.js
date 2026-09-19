import api from '../axios';

/**
 * Couche d'API pour la gestion des établissements côté SUPER-admin
 * (/super-admin/etablissements…, /super-admin/dashboard). Distincte de toute
 * ressource « établissement » consultée depuis l'admin d'une seule faculté.
 */

export function listerEtablissements(signal) {
  return api.get('/super-admin/etablissements', { signal }).then((r) => r.data);
}

export function creerEtablissement(payload) {
  return api.post('/super-admin/etablissements', payload).then((r) => r.data);
}

export function recupererEtablissement(id, signal) {
  return api.get(`/super-admin/etablissements/${id}`, { signal }).then((r) => r.data);
}

export function recupererStatsEtablissement(id, signal) {
  return api.get(`/super-admin/etablissements/${id}/stats`, { signal }).then((r) => r.data);
}

export function modifierEtablissement(id, payload) {
  return api.put(`/super-admin/etablissements/${id}`, payload).then((r) => r.data);
}

export function supprimerEtablissement(id) {
  return api.delete(`/super-admin/etablissements/${id}`).then((r) => r.data);
}

export function renvoyerIdentifiantsEtablissement(id) {
  return api.post(`/super-admin/etablissements/${id}/resend-credentials`).then((r) => r.data);
}

export function importerEtablissementsCsv(formData) {
  return api.post('/super-admin/etablissements/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((r) => r.data);
}

/** Vue d'ensemble (KPIs + liste des facultés) du tableau de bord super-admin. */
export function recupererTableauDeBordSuperAdmin(signal) {
  return api.get('/super-admin/dashboard', { signal }).then((r) => r.data);
}
