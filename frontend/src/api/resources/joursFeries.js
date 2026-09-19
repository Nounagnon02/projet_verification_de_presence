import api from '../axios';

/**
 * Couche d'API pour les jours fériés de l'université (/super-admin/jours-feries…),
 * communs à tous les établissements.
 */

export function listerJoursFeries(anneeId, signal) {
  return api.get('/super-admin/jours-feries', { params: { annee_id: anneeId }, signal }).then((r) => r.data);
}

/** Même endpoint pour l'aperçu (payload.apercu = true) et la déclaration effective. */
export function declarerJourFerie(payload) {
  return api.post('/super-admin/jours-feries', payload).then((r) => r.data);
}

export function supprimerJourFerie(id) {
  return api.delete(`/super-admin/jours-feries/${id}`).then((r) => r.data);
}
