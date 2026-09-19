import api from '../axios';

/** Couche d'API pour la page publique d'accueil (/landing…). */

export function obtenirStatistiquesPubliques(signal) {
  return api.get('/landing/stats', { signal }).then((r) => r.data);
}
