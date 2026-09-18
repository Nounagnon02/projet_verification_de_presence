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
 * Reponse de GET /presence/course-by-token/{token}.
 * « scan_challenge » a disparu de cette reponse : le scan est desormais
 * authentifie par un jeton etudiant (POST /auth/student/login), et non par un
 * defi emis ici puis renvoye tel quel.
 */
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
      verification: { gps_requis: false, wifi_requis: false, nom_salle: 'A-101' },
      ...surcharges,
    }),
  );

/**
 * Reponse de POST /auth/student/login. Jeton fictif suffisant : le contrat
 * observe par les tests est le corps envoye par le client, pas ce que le
 * jeton contient reellement.
 */
export const JETON_ETUDIANT_TEST = 'jeton-etudiant-de-test';

export const connexionEtudiant = (surcharges = {}) =>
  http.post(`${API}/auth/student/login`, () =>
    succes({
      token: JETON_ETUDIANT_TEST,
      user: { id: 1, email: 'jean.doe@uac.bj', identifiant_unique: 'DOE_JOHN_22A1234_GLT_L3', role: 'etudiant' },
      ...surcharges,
    }, 'Connecté avec succès.'),
  );

/**
 * Gestionnaires communs, suffisants pour monter la plupart des pages.
 */
export const handlersParDefaut = [
  coursParJeton(),
  connexionEtudiant(),
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
