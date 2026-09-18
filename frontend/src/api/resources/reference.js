import api from '../axios';

/**
 * Données de référence (filières, années, EC, salles…) : lues par de
 * nombreuses pages pour peupler des filtres et des listes déroulantes.
 * Regroupées ici plutôt que dupliquées comme chaîne d'URL dans chaque page.
 */

export function listerFilieres(params) {
  return api.get('/admin/filieres', { params }).then((r) => r.data);
}

export function listerAnnees() {
  return api.get('/admin/annees-academiques').then((r) => r.data);
}

export function listerEcs(params) {
  return api.get('/admin/ecs', { params }).then((r) => r.data);
}

export function listerSallesDisponibles() {
  return api.get('/admin/salles/disponibles').then((r) => r.data);
}
