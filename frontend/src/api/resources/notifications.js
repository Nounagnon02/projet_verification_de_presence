import api from '../axios';

/** Couche d'API pour les notifications du compte connecté (/admin/notifications…). */

export function listerNotifications(params, signal) {
  return api.get('/admin/notifications', { params, signal }).then((r) => r.data);
}

export function compterNotificationsNonLues(signal) {
  return api.get('/admin/notifications/unread-count', { signal }).then((r) => r.data);
}

export function marquerNotificationLue(id) {
  return api.post(`/admin/notifications/${id}/read`).then((r) => r.data);
}

export function marquerToutesNotificationsLues() {
  return api.post('/admin/notifications/read-all').then((r) => r.data);
}

export function supprimerNotification(id) {
  return api.delete(`/admin/notifications/${id}`).then((r) => r.data);
}
