import api from '../axios';

/** Couche d'API pour les scans refusés par le serveur (/admin/alerts…). */

export function listerScansRefuses(params, signal) {
  return api.get('/admin/alerts', { params, signal }).then((r) => r.data);
}

export function enregistrerPresenceDepuisRefus(id, payload) {
  return api.post(`/admin/alerts/${id}/presence`, payload).then((r) => r.data);
}
