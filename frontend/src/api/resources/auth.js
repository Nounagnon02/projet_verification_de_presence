import api from '../axios';

/**
 * Couche d'API pour l'authentification hors session (mot de passe oublié).
 * Routes publiques, hors du groupe /admin.
 */

export function demanderReinitialisationMotDePasse(email) {
  return api.post('/forgot-password', { email }).then((r) => r.data);
}

export function reinitialiserMotDePasse(payload) {
  return api.post('/reset-password', payload).then((r) => r.data);
}
