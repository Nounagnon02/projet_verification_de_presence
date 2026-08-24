import { http, HttpResponse } from 'msw';

/**
 * Base d'URL utilisee par le client axios de l'application.
 * Les gestionnaires acceptent aussi bien le chemin relatif que l'absolu.
 */
const API = '*/api';

/**
 * Fabriques de reponses conformes a l'enveloppe reelle du backend :
 * { success, message, data } — voir Controller::successResponse().
 */
export const succes = (data, message = 'OK') =>
  HttpResponse.json({ success: true, message, data });

export const echec = (message, status, extra = {}) =>
  HttpResponse.json({ success: false, message, ...extra }, { status });

/**
 * Reponse de GET /presence/course-by-token/{token}, defi inclus.
 * Le defi est emis par le serveur : un test qui le fabriquerait lui-meme
 * reproduirait exactement le bug qu'on cherche a interdire.
 */
export const DEFI_SERVEUR = 'defi-hmac-emis-par-le-serveur';

export const coursParJeton = (surcharges = {}) =>
  http.get(`${API}/presence/course-by-token/:token`, () =>
    succes({
      cours: 'Algorithmique',
      heure_debut: '08:00:00',
      heure_fin: '10:00:00',
      salle: 'A-101',
      date: '2026-03-10',
      filiere: 'GLT',
      token: 'jeton-de-test',
      scan_challenge: DEFI_SERVEUR,
      verification: { gps_requis: false, wifi_requis: false, nom_salle: 'A-101' },
      ...surcharges,
    }),
  );

/**
 * Gestionnaires communs, suffisants pour monter la plupart des pages.
 */
export const handlersParDefaut = [
  coursParJeton(),
  http.get(`${API}/user`, () =>
    HttpResponse.json({
      id: 1,
      name: 'Admin Test',
      email: 'admin@test.local',
      role: 'faculte_admin',
      etablissement_id: 1,
      etablissement: { id: 1, code: 'FAST', nom: 'Faculte de test' },
    }),
  ),
];
