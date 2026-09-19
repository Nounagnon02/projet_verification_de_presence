import { useState, useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  FiFilter, FiLoader, FiRefreshCw, FiBarChart2, FiCalendar, FiUsers,
  FiCheckCircle, FiAlertTriangle, FiDownload, FiFileText, FiChevronDown, FiChevronUp,
  FiChevronLeft, FiChevronRight
} from 'react-icons/fi';
import { enregistrer, nomFichierServeur } from '../../utils/telechargement';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';
import BarChart from '../../components/charts/BarChart';
import GaugeChart from '../../components/charts/GaugeChart';
import { listerAnnees } from '../../api/resources/reference';
import {
  listerUes, rapportFiltre, rapportSemestre, rapportComparaisonSemestres, rapportFiliereStats,
  exporterPresencesCsv, exporterRapportDepartementPdf,
} from '../../api/resources/rapports';

const TRIMESTRES = [
  { value: 1, label: 'T1 (Sep-Nov)' },
  { value: 2, label: 'T2 (Déc-Fév)' },
  { value: 3, label: 'T3 (Mar-Mai)' },
  { value: 4, label: 'T4 (Jun-Aoû)' },
];


/**
 * En-tête de section repliable.
 *
 * Au niveau module : défini dans le composant de page, il était recréé à chaque
 * rendu et React remontait alors le bouton. Tout passe par les props.
 */
const SectionToggle = ({ open, setOpen, title, badge }) => (
  <button
    onClick={() => setOpen(!open)}
    className="flex items-center justify-between w-full px-4 py-2.5 bg-surface-container-high rounded-xl hover:bg-surface-container-higher transition-all text-sm font-bold text-primary"
  >
    <span>{title}</span>
    <span className="flex items-center gap-2 text-xs text-on-surface-variant font-normal">
      {badge && <span>{badge}</span>}
      {open ? <FiChevronUp /> : <FiChevronDown />}
    </span>
  </button>
);

export default function FilteredReportsPage() {
  // Filtres en cascade. Les noms locaux sont conservés : ils sont lus par les
  // paramètres de requête, les exports et les libellés de l'écran.
  const filtres = useFiltresAcademiques({ preselectionnerAnneeActive: true });
  const { annees, filieres } = filtres;
  const { annee: anneeId, filiere: filiereId, semestre } = filtres;
  const { setAnnee: setAnneeId, setFiliere: setFiliereId, setSemestre } = filtres;

  const [trimestre, setTrimestre] = useState('');
  const [ueId, setUeId] = useState('');
  const [ecId, setEcId] = useState('');
  const [jours, setJours] = useState(30);
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');

  //Données principales
  const [initialLoading, setInitialLoading] = useState(true);
  const [exporting, setExporting] = useState(null);
  const [exportError, setExportError] = useState('');

  //Sections repliables
  const [showSemComp, setShowSemComp] = useState(false);
  const [showFiliereComp, setShowFiliereComp] = useState(false);
  const [showYearComp, setShowYearComp] = useState(false);

  //Pagination UE
  const [uePage, setUePage] = useState(1);
  const UE_PER_PAGE = 10;

  //Chargement initial
  useEffect(() => {
    const init = async () => {
      try {
        // Filières et années viennent de useFiltresAcademiques ; les UEs, de
        // la requête qui suit l'année et la filière. Il ne reste rien à charger
        // ici, mais l'écran doit sortir de son état initial.
        await Promise.resolve();
      } catch {
        // silencieux
      } finally {
        setInitialLoading(false);
      }
    };
    init();
  }, []);

  // UEs de l'année et de la filière choisies. L'endpoint honore annee_id et
  // filiere_id depuis toujours ; la page ne les lui transmettait pas, si bien
  // que la liste des UEs mélangeait toutes les années.
  //
  // Tant que le hook n'a pas arrêté son année — il présélectionne l'année
  // active — interroger le serveur enverrait une requête sans filtre, aussitôt
  // remplacée par la bonne : la requête reste désactivée jusque-là.
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

  // ECs de l'UE choisie : une valeur calculée, pas un état à synchroniser.
  const ecs = useMemo(() => {
    if (!ueId) return [];
    return ues.find(u => String(u.id) === ueId)?.ecs || [];
  }, [ueId, ues]);

  // L'EC sélectionné se remet à zéro quand l'UE change, sinon on garderait un EC
  // qui n'appartient plus à l'UE affichée. Ajustement pendant le rendu — motif
  // documenté par React — plutôt que dans un effet, qui produirait un rendu
  // intermédiaire incohérent.
  const [ueIdPrecedent, setUeIdPrecedent] = useState(ueId);

  if (ueId !== ueIdPrecedent) {
    setUeIdPrecedent(ueId);
    setEcId('');
  }

  // Même raison un cran plus haut : changer d'année ou de filière peut retirer
  // l'UE choisie de la liste. La garder interrogerait le serveur sur une UE que
  // l'écran ne propose plus.
  if (ueId && ues.length > 0 && !ues.some(u => String(u.id) === ueId)) {
    setUeId('');
  }

  //Chargement stats filtrées
  //
  // Les filtres de cette page ne s'appliquent que sur demande (bouton
  // « Appliquer ») : les modifier ne relance rien. Les paramètres réellement
  // appliqués sont donc figés dans leur propre état, avec un numéro de version
  // qui garantit qu'un nouveau clic — même sans changement de filtre — relance
  // quand même la requête.
  const [appliedFiltres, setAppliedFiltres] = useState({ params: {}, version: 0 });

  const construireParamsFiltres = () => {
    const params = {};
    if (filiereId) params.filiere_id = filiereId;
    if (anneeId) params.annee_id = anneeId;
    if (semestre) params.semestre = semestre;
    if (trimestre) params.trimestre = trimestre;
    if (ueId) params.ue_id = ueId;
    if (ecId) params.ec_id = ecId;
    if (jours) params.jours = jours;
    if (dateDebut) params.date_debut = dateDebut;
    if (dateFin) params.date_fin = dateFin;
    return params;
  };

  const rapportQuery = useQuery({
    queryKey: ['rapport-filtre', appliedFiltres],
    queryFn: ({ signal }) => rapportFiltre(appliedFiltres.params, signal),
    enabled: !initialLoading,
  });
  const loading = rapportQuery.isFetching;
  const data = rapportQuery.isError ? null : (rapportQuery.data?.data ?? rapportQuery.data ?? null);

  //Chargement comparaison années (au montage)
  const yearStatsQuery = useQuery({
    queryKey: ['rapport-annees-comparaison'],
    queryFn: async () => {
      const anRes = await listerAnnees();
      const anList = anRes?.data ?? anRes;
      if (!Array.isArray(anList)) return [];

      return Promise.all(
        anList.map(async (a) => {
          try {
            const stats = (await rapportSemestre(a.id))?.data ?? {};
            return {
              id: a.id,
              year: a.libelle || 'N/A',
              rate: stats.taux_presence ?? stats.taux_global ?? 0,
              students: stats.total_etudiants || 0,
              presences: stats.total_presences || 0,
              evenements: stats.total_evenements || 0,
              active: a.active || false,
            };
          } catch {
            return {
              id: a.id, year: a.libelle || 'N/A',
              rate: 0, students: 0, presences: 0, evenements: 0, active: a.active || false,
            };
          }
        })
      );
    },
  });
  const yearStats = yearStatsQuery.data ?? null;
  const loadingYear = yearStatsQuery.isLoading;

  // Comparaison par semestre, chargée à l'ouverture de la section.
  const semCompQuery = useQuery({
    queryKey: ['rapport-comparaison-semestres', filiereId, anneeId],
    queryFn: ({ signal }) => rapportComparaisonSemestres({ filiere_id: filiereId, annee_id: anneeId }, signal),
    enabled: showSemComp && Boolean(filiereId) && Boolean(anneeId),
  });
  const semComp = semCompQuery.data?.data ?? semCompQuery.data ?? null;
  const loadingSem = semCompQuery.isFetching;

  // Comparaison par filière, même principe.
  const filiereStatsQuery = useQuery({
    queryKey: ['rapport-filiere-stats', anneeId],
    queryFn: ({ signal }) => rapportFiliereStats({ annee_id: anneeId }, signal),
    enabled: showFiliereComp && Boolean(anneeId),
  });
  const filiereStatsBrutes = filiereStatsQuery.data?.data ?? filiereStatsQuery.data;
  const filiereStats = Array.isArray(filiereStatsBrutes)
    ? [...filiereStatsBrutes].sort((a, b) => (b.taux || 0) - (a.taux || 0)).map((f, i) => ({ ...f, rank: i + 1 }))
    : null;
  const loadingFiliere = filiereStatsQuery.isFetching;

  //Exports
  //
  // Chaque bouton porte desormais sur ce que l'utilisateur a reellement filtre.
  // Avant : l'export « global » pointait sur /reports/presence/1/pdf — la feuille
  // de presence de l'evenement d'identifiant 1, quel que soit le filtre — et
  // l'export filiere telechargeait la reponse JSON de /reports/department sous un
  // nom en .pdf, donc un fichier qu'aucun lecteur n'ouvrait.
  const exportReport = async (type) => {
    setExporting(type);
    setExportError('');
    try {
      let filename = '';
      let requete;

      switch (type) {
        case 'presences-csv':
          filename = `presences_${Date.now()}.csv`;
          // Le backend n'honore que ces trois filtres sur cet export.
          requete = exporterPresencesCsv({
            filiere_id: filiereId || undefined,
            date_debut: dateDebut || undefined,
            date_fin: dateFin || undefined,
          });
          break;

        case 'filiere-pdf':
          if (!filiereId) {
            setExportError('Sélectionnez une filière avant d\'exporter son rapport.');
            return;
          }
          filename = `rapport_filiere_${Date.now()}.pdf`;
          requete = exporterRapportDepartementPdf(filiereId);
          break;

        default:
          return;
      }

      const { data: blobData, headers } = await requete;
      // Le nom donné par le serveur résume les filtres ; le nom local n'est qu'un repli.
      enregistrer(blobData, nomFichierServeur(headers, filename));
    } catch {
      setExportError('L\'export a échoué. Réessayez dans un instant.');
    } finally {
      setExporting(null);
    }
  };

  //Helpers
  const d = data || {};
  const evolution = Array.isArray(d.evolution) ? d.evolution : [];
  const statsParUe = Array.isArray(d.stats_par_ue) ? d.stats_par_ue : [];

  // Pagination des UE
  const ueTotalPages = Math.max(1, Math.ceil(statsParUe.length / UE_PER_PAGE));
  const uePaginated = statsParUe.slice((uePage - 1) * UE_PER_PAGE, uePage * UE_PER_PAGE);

  // Évolution hebdomadaire du taux (voir ReportController::filteredStats).
  const chartData = evolution.map(s => ({
    label: typeof s.semaine === 'string' ? `${s.semaine.slice(8, 10)}/${s.semaine.slice(5, 7)}` : '',
    value: s.taux ?? 0,
  }));

  const ueChartData = statsParUe.map(ue => ({
    label: ue.code || '',
    value: ue.taux || 0,
    name: ue.intitule || '',
  }));

  const resetFilters = () => {
    setFiliereId(''); setAnneeId(''); setSemestre('');
    setTrimestre(''); setUeId(''); setEcId('');
    setJours(30); setDateDebut(''); setDateFin('');
    setUePage(1);
  };

  //Composant toggle pour une section

  if (initialLoading) {
    return <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  }

  return (
    <div>
      {/* ==En-tête ==*/}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Rapports de Présence</h1>
          <p className="text-sm text-on-surface-variant">
            Analyse, filtres, exports et comparaisons des présences
          </p>
        </div>
        <div className="flex gap-2">
          <button onClick={resetFilters}
            className="px-3 py-2 text-xs font-semibold text-on-surface-variant bg-surface-container-high rounded-xl hover:bg-surface-container-higher transition-all">
            Réinitialiser
          </button>
          <button
            onClick={() => setAppliedFiltres((prev) => ({ params: construireParamsFiltres(), version: prev.version + 1 }))}
            disabled={loading}
            className="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-50">
            {loading ? <FiLoader className="animate-spin" /> : <FiRefreshCw />}
            Appliquer
          </button>
        </div>
      </div>

      {/* ==Filtres ==*/}
      <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10 mb-4">
        <div className="flex items-center gap-2 mb-3">
          <FiFilter className="text-primary" size={16} />
          <span className="text-sm font-bold text-primary">Filtres</span>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-9 gap-2.5">
          <div>
            <label htmlFor="rapport-filtre-filiere" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Filière</label>
            <select id="rapport-filtre-filiere" value={filiereId} onChange={e => setFiliereId(e.target.value)}
              disabled={filtres.anneeVide}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface disabled:opacity-40">
              <option value="">{filtres.anneeVide ? 'Aucune' : 'Toutes'}</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filtre-annee" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Année</label>
            <select id="rapport-filtre-annee" value={anneeId} onChange={e => setAnneeId(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filtre-semestre" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Semestre</label>
            <select id="rapport-filtre-semestre" value={semestre} onChange={e => setSemestre(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Tous</option>
              {filtres.semestres.map(s => <option key={s} value={s}>S{s}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filtre-trimestre" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Trimestre</label>
            <select id="rapport-filtre-trimestre" value={trimestre} onChange={e => setTrimestre(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Tous</option>
              {TRIMESTRES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filtre-ue" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">UE</label>
            <select id="rapport-filtre-ue" value={ueId} onChange={e => setUeId(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {ues.map(u => <option key={u.id} value={u.id}>{u.code}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filtre-ec" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">EC</label>
            <select id="rapport-filtre-ec" value={ecId} onChange={e => setEcId(e.target.value)} disabled={!ueId}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface disabled:opacity-40">
              <option value="">Tous</option>
              {ecs.map(e => <option key={e.id} value={e.id}>{e.code || e.intitule}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="rapport-filtre-jours" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Jours</label>
            <input id="rapport-filtre-jours" type="number" min="1" max="365" value={jours}
              onChange={e => setJours(Math.max(1, parseInt(e.target.value) || 30))}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
          <div>
            <label htmlFor="rapport-filtre-du" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Du</label>
            <input id="rapport-filtre-du" type="date" value={dateDebut} onChange={e => setDateDebut(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
          <div>
            <label htmlFor="rapport-filtre-au" className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Au</label>
            <input id="rapport-filtre-au" type="date" value={dateFin} onChange={e => setDateFin(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
        </div>
      </div>

      {/* ==Loading / No data ==*/}
      {loading ? (
        <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>
      ) : !data ? (
        <div className="text-center py-16 text-on-surface-variant bg-surface-container-lowest rounded-2xl border border-outline-variant/10 mb-6">
          <FiBarChart2 className="mx-auto text-4xl mb-3 opacity-40" />
          <p>Appliquez des filtres pour voir les données.</p>
        </div>
      ) : (
        <>

          {/* ==KPIs ==*/}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Taux Global</p>
              <p className="text-2xl font-bold font-headline" style={{ color: d.taux_global >= 80 ? '#2E7D32' : d.taux_global >= 50 ? '#A65207' : '#C62828' }}>
                {d.taux_global ?? '—'}%
              </p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1"><FiUsers className="inline mr-1" />Présences</p>
              <p className="text-2xl font-bold font-headline text-primary">{d.total_presences ?? '—'}</p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1"><FiCalendar className="inline mr-1" />Séances</p>
              <p className="text-2xl font-bold font-headline text-primary">{d.total_evenements ?? '—'}</p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1"><FiCheckCircle className="inline mr-1" />Valides</p>
              <p className="text-2xl font-bold font-headline text-success">{d.presences_valides ?? '—'}</p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1"><FiAlertTriangle className="inline mr-1" />Suspectes</p>
              <p className="text-2xl font-bold font-headline" style={{ color: (d.presences_suspectes || 0) > 0 ? '#C62828' : '#2E7D32' }}>
                {d.presences_suspectes ?? 0}
              </p>
            </div>
          </div>

          {/* ==Exports ==*/}
          <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10 mb-6">
            <div className="flex items-center gap-2 mb-3">
              <FiDownload className="text-primary" size={16} />
              <span className="text-sm font-bold text-primary">Exports</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <button onClick={() => exportReport('presences-csv')} disabled={exporting}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-success/10 text-success rounded-lg text-xs font-semibold hover:bg-success/20 transition-all disabled:opacity-50">
                <FiFileText /> {exporting === 'presences-csv' ? '...' : 'Liste Présences CSV'}
              </button>
              <button onClick={() => exportReport('filiere-pdf')} disabled={exporting || !filiereId}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-warning/10 text-warning rounded-lg text-xs font-semibold hover:bg-warning/20 transition-all disabled:opacity-50">
                <FiFileText /> {exporting === 'filiere-pdf' ? '...' : 'Rapport Filière PDF'}
              </button>
            </div>
            {!filiereId && (
              <p className="text-[10px] text-on-surface-variant mt-2">
                Le rapport PDF porte sur une filière : sélectionnez-en une dans les filtres.
              </p>
            )}
            {exportError && (
              <p className="text-xs text-error font-medium mt-2">{exportError}</p>
            )}
          </div>

          {/* ==Graphiques ==*/}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
              <h2 className="text-sm font-bold font-headline text-primary mb-3">Évolution ({jours} jours)</h2>
              <p className="text-[10px] text-on-surface-variant mb-2">Nombre de jours modifiable dans les filtres</p>
              {chartData.length > 0 ? (
                <BarChart data={chartData.slice(-Math.min(jours, 60))} bars="value" height={180} />
              ) : (
                <div className="h-[180px] flex items-center justify-center text-on-surface-variant text-sm">Aucune donnée</div>
              )}
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
              <h2 className="text-sm font-bold font-headline text-primary mb-3">Taux par UE</h2>
              {ueChartData.length > 0 ? (
                <BarChart data={ueChartData} bars="value" height={180} />
              ) : (
                <div className="h-[180px] flex items-center justify-center text-on-surface-variant text-sm">Aucune UE</div>
              )}
            </div>
          </div>

          {/* ==Gauge ==*/}
          {(d.taux_global ?? null) !== null && (
            <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10 mb-6 flex flex-col items-center">
              <h2 className="text-sm font-bold font-headline text-primary mb-3">Taux Global de Présence</h2>
              <GaugeChart value={d.taux_global} max={100} size={160} label="Présence" />
            </div>
          )}

          {/* ==Tableau détail UE ==*/}
          {statsParUe.length > 0 && (
            <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden mb-6">
              <div className="p-4 border-b border-outline-variant/10 flex items-center justify-between">
                <div>
                  <h2 className="text-sm font-bold font-headline text-primary">Détail par UE</h2>
                  <p className="text-[10px] text-on-surface-variant mt-0.5">{statsParUe.length} UE{statsParUe.length > 1 ? 's' : ''}</p>
                </div>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-on-surface-variant uppercase tracking-wider">
                      <th className="p-3 font-semibold">Code</th>
                      <th className="p-3 font-semibold">Intitulé</th>
                      <th className="p-3 font-semibold text-right">Semestre</th>
                      <th className="p-3 font-semibold text-right">Séances</th>
                      <th className="p-3 font-semibold text-right">Présences</th>
                      <th className="p-3 font-semibold text-right">Taux</th>
                    </tr>
                  </thead>
                  <tbody>
                    {uePaginated.map((ue, i) => (
                      <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                        <td className="p-3 font-mono text-xs">{ue.code}</td>
                        <td className="p-3">{ue.intitule}</td>
                        <td className="p-3 text-right text-on-surface-variant">S{ue.semestre}</td>
                        <td className="p-3 text-right">{ue.total_evenements}</td>
                        <td className="p-3 text-right">{ue.total_presences}</td>
                        <td className="p-3 text-right font-bold" style={{ color: ue.taux >= 80 ? '#2E7D32' : ue.taux >= 50 ? '#A65207' : '#C62828' }}>
                          {ue.taux}%
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {/* Pagination UE */}
              {ueTotalPages > 1 && (
                <div className="flex items-center justify-between px-4 py-3 border-t border-outline-variant/10">
                  <p className="text-[10px] text-on-surface-variant">
                    Page {uePage} / {ueTotalPages} ({statsParUe.length} UE{statsParUe.length > 1 ? 's' : ''})
                  </p>
                  <div className="flex items-center gap-1">
                    <button onClick={() => setUePage(p => Math.max(1, p - 1))} disabled={uePage <= 1}
                      className="p-1.5 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                      <FiChevronLeft size={16} />
                    </button>
                    {Array.from({ length: Math.min(ueTotalPages, 5) }, (_, i) => {
                      const start = Math.max(1, Math.min(uePage - 2, ueTotalPages - 4));
                      const page = start + i;
                      if (page > ueTotalPages) return null;
                      return (
                        <button key={page} onClick={() => setUePage(page)}
                          className={`w-7 h-7 rounded-lg text-xs font-bold transition-all ${page === uePage ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container-high'}`}>
                          {page}
                        </button>
                      );
                    })}
                    <button onClick={() => setUePage(p => Math.min(ueTotalPages, p + 1))} disabled={uePage >= ueTotalPages}
                      className="p-1.5 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                      <FiChevronRight size={16} />
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      )}

      {/*  */}
      {/* ==SECTIONS COMPARAISONS (repliables) ==*/}
      {/*  */}

      <div className="space-y-3 mt-4">

        {/*Comparaison Semestrielle*/}
        <SectionToggle open={showSemComp} setOpen={setShowSemComp} title="Comparaison Semestrielle"
          badge={semComp?.semestres?.length ? `${semComp.semestres.length} semestres` : ''} />
        {showSemComp && (
          <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
            {!filiereId || !anneeId ? (
              <p className="text-on-surface-variant text-sm">Sélectionnez une filière et une année dans les filtres pour voir la comparaison.</p>
            ) : loadingSem ? (
              <div className="flex justify-center p-6"><FiLoader className="animate-spin text-primary w-6 h-6" /></div>
            ) : semComp?.semestres?.length > 0 ? (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <BarChart data={semComp.semestres.map(s => ({ label: s.label, value: s.taux }))} bars="value" height={200} />
                <div className="space-y-2">
                  {semComp.semestres.map(s => (
                    <div key={s.semestre} className="flex items-center justify-between p-2.5 bg-surface-container-high rounded-lg">
                      <span className="font-bold text-primary text-sm">{s.label}</span>
                      <span className={`font-bold ${s.taux >= 80 ? 'text-success' : s.taux >= 50 ? 'text-warning' : 'text-error'}`}>
                        {s.taux}% <span className="text-xs text-on-surface-variant font-normal">({s.total_presences} prés.)</span>
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-on-surface-variant text-sm">Aucune donnée de semestre disponible.</p>
            )}
          </div>
        )}

        {/*Comparaison Filières*/}
        <SectionToggle open={showFiliereComp} setOpen={setShowFiliereComp} title="Comparaison Filières (classement)"
          badge={filiereStats?.length ? `${filiereStats.length} filières` : ''} />
        {showFiliereComp && (
          <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
            {!anneeId ? (
              <p className="text-on-surface-variant text-sm">Sélectionnez une année dans les filtres pour voir le classement.</p>
            ) : loadingFiliere ? (
              <div className="flex justify-center p-6"><FiLoader className="animate-spin text-primary w-6 h-6" /></div>
            ) : filiereStats?.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-on-surface-variant uppercase tracking-wider">
                      <th className="p-2 font-semibold">Rang</th>
                      <th className="p-2 font-semibold">Filière</th>
                      <th className="p-2 font-semibold text-right">Niveau</th>
                      <th className="p-2 font-semibold text-right">Taux</th>
                      <th className="p-2 font-semibold text-right">Présences</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filiereStats.map((f, i) => (
                      <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-all">
                        <td className="p-2">
                          <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold ${i === 0 ? 'bg-success/20 text-success' : i < 3 ? 'bg-primary/20 text-primary' : 'bg-surface-container-high text-on-surface-variant'}`}>
                            #{f.rank}
                          </span>
                        </td>
                        <td className="p-2 font-medium">{f.intitule || f.code}</td>
                        <td className="p-2 text-right text-on-surface-variant">{f.niveau}</td>
                        <td className="p-2 text-right font-bold" style={{ color: f.taux >= 80 ? '#2E7D32' : f.taux >= 50 ? '#A65207' : '#C62828' }}>
                          {f.taux}%
                        </td>
                        <td className="p-2 text-right text-on-surface-variant">{f.total_presences}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-on-surface-variant text-sm">Aucune donnée de filière disponible.</p>
            )}
          </div>
        )}

        {/*Comparaison Années*/}
        <SectionToggle open={showYearComp} setOpen={setShowYearComp} title="Comparaison Années Académiques"
          badge={yearStats?.filter(y => y.rate > 0).length ? `${yearStats.filter(y => y.rate > 0).length} années` : ''} />
        {showYearComp && (
          <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
            {loadingYear ? (
              <div className="flex justify-center p-6"><FiLoader className="animate-spin text-primary w-6 h-6" /></div>
            ) : yearStats?.filter(y => y.rate > 0).length > 0 ? (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                  <h3 className="text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-3">Taux par Année</h3>
                  <BarChart data={yearStats.filter(y => y.rate > 0).map(y => ({ label: y.year, value: y.rate }))} bars="value" height={200} />
                </div>
                <div className="space-y-2">
                  {yearStats.filter(y => y.rate > 0).map((y, i) => (
                    <div key={i} className="flex items-center justify-between p-2.5 bg-surface-container-high rounded-lg">
                      <span className="font-bold text-primary text-sm">{y.year}</span>
                      <span className={`font-bold ${y.rate >= 80 ? 'text-success' : y.rate >= 50 ? 'text-warning' : 'text-error'}`}>
                        {y.rate}%
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-on-surface-variant text-sm">Les données de présence ne sont disponibles que pour l'année active.</p>
            )}
          </div>
        )}

      </div>
    </div>
  );
}
