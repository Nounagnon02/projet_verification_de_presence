import api from '../axios';

/**
 * Réglages anti-fraude et de scan de l'université (ParametreGlobal côté
 * serveur). Une seule ligne, lue et modifiée par le super admin.
 */

export function recupererParametresGlobaux(signal) {
  return api.get('/super-admin/parametres', { signal }).then((r) => r.data);
}

export function modifierParametresGlobaux(payload) {
  return api.put('/super-admin/parametres', payload).then((r) => r.data);
}
