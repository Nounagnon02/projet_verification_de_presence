import api from '../axios';

/** Couche d'API pour le profil du compte connecté (/admin/profile). */

export function obtenirProfil(signal) {
  return api.get('/admin/profile', { signal }).then((r) => r.data);
}

export function modifierProfil(payload) {
  return api.put('/admin/profile', payload).then((r) => r.data);
}
