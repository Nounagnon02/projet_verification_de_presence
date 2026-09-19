import api from '../axios';

/**
 * Couche d'API pour le compte de la personne connectée : mot de passe, double
 * authentification (/admin/profile/2fa/…) et sessions actives (/admin/sessions…).
 */

export function modifierMotDePasse(payload) {
  return api.put('/admin/profile/password', payload).then((r) => r.data);
}

export function activerDeuxFacteurs() {
  return api.post('/admin/profile/2fa/enable').then((r) => r.data);
}

export function confirmerDeuxFacteurs(code) {
  return api.post('/admin/profile/2fa/confirm', { code }).then((r) => r.data);
}

export function desactiverDeuxFacteurs(currentPassword) {
  return api.post('/admin/profile/2fa/disable', { current_password: currentPassword }).then((r) => r.data);
}

export function listerSessions(signal) {
  return api.get('/admin/sessions', { signal }).then((r) => r.data);
}

export function revoquerAutresSessions() {
  return api.delete('/admin/sessions/others').then((r) => r.data);
}
