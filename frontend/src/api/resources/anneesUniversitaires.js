import api from '../axios';

/**
 * Couche d'API pour les années académiques de l'université, gérées par le
 * SUPER-admin (/super-admin/annees-academiques…). Distincte de la ressource
 * « années » lue par les pages d'admin de faculté (src/api/resources/reference.js,
 * /admin/annees-academiques), qui porte sur l'année choisie par un seul établissement.
 */

export function listerAnneesUniversitaires(signal) {
  return api.get('/super-admin/annees-academiques', { signal }).then((r) => r.data);
}

export function creerAnneeUniversitaire(payload) {
  return api.post('/super-admin/annees-academiques', payload).then((r) => r.data);
}

export function modifierAnneeUniversitaire(id, payload) {
  return api.put(`/super-admin/annees-academiques/${id}`, payload).then((r) => r.data);
}

export function definirAnneeEnCours(id) {
  return api.patch(`/super-admin/annees-academiques/${id}/en-cours`).then((r) => r.data);
}

export function supprimerAnneeUniversitaire(id) {
  return api.delete(`/super-admin/annees-academiques/${id}`).then((r) => r.data);
}
