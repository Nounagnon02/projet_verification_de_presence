import { useState, useEffect, useMemo, useCallback } from 'react';
import api from '../api/axios';

/**
 * Filtres académiques en cascade : année → filière → niveau/semestre.
 *
 * Pourquoi un hook partagé plutôt que la logique recopiée sur chaque écran :
 * six pages portaient les mêmes quatre filtres, toutes indépendants. Chacune
 * appelait « /admin/filieres » sans paramètre et codait « Semestre 1 à 6 » en
 * dur. On pouvait donc choisir une année vide et se voir proposer les filières
 * d'une autre, ou un semestre qui n'existe nulle part.
 *
 * Deux règles portent la cascade :
 *
 *  - Les OPTIONS viennent du serveur, jamais d'une liste figée. Les semestres
 *    réels vont de 1 à 10 ; la liste codée en dur s'arrêtait à 6, rendant tout
 *    le Master infiltrable.
 *
 *  - Un filtre enfant SE REMET À ZÉRO quand son parent change, faute de quoi on
 *    conserverait une filière absente de la nouvelle année — donc un résultat
 *    vide sans que rien ne l'explique à l'écran.
 *
 * La remise à zéro se fait pendant le rendu, et non dans un effet : c'est le
 * motif recommandé par React pour un état dérivé d'une prop, et il évite le
 * rendu intermédiaire incohérent qu'un effet produirait — ici, un appel réseau
 * parti avec un couple (année, filière) impossible.
 *
 * @param {{ onChangement?: () => void, preselectionnerAnneeActive?: boolean }} options
 *   onChangement est appelé à chaque modification d'un filtre. Les écrans
 *   paginés y remettent la pagination à la première page.
 *   preselectionnerAnneeActive ouvre l'écran sur l'année en cours plutôt que
 *   sur « toutes les années » : ce que l'on veut d'un tableau de bord, qui
 *   doit montrer l'exercice courant sans qu'on ait à le désigner.
 */
export function useFiltresAcademiques({ onChangement, preselectionnerAnneeActive = false } = {}) {
  const [annee, setAnneeBrut] = useState('');
  const [filiere, setFiliereBrut] = useState('');
  const [niveau, setNiveauBrut] = useState('');
  const [semestre, setSemestreBrut] = useState('');

  const [annees, setAnnees] = useState([]);
  const [filieres, setFilieres] = useState([]);

  // Année dont les filières sont effectivement chargées. Le chargement est
  // DÉDUIT de l'écart avec l'année demandée, plutôt que porté par un état posé
  // au début de l'effet : React déconseille d'appeler setState dans le corps
  // d'un effet, et la valeur dérivée ne peut pas se désynchroniser.
  const [anneeChargee, setAnneeChargee] = useState(null);
  const chargement = anneeChargee !== annee;

  // Liste NON restreinte, pour les formulaires de création et de promotion.
  // Y appliquer la restriction de la barre de filtres serait un contresens :
  // on doit pouvoir créer un étudiant dans une filière que l'on n'est pas en
  // train de consulter.
  const [filieresToutes, setFilieresToutes] = useState([]);

  // Les années : chargées une fois, elles ne dépendent de rien.
  useEffect(() => {
    const controleur = new AbortController();

    api.get('/admin/annees-academiques', { signal: controleur.signal })
      .then(({ data }) => {
        const liste = data?.data ?? data ?? [];
        setAnnees(liste);

        if (preselectionnerAnneeActive) {
          const active = liste.find((a) => a.active);
          if (active) setAnneeBrut(String(active.id));
        }
      })
      .catch(() => { /* l'écran affiche déjà son erreur de chargement */ });

    return () => controleur.abort();
    // Le drapeau n'est lu qu'au premier chargement : le changer ensuite ne doit
    // pas réécraser le choix de l'utilisateur.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Liste complète, chargée une fois. Elle sert aux formulaires, et de repli
  // quand on revient à « Toutes les années » — inutile alors de réinterroger le
  // serveur pour une réponse que l'on possède déjà.
  useEffect(() => {
    const controleur = new AbortController();

    api.get('/admin/filieres', { signal: controleur.signal })
      .then(({ data }) => {
        setFilieresToutes(data?.data ?? data ?? []);
        setAnneeChargee((precedente) => (precedente === null ? '' : precedente));
      })
      .catch((e) => {
        // Une annulation au démontage est normale. Sur toute autre erreur, on
        // débloque quand même l'affichage : un filtre inerte vaut mieux qu'un
        // écran figé sur « chargement ».
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
          setAnneeChargee((precedente) => (precedente === null ? '' : precedente));
        }
      });

    return () => controleur.abort();
  }, []);

  // Filières de l'année choisie. La requête est annulable : en changeant
  // d'année deux fois de suite, la première réponse pourrait sinon écraser la
  // seconde et afficher les filières de l'année précédente.
  useEffect(() => {
    if (!annee) return undefined;

    const controleur = new AbortController();

    api.get('/admin/filieres', {
      params: { annee_id: annee },
      signal: controleur.signal,
    })
      .then(({ data }) => {
        setFilieres(data?.data ?? data ?? []);
        setAnneeChargee(annee);
      })
      .catch((e) => {
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') setAnneeChargee(annee);
      });

    return () => controleur.abort();
  }, [annee]);

  // Sans année choisie, les filières affichées SONT la liste complète.
  const filieresAffichees = annee ? filieres : filieresToutes;

  // Niveaux réellement présents. Le niveau est un attribut de la filière : le
  // proposer en dur (L1..M2) laissait construire « IM-L3 + M1 », vide par
  // construction. Sur onze filières et cinq niveaux, cinquante-cinq
  // combinaisons étaient offertes, onze seulement pouvaient donner un résultat.
  // Filières effectivement concernées : celle qui est choisie, sinon toutes
  // celles de l'année. Niveaux et semestres en découlent tous les deux.
  const retenues = useMemo(
    () => (filiere
      ? filieresAffichees.filter((f) => String(f.id) === String(filiere))
      : filieresAffichees),
    [filieresAffichees, filiere],
  );

  const niveaux = useMemo(
    () => [...new Set(retenues.map((f) => f.niveau).filter(Boolean))].sort(),
    [retenues],
  );

  const semestres = useMemo(
    () => [...new Set(retenues.flatMap((f) => f.semestres ?? []))].sort((a, b) => a - b),
    [retenues],
  );

  // Le niveau devenu impossible se remet à zéro, comme le semestre. Choisir la
  // filière GL-M2 puis conserver « L1 » demandait au serveur une combinaison
  // vide par construction, sans que l'écran l'explique.
  if (niveau && niveaux.length > 0 && !niveaux.includes(niveau)) {
    setNiveauBrut('');
  }

  // Remise à zéro en cascade, pendant le rendu.
  const [anneePrecedente, setAnneePrecedente] = useState(annee);

  if (annee !== anneePrecedente) {
    setAnneePrecedente(annee);
    setFiliereBrut('');
    setNiveauBrut('');
    setSemestreBrut('');
  }

  const [filierePrecedente, setFilierePrecedente] = useState(filiere);

  if (filiere !== filierePrecedente) {
    setFilierePrecedente(filiere);
    // Le niveau est porté par la filière : le garder produirait une
    // contradiction. Le semestre, lui, se restreint à ceux de la filière.
    setNiveauBrut('');
    setSemestreBrut('');
  }

  // Un filtre dont la valeur a disparu des options se remet à zéro : sans cela,
  // l'écran interrogerait le serveur avec un identifiant que la liste ne
  // propose plus, et n'afficherait aucun résultat sans raison visible.
  if (filiere && !chargement && !filieresAffichees.some((f) => String(f.id) === String(filiere))) {
    setFiliereBrut('');
  }

  if (semestre && semestres.length > 0 && !semestres.includes(Number(semestre))) {
    setSemestreBrut('');
  }

  const enveloppe = useCallback(
    (setter) => (valeur) => {
      setter(valeur);
      onChangement?.();
    },
    [onChangement],
  );

  return {
    annee, filiere, niveau, semestre,
    setAnnee: enveloppe(setAnneeBrut),
    setFiliere: enveloppe(setFiliereBrut),
    setNiveau: enveloppe(setNiveauBrut),
    setSemestre: enveloppe(setSemestreBrut),
    annees, filieres: filieresAffichees, filieresToutes, niveaux, semestres,
    chargement,
    // Vrai quand l'année choisie ne contient rien : l'écran doit le dire plutôt
    // que d'afficher un tableau vide sans explication.
    anneeVide: Boolean(annee) && !chargement && filieresAffichees.length === 0,
  };
}

export default useFiltresAcademiques;
