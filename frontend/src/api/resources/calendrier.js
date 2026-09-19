import api from '../axios';

/**
 * Calendrier de l'établissement (/admin/calendrier…) : périodes de cours par
 * semestre, et fermetures (vacances, examens…).
 */

export function calendrier(anneeId, signal) {
  return api.get('/admin/calendrier', { params: anneeId ? { annee_id: anneeId } : {}, signal }).then((r) => r.data);
}

export function enregistrerPeriode(payload) {
  return api.put('/admin/calendrier/periodes', payload).then((r) => r.data);
}

export function retirerPeriode(id) {
  return api.delete(`/admin/calendrier/periodes/${id}`).then((r) => r.data);
}

/** Même route pour l'aperçu (payload.apercu = true) et la déclaration réelle. */
export function declarerFermeture(payload) {
  return api.post('/admin/calendrier/fermetures', payload).then((r) => r.data);
}

export function retirerFermeture(id) {
  return api.delete(`/admin/calendrier/fermetures/${id}`).then((r) => r.data);
}
