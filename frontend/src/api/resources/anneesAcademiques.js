import api from '../axios';

/**
 * Écritures sur l'année académique de l'établissement (/admin/annees-academiques…).
 * La lecture (liste) est déjà couverte par listerAnnees() de reference.js.
 *
 * Le CRUD complet (création, modification, suppression d'une année) vit côté
 * super-admin — /super-admin/annees-academiques, une route distincte gérée par
 * super-admin/AnneesUniversitairesPage.jsx, hors du périmètre de cette
 * migration : ce module ne porte que ce que l'établissement peut faire sur
 * l'année en cours.
 */

/** Fait de `id` l'année active de l'établissement. */
export function activerAnnee(id) {
  return api.patch(`/admin/annees-academiques/${id}/activate`).then((r) => r.data);
}

/** Aperçu de ce qu'une préparation copierait de `sourceId` vers `id`. */
export function previsualiserPreparation(id, sourceId, signal) {
  return api.get(`/admin/annees-academiques/${id}/preparation`, { params: { source: sourceId }, signal }).then((r) => r.data);
}

/** Copie filières, maquette et (optionnellement) emploi du temps depuis l'année source. */
export function preparerAnnee(id, payload) {
  return api.post(`/admin/annees-academiques/${id}/preparer`, payload).then((r) => r.data);
}
