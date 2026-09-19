import api from '../axios';

/** Couche d'API pour la gestion des salles (/admin/salles…). */

export function listerSalles(signal) {
  return api.get('/admin/salles', { signal }).then((r) => r.data);
}

export function creerSalle(payload) {
  return api.post('/admin/salles', payload).then((r) => r.data);
}

export function modifierSalle(id, payload) {
  return api.put(`/admin/salles/${id}`, payload).then((r) => r.data);
}

export function supprimerSalle(id) {
  return api.delete(`/admin/salles/${id}`).then((r) => r.data);
}

/**
 * Utilisateur connecté, pour préremplir l'établissement du formulaire de
 * salle. N'est pas une donnée de salle, mais n'a pas d'autre foyer : un seul
 * écran la lit, et uniquement pour cet usage.
 */
export function utilisateurConnecte() {
  return api.get('/user').then((r) => r.data);
}
