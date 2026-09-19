import { Fragment, useState, useCallback, useMemo, useRef, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { FiLoader, FiRefreshCw, FiDownload, FiChevronDown, FiSearch, FiArrowUp, FiArrowDown } from 'react-icons/fi';
import { enregistrer, nomFichierServeur } from '../../utils/telechargement';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';
import useDebounce from '../../hooks/useDebounce';
import TauxHebdomadaireChart from '../../components/charts/TauxHebdomadaireChart';
import BarreTaux from '../../components/charts/BarreTaux';
import { couleurTaux, libelleTaux } from '../../utils/taux';
import ComparaisonsRapport from './ComparaisonsRapport';
import {
  listerUes, rapportFiltre, rapportEtudiantsAbsents,
  exporterPresencesCsv, exporterEtudiantsAbsentsCsv, exporterRapportDepartementPdf,
} from '../../api/resources/rapports';

/** « 2026-09-14 » à partir d'une date locale. */
const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/** Période par défaut : les 30 derniers jours, aujourd'hui compris. */
const periodeParDefaut = () => {
  const fin = new Date();
  const debut = new Date(fin);
  debut.setDate(fin.getDate() - 29);
  return { debut: iso(debut), fin: iso(fin) };
};

const formaterDate = (valeur) => (valeur ? valeur.split('-').reverse().join('/') : '…');
const jourMois = (valeur) => (valeur ? `${valeur.slice(8, 10)}/${valeur.slice(5, 7)}` : '');

const ABSENTS_VISIBLES = 6;
const UES_FAIBLES = 5;
const UE_PAR_PAGE = 10;

const CARTE = 'bg-surface-container-lowest rounded-2xl border border-outline-variant/10';
const ETIQUETTE = 'text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant';
const CHAMP = 'w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface disabled:opacity-40';

const PastilleTaux = ({ taux }) => {
  const couleur = couleurTaux(taux);

  return (
    <span
      className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums"
      style={couleur ? { color: couleur, backgroundColor: `${couleur}1f` } : undefined}
    >
      {libelleTaux(taux)}
    </span>
  );
};

/** Carte chiffrée. Le libellé réserve deux lignes : les valeurs restent alignées quand l'un d'eux passe à la ligne. */
const Chiffre = ({ libelle, valeur, detail, action, couleur, className = '' }) => (
  <div className={`${CARTE} p-4 flex flex-col gap-1 min-w-0 ${className}`}>
    <p className={`${ETIQUETTE} leading-tight min-h-[2.5em]`}>{libelle}</p>
    <p className="text-2xl font-bold font-headline tabular-nums text-primary" style={couleur ? { color: couleur } : undefined}>{valeur}</p>
    {detail && <p className="text-[11px] text-on-surface-variant">{detail}</p>}
    {action && <div className="mt-auto pt-1 text-xs font-semibold">{action}</div>}
  </div>
);

const CHOIX_EXPORT = [
  { id: 'presences-csv', titre: 'Liste des présences', detail: 'CSV · tous les filtres, rappelés dans le nom du fichier' },
  { id: 'absents-csv', titre: 'Étudiants les plus absents', detail: 'CSV · la liste entière' },
  { id: 'ue-csv', titre: 'Détail par UE', detail: 'CSV · séances, attendus, présents et taux' },
  { id: 'filiere-pdf', titre: 'Rapport de filière', detail: 'PDF · la filière choisie dans les filtres' },
];

/**
 * Un seul bouton pour tous les exports. Ils étaient éparpillés entre un bloc
 * au milieu de la page et l'en-tête du tableau par UE.
 */
const MenuExport = ({ onExporter, enCours, filiereChoisie }) => {
  const [ouvert, setOuvert] = useState(false);
  const racine = useRef(null);

  useEffect(() => {
    if (!ouvert) return undefined;

    const clicExterieur = (e) => { if (!racine.current?.contains(e.target)) setOuvert(false); };
    const echap = (e) => { if (e.key === 'Escape') setOuvert(false); };

    document.addEventListener('mousedown', clicExterieur);
    document.addEventListener('keydown', echap);

    return () => {
      document.removeEventListener('mousedown', clicExterieur);
      document.removeEventListener('keydown', echap);
    };
  }, [ouvert]);

  return (
    <div className="relative" ref={racine}>
      <button
        type="button"
        onClick={() => setOuvert((o) => !o)}
        aria-haspopup="menu"
        aria-expanded={ouvert}
        disabled={Boolean(enCours)}
        className="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-60"
      >
        {enCours ? <FiLoader className="animate-spin" aria-hidden="true" /> : <FiDownload aria-hidden="true" />}
        {enCours ? 'Export…' : 'Exporter'}
        <FiChevronDown aria-hidden="true" />
      </button>

      {ouvert && (
        <div role="menu" aria-label="Exporter" className="absolute right-0 top-full mt-2 z-20 w-80 max-w-[86vw] p-1.5 bg-surface-container-lowest border border-outline-variant/20 rounded-xl shadow-lg">
          {CHOIX_EXPORT.map((choix) => {
            const desactive = choix.id === 'filiere-pdf' && !filiereChoisie;

            return (
              <button
                key={choix.id}
                type="button"
                role="menuitem"
                disabled={desactive}
                onClick={() => { setOuvert(false); onExporter(choix.id); }}
                className="w-full text-left px-3 py-2.5 rounded-lg hover:bg-surface-container-low disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent"
              >
                <span className="block text-sm font-semibold text-on-surface">{choix.titre}</span>
                <span className="block text-[11px] text-on-surface-variant">
                  {desactive ? "PDF · choisissez d'abord une filière dans les filtres" : choix.detail}
                </span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};

/** Étudiants ayant au moins une absence, les plus absents d'abord. */
const TableAbsents = ({ liste }) => {
  const [tout, setTout] = useState(false);

  if (!liste) {
    return <p className="text-sm text-on-surface-variant">La liste n'a pas pu être chargée. Réessayez avec « Actualiser ».</p>;
  }

  const etudiants = Array.isArray(liste.etudiants) ? liste.etudiants : [];
  const attendus = liste.etudiants_attendus ?? 0;

  if (attendus === 0) {
    return <p className="text-sm text-on-surface-variant">Aucun étudiant attendu sur ce périmètre.</p>;
  }

  if (etudiants.length === 0) {
    return <p className="text-sm text-on-surface-variant">Aucune absence : tous les étudiants attendus ont été présents.</p>;
  }

  const visibles = tout ? etudiants : etudiants.slice(0, ABSENTS_VISIBLES);

  return (
    <>
      <div className="overflow-x-auto -mx-5 px-5">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-[10px] text-on-surface-variant uppercase tracking-wider border-b border-outline-variant/20">
              <th scope="col" className="py-2 pr-3 font-semibold">Étudiant</th>
              <th scope="col" className="py-2 px-3 font-semibold">Filière</th>
              <th scope="col" className="py-2 px-3 font-semibold text-right">Absences</th>
              <th scope="col" className="py-2 px-3 font-semibold text-right whitespace-nowrap">Présents / attendus</th>
              <th scope="col" className="py-2 px-3 font-semibold text-right">Taux</th>
              <th scope="col" className="py-2 pl-3 font-semibold whitespace-nowrap">Dernier cours manqué</th>
            </tr>
          </thead>
          <tbody>
            {visibles.map((e) => (
              <tr key={e.etudiant_id} className="border-b border-outline-variant/10 last:border-0">
                <td className="py-2.5 pr-3">
                  <Link to={`/attendance/student-stats/${e.etudiant_id}`} className="font-semibold text-on-surface hover:text-primary hover:underline">
                    {e.prenom} {e.nom}
                  </Link>
                  <span className="block font-mono text-[11px] text-on-surface-variant">{e.matricule}</span>
                </td>
                <td className="py-2.5 px-3 font-mono text-xs">{e.filiere_code ?? '—'}</td>
                <td className="py-2.5 px-3 text-right font-bold tabular-nums">{e.absences}</td>
                <td className="py-2.5 px-3 text-right text-on-surface-variant tabular-nums">{e.presents} / {e.attendus}</td>
                <td className="py-2.5 px-3 text-right"><PastilleTaux taux={e.taux} /></td>
                <td className="py-2.5 pl-3 text-xs text-on-surface-variant">
                  {e.dernier_manque ? `${e.dernier_manque.ec ?? 'Cours'} · ${jourMois(e.dernier_manque.date)}` : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 mt-3">
        <p className="text-xs text-on-surface-variant">
          {etudiants.length} étudiant{etudiants.length > 1 ? 's' : ''} sur les {attendus} attendus {etudiants.length > 1 ? 'ont' : 'a'} au moins une absence.
        </p>
        {etudiants.length > ABSENTS_VISIBLES && (
          <button
            type="button"
            onClick={() => setTout((v) => !v)}
            aria-expanded={tout}
            className="px-3 py-1.5 text-xs font-semibold text-primary bg-primary/10 rounded-lg hover:bg-primary/20 transition-all"
          >
            {tout ? 'Réduire' : `Afficher les ${etudiants.length}`}
          </button>
        )}
      </div>
    </>
  );
};

const COLONNES_UE = [
  { col: 'code', libelle: 'Code' },
  { col: 'intitule', libelle: 'Intitulé' },
  { col: 'semestre', libelle: 'Semestre', droite: true },
  { col: 'total_evenements', libelle: 'Séances', droite: true },
  { col: 'presences_attendues', libelle: 'Attendus', droite: true },
  { col: 'total_presences', libelle: 'Présents', droite: true },
  { col: 'taux', libelle: 'Taux', droite: true },
];

/**
 * Détail par UE. Il suit les filtres du haut : ses propres listes « semestre »
 * et « filière » faisaient doublon, et pouvaient les contredire.
 */
const TableUe = ({ lignes }) => {
  const [recherche, setRecherche] = useState('');
  const [page, setPage] = useState(1);
  const [tri, setTri] = useState({ col: 'code', dir: 'asc' });
  const rechercheDebouncee = useDebounce(recherche, 300);

  const filtrees = useMemo(() => {
    const q = rechercheDebouncee.trim().toLowerCase();
    const retenues = q ? lignes.filter((u) => `${u.code} ${u.intitule}`.toLowerCase().includes(q)) : lignes;
    const sens = tri.dir === 'asc' ? 1 : -1;

    return [...retenues].sort((a, b) => {
      const va = a[tri.col];
      const vb = b[tri.col];
      if (typeof va === 'number' || typeof vb === 'number') return ((va ?? 0) - (vb ?? 0)) * sens;
      return String(va ?? '').localeCompare(String(vb ?? '')) * sens;
    });
  }, [lignes, rechercheDebouncee, tri]);

  // Taux pondéré par les présences attendues : la moyenne des taux donnait le
  // même poids à une UE de 3 étudiants qu'à une UE de 300.
  const totaux = useMemo(() => {
    const attendus = filtrees.reduce((s, u) => s + (u.presences_attendues || 0), 0);
    const presents = filtrees.reduce((s, u) => s + (u.total_presences || 0), 0);

    return {
      seances: filtrees.reduce((s, u) => s + (u.total_evenements || 0), 0),
      attendus,
      presents,
      taux: attendus ? Math.round((presents / attendus) * 1000) / 10 : null,
    };
  }, [filtrees]);

  const pages = Math.max(1, Math.ceil(filtrees.length / UE_PAR_PAGE));
  const pageCourante = Math.min(page, pages);
  const visibles = filtrees.slice((pageCourante - 1) * UE_PAR_PAGE, pageCourante * UE_PAR_PAGE);

  const trier = (col) => setTri((t) => (t.col === col && t.dir === 'asc' ? { col, dir: 'desc' } : { col, dir: 'asc' }));

  return (
    <section aria-labelledby="titre-detail-ue" className={`${CARTE} overflow-hidden`}>
      <div className="p-4 flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/10">
        <div>
          <h2 id="titre-detail-ue" className="text-sm font-bold font-headline text-primary">Détail par UE</h2>
          <p className="text-[11px] text-on-surface-variant mt-0.5">Suit les filtres du haut · {lignes.length} UE</p>
        </div>
        <div className="relative w-full sm:w-72">
          <FiSearch className="absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant" size={12} aria-hidden="true" />
          <input
            id="recherche-ue"
            type="search"
            value={recherche}
            onChange={(e) => { setRecherche(e.target.value); setPage(1); }}
            placeholder="Rechercher un code ou un intitulé"
            aria-label="Rechercher une UE"
            className="w-full pl-7 pr-2 py-1.5 bg-surface-container-high rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-primary/30 text-on-surface"
          />
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-[10px] text-on-surface-variant uppercase tracking-wider bg-surface-container-low/30">
              {COLONNES_UE.map(({ col, libelle, droite }) => (
                <th
                  key={col}
                  scope="col"
                  aria-sort={tri.col === col ? (tri.dir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  className={`p-3 font-semibold ${droite ? 'text-right' : ''}`}
                >
                  <button type="button" onClick={() => trier(col)} className="inline-flex items-center gap-1 uppercase tracking-wider hover:text-primary">
                    {libelle}
                    {tri.col === col
                      ? (tri.dir === 'asc' ? <FiArrowUp size={10} aria-hidden="true" /> : <FiArrowDown size={10} aria-hidden="true" />)
                      : <span className="opacity-30" aria-hidden="true">↕</span>}
                  </button>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {visibles.length === 0 ? (
              <tr><td colSpan={COLONNES_UE.length} className="p-8 text-center text-on-surface-variant text-xs">Aucune UE ne correspond à la recherche</td></tr>
            ) : visibles.map((ue) => (
              <tr key={ue.ue_id} className="border-b border-outline-variant/10 last:border-0 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-3 font-mono text-xs">{ue.code}</td>
                <td className="p-3">{ue.intitule}</td>
                <td className="p-3 text-right text-on-surface-variant">S{ue.semestre}</td>
                <td className="p-3 text-right tabular-nums">{ue.total_evenements}</td>
                <td className="p-3 text-right tabular-nums text-on-surface-variant">{ue.presences_attendues ?? '—'}</td>
                <td className="p-3 text-right tabular-nums">{ue.total_presences}</td>
                <td className="p-3 text-right"><PastilleTaux taux={ue.presences_attendues ? ue.taux : null} /></td>
              </tr>
            ))}
          </tbody>
          {filtrees.length > 0 && (
            <tfoot>
              <tr className="border-t-2 border-outline-variant/20 bg-surface-container-low/50 text-xs font-bold text-on-surface-variant tabular-nums">
                <td className="p-3" colSpan={3}>{filtrees.length} UE</td>
                <td className="p-3 text-right">{totaux.seances}</td>
                <td className="p-3 text-right">{totaux.attendus}</td>
                <td className="p-3 text-right">{totaux.presents}</td>
                <td className="p-3 text-right" style={{ color: couleurTaux(totaux.taux) }}>{libelleTaux(totaux.taux)}</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      {pages > 1 && (
        <div className="flex items-center justify-between px-4 py-3 border-t border-outline-variant/10">
          <span className="text-xs text-on-surface-variant">Page {pageCourante} / {pages}</span>
          <div className="flex gap-1">
            <button type="button" onClick={() => setPage(pageCourante - 1)} disabled={pageCourante === 1}
              className="px-3 py-1 text-xs rounded-lg bg-surface-container-high hover:bg-surface-container-highest disabled:opacity-40 transition-all">‹ Préc.</button>
            <button type="button" onClick={() => setPage(pageCourante + 1)} disabled={pageCourante === pages}
              className="px-3 py-1 text-xs rounded-lg bg-surface-container-high hover:bg-surface-container-highest disabled:opacity-40 transition-all">Suiv. ›</button>
          </div>
        </div>
      )}
    </section>
  );
};

/**
 * Rapports de présence.
 *
 * Organisation : ce que l'on regarde (périmètre), une seule barre de filtres
 * qui vaut pour toute la page et pour les exports, les chiffres clés, puis du
 * plus synthétique au plus détaillé. Les comparaisons, qui portent sur toute
 * l'année, ont leur onglet.
 */
const ReportsPage = () => {
  const [onglet, setOnglet] = useState('ensemble');
  const surEnsemble = onglet === 'ensemble';

  // Année → filière → semestre en cascade : les options viennent du serveur,
  // et un filtre enfant se remet à zéro quand son parent change.
  const filtres = useFiltresAcademiques({ preselectionnerAnneeActive: true });
  const { annee: anneeId, filiere: filiereId, semestre } = filtres;

  const [ueId, setUeId] = useState('');
  const [ecId, setEcId] = useState('');
  // Une seule période, qui s'applique à tout : compteurs, UE, évolution,
  // absents et exports.
  const [dateDebut, setDateDebut] = useState(() => periodeParDefaut().debut);
  const [dateFin, setDateFin] = useState(() => periodeParDefaut().fin);

  // UEs de l'année et de la filière choisies ; le semestre se filtre ici.
  const uesQuery = useQuery({
    queryKey: ['ues', anneeId, filiereId],
    queryFn: ({ signal }) => {
      const params = {};
      if (anneeId) params.annee_id = anneeId;
      if (filiereId) params.filiere_id = filiereId;
      return listerUes(params, signal);
    },
    enabled: !filtres.chargement,
  });
  // Mémoïsé : `?? []` recréerait un tableau à chaque rendu tant que la requête
  // n'a pas répondu, et invaliderait les useMemo qui en dépendent.
  const uesBrutes = uesQuery.data;
  const ues = useMemo(() => uesBrutes?.data ?? uesBrutes ?? [], [uesBrutes]);

  const uesProposees = useMemo(
    () => (semestre ? ues.filter((u) => String(u.semestre) === String(semestre)) : ues),
    [ues, semestre],
  );

  const ecs = useMemo(() => (ueId ? ues.find((u) => String(u.id) === ueId)?.ecs || [] : []), [ueId, ues]);

  // L'EC se remet à zéro quand l'UE change, et l'UE quand elle sort des
  // options (autre année, filière ou semestre). Ajustements faits pendant le
  // rendu, comme dans useFiltresAcademiques, plutôt que dans un effet.
  const [ueIdPrecedent, setUeIdPrecedent] = useState(ueId);

  if (ueId !== ueIdPrecedent) {
    setUeIdPrecedent(ueId);
    setEcId('');
  }

  if (ueId && ues.length > 0 && !uesProposees.some((u) => String(u.id) === ueId)) {
    setUeId('');
  }

  const construireParams = useCallback(() => {
    const params = {};
    if (anneeId) params.annee_id = anneeId;
    if (filiereId) params.filiere_id = filiereId;
    if (semestre) params.semestre = semestre;
    if (ueId) params.ue_id = ueId;
    if (ecId) params.ec_id = ecId;
    if (dateDebut) params.date_debut = dateDebut;
    if (dateFin) params.date_fin = dateFin;
    return params;
  }, [anneeId, filiereId, semestre, ueId, ecId, dateDebut, dateFin]);

  // Une seule valeur débouncée pour tous les filtres : le rapport part 400 ms
  // après le dernier changement, pas à chaque chiffre tapé dans une date.
  const parametresCourants = JSON.stringify(construireParams());
  const parametres = useDebounce(parametresCourants, 400);
  // Tant que la valeur débouncée retarde sur les filtres, elle porte des
  // paramètres déjà périmés — à l'ouverture, ceux d'avant la présélection de
  // l'année active : on attend qu'elle les rattrape.
  const aJour = parametres === parametresCourants;

  // Le rapport et la liste des absents portent sur les mêmes filtres, mais
  // chacun a sa propre clé : une requête peut échouer sans faire disparaître
  // l'autre (Promise.allSettled avant, la tolérance de TanStack Query
  // maintenant).
  const rapportQuery = useQuery({
    queryKey: ['rapport-filtre', parametres],
    queryFn: ({ signal }) => rapportFiltre(JSON.parse(parametres), signal),
    enabled: !filtres.chargement && aJour,
  });
  const absentsQuery = useQuery({
    queryKey: ['rapport-etudiants-absents', parametres],
    queryFn: ({ signal }) => rapportEtudiantsAbsents(JSON.parse(parametres), signal),
    enabled: !filtres.chargement && aJour,
  });
  const loading = rapportQuery.isFetching || absentsQuery.isFetching;
  const data = rapportQuery.isError ? null : (rapportQuery.data?.data ?? null);
  const absents = absentsQuery.isError ? null : (absentsQuery.data?.data ?? null);

  const statsParUe = useMemo(() => (Array.isArray(data?.stats_par_ue) ? data.stats_par_ue : []), [data]);
  const evolution = useMemo(() => (Array.isArray(data?.evolution) ? data.evolution : []), [data]);

  // Les UE les plus faibles d'abord : c'est là qu'il faut agir.
  const uesAvecSeances = useMemo(() => statsParUe.filter((ue) => ue.presences_attendues > 0), [statsParUe]);
  const uesFaibles = useMemo(() => [...uesAvecSeances].sort((a, b) => a.taux - b.taux).slice(0, UES_FAIBLES), [uesAvecSeances]);

  //  EXPORTS
  const [exportEnCours, setExportEnCours] = useState(null);
  const [erreurExport, setErreurExport] = useState('');

  const exporterUeCsv = () => {
    const entete = 'Code,Intitulé,Semestre,Séances,Attendus,Présents,Taux';
    const lignes = statsParUe.map((u) => [
      u.code,
      `"${String(u.intitule ?? '').replaceAll('"', '""')}"`,
      `S${u.semestre}`,
      u.total_evenements,
      u.presences_attendues ?? '',
      u.total_presences,
      u.presences_attendues ? `${u.taux}%` : '',
    ].join(','));

    // BOM : sans lui, Excel lit mal les accents.
    enregistrer(`\uFEFF${entete}\n${lignes.join('\n')}`, `detail_par_ue_du-${dateDebut}_au-${dateFin}.csv`);
  };

  const exporter = async (type) => {
    setErreurExport('');

    if (type === 'ue-csv') {
      if (statsParUe.length === 0) {
        setErreurExport('Aucune UE à exporter sur ce périmètre.');
        return;
      }
      exporterUeCsv();
      return;
    }

    // Les filtres affichés, et non ceux du dernier chargement.
    const params = construireParams();
    const exportateurs = {
      'presences-csv': { fn: () => exporterPresencesCsv(params), repli: 'presences.csv' },
      'absents-csv': { fn: () => exporterEtudiantsAbsentsCsv(params), repli: 'etudiants_absents.csv' },
      'filiere-pdf': { fn: () => exporterRapportDepartementPdf(filiereId), repli: 'rapport_filiere.pdf' },
    };
    const cible = exportateurs[type];

    if (!cible || (type === 'filiere-pdf' && !filiereId)) return;

    setExportEnCours(type);

    try {
      const { data: contenu, headers } = await cible.fn();
      // Le nom donné par le serveur résume les filtres ; le nom local n'est qu'un repli.
      enregistrer(contenu, nomFichierServeur(headers, cible.repli));
    } catch {
      setErreurExport("L'export a échoué. Réessayez dans un instant.");
    } finally {
      setExportEnCours(null);
    }
  };

  const reinitialiser = () => {
    const active = filtres.annees.find((a) => a.active);
    filtres.setAnnee(active ? String(active.id) : '');
    filtres.setFiliere('');
    filtres.setSemestre('');
    setUeId('');
    setEcId('');
    const periode = periodeParDefaut();
    setDateDebut(periode.debut);
    setDateFin(periode.fin);
  };

  const voirAbsents = () => document.getElementById('etudiants-absents')?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });

  // Ce que l'on regarde, en une ligne : l'écran ne disait nulle part sur quoi
  // portaient les chiffres.
  const anneeChoisie = filtres.annees.find((a) => String(a.id) === String(anneeId));
  const filiereChoisie = filtres.filieres.find((f) => String(f.id) === String(filiereId));
  const ueChoisie = ues.find((u) => String(u.id) === ueId);
  const ecChoisi = ecs.find((e) => String(e.id) === ecId);

  const perimetre = [
    (data?.entite ?? '').split(' — ')[0],
    anneeChoisie ? `Année ${anneeChoisie.libelle}` : 'Toutes les années',
    surEnsemble && `du ${formaterDate(dateDebut)} au ${formaterDate(dateFin)}`,
    filiereChoisie ? filiereChoisie.code : 'toutes filières',
    surEnsemble && semestre && `S${semestre}`,
    surEnsemble && ueChoisie?.code,
    surEnsemble && ecChoisi && (ecChoisi.code || ecChoisi.intitule),
  ].filter(Boolean);

  const d = data || {};

  return (
    <div>
      {/*  EN-TÊTE  */}
      <header className="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-5">
        <div className="min-w-0">
          <h1 className="text-2xl font-bold text-primary font-headline">Rapports de présence</h1>
          <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-on-surface-variant">
            {perimetre.map((morceau, i) => (
              <Fragment key={`${i}-${morceau}`}>
                {i > 0 && <span aria-hidden="true" className="text-outline-variant">·</span>}
                <span className="font-medium text-on-surface">{morceau}</span>
              </Fragment>
            ))}
          </p>
        </div>
        <div className="flex gap-2 shrink-0">
          <button
            type="button"
            onClick={() => { rapportQuery.refetch(); absentsQuery.refetch(); }}
            disabled={loading}
            className="flex items-center gap-2 px-3 py-2 bg-surface-container-high text-on-surface rounded-xl text-xs font-semibold hover:bg-surface-container-highest transition-all disabled:opacity-50"
          >
            {loading ? <FiLoader className="animate-spin" aria-hidden="true" /> : <FiRefreshCw aria-hidden="true" />}
            Actualiser
          </button>
          <MenuExport onExporter={exporter} enCours={exportEnCours} filiereChoisie={Boolean(filiereId)} />
        </div>
      </header>

      {erreurExport && <p role="alert" className="-mt-2 mb-4 text-xs text-error font-medium">{erreurExport}</p>}

      {/*  ONGLETS  */}
      <div role="tablist" aria-label="Sections du rapport" className="flex gap-1 border-b border-outline-variant/20 mb-4">
        {[['ensemble', "Vue d'ensemble"], ['comparaisons', 'Comparaisons']].map(([id, libelle]) => (
          <button
            key={id}
            type="button"
            role="tab"
            id={`onglet-${id}`}
            aria-controls={`panneau-${id}`}
            aria-selected={onglet === id}
            onClick={() => setOnglet(id)}
            className={`px-4 py-2.5 -mb-px text-sm font-semibold border-b-2 transition-colors ${onglet === id ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-primary'}`}
          >
            {libelle}
          </button>
        ))}
      </div>

      {/*  FILTRES  */}
      <section aria-label="Filtres du rapport" className={`${CARTE} p-4 mb-5`}>
        <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
          <p className={ETIQUETTE}>
            {surEnsemble
              ? "Filtres · ils s'appliquent à toute la page et aux exports"
              : "Filtres · seules l'année et la filière s'appliquent aux comparaisons"}
          </p>
          <button type="button" onClick={reinitialiser} className="text-xs font-semibold text-primary hover:underline">
            Réinitialiser
          </button>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
          <div>
            <label htmlFor="rapport-annee" className={`${ETIQUETTE} block mb-0.5`}>Année</label>
            <select id="rapport-annee" value={anneeId} onChange={(e) => filtres.setAnnee(e.target.value)} className={CHAMP}>
              <option value="">Toutes</option>
              {filtres.annees.map((a) => <option key={a.id} value={a.id}>{a.libelle}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filiere" className={`${ETIQUETTE} block mb-0.5`}>Filière</label>
            <select id="rapport-filiere" value={filiereId} onChange={(e) => filtres.setFiliere(e.target.value)}
              disabled={filtres.anneeVide} className={CHAMP}>
              <option value="">{filtres.anneeVide ? 'Aucune' : 'Toutes'}</option>
              {filtres.filieres.map((f) => <option key={f.id} value={f.id}>{f.code}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-semestre" className={`${ETIQUETTE} block mb-0.5`}>Semestre</label>
            <select id="rapport-semestre" value={semestre} onChange={(e) => filtres.setSemestre(e.target.value)}
              disabled={!surEnsemble} className={CHAMP}>
              <option value="">Tous</option>
              {filtres.semestres.map((s) => <option key={s} value={s}>S{s}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-ue" className={`${ETIQUETTE} block mb-0.5`}>UE</label>
            <select id="rapport-ue" value={ueId} onChange={(e) => setUeId(e.target.value)} disabled={!surEnsemble} className={CHAMP}>
              <option value="">Toutes</option>
              {uesProposees.map((u) => <option key={u.id} value={u.id}>{u.code}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-ec" className={`${ETIQUETTE} block mb-0.5`}>EC</label>
            <select id="rapport-ec" value={ecId} onChange={(e) => setEcId(e.target.value)} disabled={!surEnsemble || !ueId} className={CHAMP}>
              <option value="">{ueId ? 'Tous' : 'Choisissez une UE'}</option>
              {ecs.map((e) => <option key={e.id} value={e.id}>{e.code || e.intitule}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-du" className={`${ETIQUETTE} block mb-0.5`}>Du</label>
            <input id="rapport-du" type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)}
              disabled={!surEnsemble} className={CHAMP} />
          </div>
          <div>
            <label htmlFor="rapport-au" className={`${ETIQUETTE} block mb-0.5`}>Au</label>
            <input id="rapport-au" type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)}
              disabled={!surEnsemble} className={CHAMP} />
          </div>
        </div>
      </section>

      {surEnsemble ? (
        <div role="tabpanel" id="panneau-ensemble" aria-labelledby="onglet-ensemble">
          {!data ? (
            loading ? (
              <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" aria-label="Chargement du rapport" /></div>
            ) : (
              <div className={`${CARTE} text-center py-16 text-sm text-on-surface-variant`}>
                Le rapport n'a pas pu être chargé. Réessayez avec « Actualiser ».
              </div>
            )
          ) : (
            <div className={`space-y-6 transition-opacity ${loading ? 'opacity-60' : ''}`} aria-busy={loading}>
              {/*  CHIFFRES CLÉS  */}
              {/* Le taux se calcule comme au tableau de bord : présences valides ÷
                  présences attendues (inscrits de chaque cours), sur les séances
                  terminées. */}
              <section aria-label="Chiffres clés" className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-[1.4fr_repeat(5,minmax(0,1fr))] gap-3">
                <Chiffre
                  className="col-span-2 md:col-span-1 shadow-sm"
                  libelle="Taux de présence"
                  valeur={d.presences_attendues ? libelleTaux(d.taux_global) : '—'}
                  couleur={d.presences_attendues ? couleurTaux(d.taux_global) : undefined}
                  detail={d.presences_attendues ? `${d.presences_valides} présents sur ${d.presences_attendues} attendus` : 'Aucune séance terminée'}
                />
                <Chiffre libelle="Séances terminées" valeur={d.total_evenements ?? 0} />
                <Chiffre libelle="Présents" valeur={d.presences_valides ?? 0} />
                <Chiffre
                  libelle="Absences"
                  valeur={d.absences ?? 0}
                  action={(d.absences ?? 0) > 0 && (
                    <button type="button" onClick={voirAbsents} className="text-primary hover:underline">Voir les étudiants ↓</button>
                  )}
                />
                <Chiffre
                  libelle="Suspects à arbitrer"
                  valeur={d.presences_suspectes ?? 0}
                  couleur={(d.presences_suspectes ?? 0) > 0 ? '#A65207' : undefined}
                  action={<Link to="/attendance/queue" className="text-primary hover:underline">File d'attente →</Link>}
                />
                <Chiffre className="col-span-2 md:col-span-1" libelle="Rejetés" valeur={d.presences_rejetees ?? 0} />
              </section>

              {/*  TENDANCE ET POINTS FAIBLES  */}
              <div className="grid grid-cols-1 lg:grid-cols-[1.25fr_1fr] gap-4 items-start">
                <section aria-labelledby="titre-semaines" className={`${CARTE} p-5`}>
                  <h2 id="titre-semaines" className="text-sm font-bold font-headline text-primary">Taux de présence par semaine</h2>
                  <p className="text-[11px] text-on-surface-variant mt-0.5 mb-3">
                    Du {formaterDate(dateDebut)} au {formaterDate(dateFin)} · une semaine sans séance terminée reste vide
                  </p>
                  {evolution.length > 0 ? (
                    <TauxHebdomadaireChart semaines={evolution} />
                  ) : (
                    <div className="h-[180px] flex items-center justify-center text-sm text-on-surface-variant">Aucune séance terminée sur la période</div>
                  )}
                </section>

                <section aria-labelledby="titre-ues-faibles" className={`${CARTE} p-5`}>
                  <h2 id="titre-ues-faibles" className="text-sm font-bold font-headline text-primary">UE les plus faibles</h2>
                  <p className="text-[11px] text-on-surface-variant mt-0.5 mb-4">
                    {uesAvecSeances.length} UE {uesAvecSeances.length > 1 ? 'ont' : 'a'} eu des séances sur la période · détail complet plus bas
                  </p>
                  {uesFaibles.length > 0 ? (
                    <ol className="space-y-3.5">
                      {uesFaibles.map((ue) => (
                        <li key={ue.ue_id} className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-1.5 items-baseline">
                          <span className="text-xs text-on-surface min-w-0 truncate" title={`${ue.code} · ${ue.intitule}`}>
                            <span className="font-mono text-[11px] text-on-surface-variant mr-1.5">{ue.code}</span>
                            {ue.intitule}
                          </span>
                          <span className="text-xs tabular-nums whitespace-nowrap">
                            <b style={{ color: couleurTaux(ue.taux) }}>{libelleTaux(ue.taux)}</b>
                            <span className="ml-1.5 text-on-surface-variant">{ue.total_presences} / {ue.presences_attendues}</span>
                          </span>
                          <BarreTaux taux={ue.taux} className="col-span-2" />
                        </li>
                      ))}
                    </ol>
                  ) : (
                    <div className="h-[180px] flex items-center justify-center text-sm text-on-surface-variant">Aucune UE avec des étudiants attendus</div>
                  )}
                </section>
              </div>

              {/*  ÉTUDIANTS  */}
              <section id="etudiants-absents" aria-labelledby="titre-absents" className={`${CARTE} p-5 scroll-mt-4`}>
                <h2 id="titre-absents" className="text-sm font-bold font-headline text-primary">Étudiants les plus absents</h2>
                <p className="text-[11px] text-on-surface-variant mt-0.5 mb-3">
                  Sur la période et les filtres choisis · un nom ouvre la fiche de présence de l'étudiant
                </p>
                <TableAbsents key={parametres} liste={absents} />
              </section>

              {/*  DÉTAIL  */}
              {statsParUe.length > 0 && <TableUe key={parametres} lignes={statsParUe} />}
            </div>
          )}
        </div>
      ) : (
        <div role="tabpanel" id="panneau-comparaisons" aria-labelledby="onglet-comparaisons">
          <ComparaisonsRapport anneeId={anneeId} anneeLibelle={anneeChoisie?.libelle} filiereId={filiereId} />
        </div>
      )}
    </div>
  );
};

export default ReportsPage;
