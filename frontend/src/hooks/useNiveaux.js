import { useEffect, useState } from 'react';
import api from '../api/axios';

/**
 * Niveaux officiels (L1…M2) et leurs semestres, lus au serveur.
 *
 * Les écrans recopiaient « L1, L2, L3, M1, M2 » et « Semestre 1 à 6 » en dur,
 * et le serveur ne validait aucune liste : un niveau « Licence 1 » passait par
 * l'API, sans semestre associé. La liste vient désormais d'un seul endroit.
 * Elle ne change pas pendant une session : un seul appel, gardé en mémoire.
 */
let enMemoire = null;

export default function useNiveaux() {
  const [niveaux, setNiveaux] = useState(() => enMemoire ?? []);

  useEffect(() => {
    if (enMemoire) return undefined;

    const controleur = new AbortController();

    api.get('/admin/niveaux', { signal: controleur.signal })
      .then(({ data }) => {
        const liste = Array.isArray(data?.data) ? data.data : [];
        if (liste.length) enMemoire = liste;
        setNiveaux(liste);
      })
      .catch(() => { /* les écrans retombent sur tous les semestres */ });

    return () => controleur.abort();
  }, []);

  return niveaux;
}

/** Semestres d'un niveau ; tous (1 à 10) si le niveau est inconnu ou pas encore chargé. */
export const semestresDuNiveau = (niveaux, code) =>
  niveaux.find((n) => n.code === code)?.semestres ?? Array.from({ length: 10 }, (_, i) => i + 1);
