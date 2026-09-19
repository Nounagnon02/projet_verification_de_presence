import api from '../axios';

/**
 * Couche d'API pour les inscriptions d'un étudiant à ses ECs
 * (/admin/students/{id}/ecs…) et pour les groupes de TD/TP d'une promotion
 * (/admin/groupes…).
 *
 * La lecture des groupes d'une promotion (GET /admin/groupes) est déjà portée
 * par listerGroupes dans etudiants.js — réutilisée ici plutôt que dupliquée.
 */

export function listerEcsInscrits(studentId, signal) {
  return api.get(`/admin/students/${studentId}/ecs`, { signal }).then((r) => r.data);
}

export function listerEcsDisponibles(studentId, signal) {
  return api.get(`/admin/students/${studentId}/ecs-available`, { signal }).then((r) => r.data);
}

export function inscrireEc(studentId, ecIds, signal) {
  return api.post(`/admin/students/${studentId}/ecs`, { ec_ids: ecIds }, { signal }).then((r) => r.data);
}

export function desinscrireEc(studentId, ecId, signal) {
  return api.delete(`/admin/students/${studentId}/ecs/${ecId}`, { signal }).then((r) => r.data);
}

export function reinitialiserInscriptions(studentId, signal) {
  return api.post(`/admin/students/${studentId}/ecs/reset`, {}, { signal }).then((r) => r.data);
}

export function repartirGroupes(payload) {
  return api.post('/admin/groupes/repartir', payload).then((r) => r.data);
}

export function creerGroupe(payload) {
  return api.post('/admin/groupes', payload).then((r) => r.data);
}

export function supprimerGroupe(id) {
  return api.delete(`/admin/groupes/${id}`).then((r) => r.data);
}
