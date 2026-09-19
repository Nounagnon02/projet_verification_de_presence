import api from '../axios';

/**
 * Créneaux de l'emploi du temps type (/admin/emploi-du-temps…), et les
 * groupes de TD/TP qu'un créneau ou un événement peut viser pour un EC et un
 * type de séance donnés. Domaine distinct de src/api/resources/calendrier.js
 * (périodes et fermetures) et de evenements.js (séances datées) : ici, le
 * gabarit hebdomadaire.
 */

export function creerCreneau(payload) {
  return api.post('/admin/emploi-du-temps', payload).then((r) => r.data);
}

export function modifierCreneau(id, payload) {
  return api.put(`/admin/emploi-du-temps/${id}`, payload).then((r) => r.data);
}

export function supprimerCreneau(id) {
  return api.delete(`/admin/emploi-du-temps/${id}`).then((r) => r.data);
}

/** Groupes de TD/TP d'un EC pour un type de séance (formulaire de créneau et d'événement). */
export function listerGroupesPourEc(ecId, type, signal) {
  return api.get('/admin/groupes', { params: { ec_id: String(ecId), type }, signal }).then((r) => r.data);
}
