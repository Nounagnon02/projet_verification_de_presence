import axios from 'axios';

/**
 * Client API dédié à l'étudiant qui valide sa présence depuis le navigateur
 * (PresenceValidationPage), distinct de src/api/axios.js (l'administrateur).
 *
 * Cette page est accessible SANS connexion administrateur : elle authentifie
 * l'étudiant elle-même (POST /auth/student/login) et porte SON jeton. Le
 * client admin ne convenait pas : son intercepteur lit et écrit la clé de
 * jeton ADMIN et redirige vers /login (l'écran administrateur) au premier
 * 401 — un même navigateur servant aussi à un administrateur aurait ainsi vu
 * sa propre session écrasée ou coupée par l'activité d'un étudiant.
 */
export const CLE_JETON_ETUDIANT = 'presence_student_token';

const apiEtudiant = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
});

apiEtudiant.interceptors.request.use((config) => {
  const jeton = localStorage.getItem(CLE_JETON_ETUDIANT);
  if (jeton) {
    config.headers.Authorization = `Bearer ${jeton}`;
  }
  return config;
});

export function enregistrerJetonEtudiant(jeton) {
  localStorage.setItem(CLE_JETON_ETUDIANT, jeton);
}

export function effacerJetonEtudiant() {
  localStorage.removeItem(CLE_JETON_ETUDIANT);
}

export function aUnJetonEtudiant() {
  return Boolean(localStorage.getItem(CLE_JETON_ETUDIANT));
}

export default apiEtudiant;
