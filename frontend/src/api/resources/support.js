import api from '../axios';

/** Couche d'API pour les tickets de support (/admin/tickets…). */

export function creerTicket(payload) {
  return api.post('/admin/tickets', payload).then((r) => r.data);
}

export function obtenirTicket(id, signal) {
  return api.get(`/admin/tickets/${id}`, { signal }).then((r) => r.data);
}

export function repondreTicket(id, payload) {
  return api.post(`/admin/tickets/${id}/reply`, payload).then((r) => r.data);
}

export function changerStatutTicket(id, status) {
  return api.patch(`/admin/tickets/${id}/status`, { status }).then((r) => r.data);
}
