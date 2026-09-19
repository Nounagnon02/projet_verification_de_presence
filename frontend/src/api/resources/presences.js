import api from '../axios';

/**
 * Couche d'API pour la validation des présences (/admin/presence/…) : file
 * d'arbitrage des scans suspects, historique, export, saisie manuelle — et,
 * côté étudiant, la lecture publique d'un cours depuis un token de QR code.
 *
 * pendingValidations (listerFilePresences) renvoie volontairement le
 * paginateur Laravel BRUT dans « data » (pas le format { data, meta } des
 * autres listes) : { current_page, data: [...], from, to, last_page,
 * per_page, total }. C'est un choix délibéré côté serveur, cette fonction ne
 * doit pas en aplatir la forme.
 */

export function listerFilePresences(params, signal) {
  return api.get('/admin/presence/pending', { params, signal }).then((r) => r.data);
}

export function validerPresence(id, payload) {
  return api.patch(`/admin/presence/${id}/validate`, payload).then((r) => r.data);
}

export function listerHistoriquePresences(params) {
  return api.get('/admin/presence/history', { params }).then((r) => r.data);
}

/** Export CSV/XLSX/PDF : réponse complète (et non r.data), pour lire l'en-tête Content-Disposition. */
export function exporterHistoriquePresences(params) {
  return api.get('/admin/presence/export', { params, responseType: 'blob' });
}

export function listerEtudiantsPourSaisieManuelle(seanceId, signal) {
  return api.get(`/admin/presence/manuelle/${seanceId}/etudiants`, { signal }).then((r) => r.data);
}

export function saisirPresenceManuelle(payload) {
  return api.post('/admin/presence/manuelle', payload).then((r) => r.data);
}

/** Route publique : infos du cours visé par un QR code, pour l'écran de scan étudiant. */
export function recupererCoursParToken(qrToken) {
  return api.get(`/presence/course-by-token/${qrToken}`).then((r) => r.data);
}
