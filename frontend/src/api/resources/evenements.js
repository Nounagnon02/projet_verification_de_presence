import api from '../axios';

/** Couche d'API pour les séances de cours (/admin/evenements…). */

export function listerEvenements(params, signal) {
  return api.get('/admin/evenements', { params, signal }).then((r) => r.data);
}

export function creerEvenement(payload) {
  return api.post('/admin/evenements', payload).then((r) => r.data);
}

export function modifierEvenement(id, payload) {
  return api.put(`/admin/evenements/${id}`, payload).then((r) => r.data);
}

export function supprimerEvenement(id) {
  return api.delete(`/admin/evenements/${id}`).then((r) => r.data);
}

export function genererQrCode(eventId) {
  return api.get(`/admin/qrcode/${eventId}/generate`).then((r) => r.data);
}

/** Créneaux de l'emploi du temps pour un EC et une date, pour préremplir le formulaire. */
export function creneauxEmploiDuTemps(ecId, date, signal) {
  return api.get('/admin/evenements/creneaux-emploi-du-temps', { params: { ec_id: ecId, date }, signal }).then((r) => r.data);
}
