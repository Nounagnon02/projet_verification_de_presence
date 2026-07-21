import { useState, useEffect, useCallback, useMemo, memo } from 'react';
import {
  FiFilter, FiLoader, FiRefreshCw, FiBarChart2, FiCalendar, FiUsers,
  FiCheckCircle, FiAlertTriangle, FiDownload, FiFileText, FiChevronDown, FiChevronUp,
  FiSearch, FiChevronRight, FiArrowUp, FiArrowDown
} from 'react-icons/fi';
import api from '../../api/axios';
import BarChart from '../../components/charts/BarChart';
import GaugeChart from '../../components/charts/GaugeChart';
import useDebounce from '../../hooks/useDebounce';

const SEMESTRES = Array.from({ length: 10 }, (_, i) => ({ value: i + 1, label: `S${i + 1}` }));

const SortIcon = ({ col, ueSort }) => {
  if (ueSort.col !== col) return <span className="opacity-20 ml-1">↕</span>;
  return ueSort.dir === 'asc' ? <FiArrowUp className="inline ml-1" size={10} /> : <FiArrowDown className="inline ml-1" size={10} />;
};

const UeTable = memo(({ statsParUe, filieres, ueSemFilter, setUeSemFilter, ueFiliereFilter, setUeFiliereFilter,
  ueSearch, setUeSearch, debouncedUeSearch, uePage, setUePage, ueSort, handleUeSort, exportUeCSV, persistFilter, UE_PER_PAGE }) => {

  const semestresDispos = useMemo(() => [...new Set(statsParUe.map(u => u.semestre))].sort((a, b) => a - b), [statsParUe]);
  const filieresDispos = useMemo(() => [...new Map(statsParUe.filter(u => u.filiere_code).map(u => [u.filiere_code, { code: u.filiere_code, intitule: u.filiere_intitule || u.filiere_code }])).values()], [statsParUe]);

  const filtered = useMemo(() => {
    let rows = statsParUe;
    if (ueSemFilter) rows = rows.filter(u => String(u.semestre) === ueSemFilter);
    if (ueFiliereFilter) rows = rows.filter(u => u.filiere_code === ueFiliereFilter);
    if (debouncedUeSearch) {
      const q = debouncedUeSearch.toLowerCase();
      rows = rows.filter(u => (u.code || '').toLowerCase().includes(q) || (u.intitule || '').toLowerCase().includes(q));
    }
    return [...rows].sort((a, b) => {
      const dir = ueSort.dir === 'asc' ? 1 : -1;
      const col = ueSort.col;
      if (col === 'taux' || col === 'total_evenements' || col === 'total_presences' || col === 'semestre' || col === 'total_etudiants')
        return ((a[col] ?? 0) - (b[col] ?? 0)) * dir;
      return ((a[col] || '').localeCompare(b[col] || '')) * dir;
    });
  }, [statsParUe, ueSemFilter, ueFiliereFilter, debouncedUeSearch, ueSort]);

  const totaux = useMemo(() => ({
    seances: filtered.reduce((s, u) => s + (u.total_evenements || 0), 0),
    presences: filtered.reduce((s, u) => s + (u.total_presences || 0), 0),
    etudiants: filtered.reduce((s, u) => s + (u.total_etudiants || 0), 0),
    taux: filtered.length ? Math.round(filtered.reduce((s, u) => s + (u.taux || 0), 0) / filtered.length) : 0,
  }), [filtered]);

  const totalPages = Math.ceil(filtered.length / UE_PER_PAGE);
  const paginated = filtered.slice((uePage - 1) * UE_PER_PAGE, uePage * UE_PER_PAGE);

  const SortTh = ({ col, label, right }) => (
    <th className={`p-3 font-semibold cursor-pointer select-none hover:text-primary transition-colors ${right ? 'text-right' : ''}`}
      onClick={() => handleUeSort(col)}>
      {label}<SortIcon col={col} ueSort={ueSort} />
    </th>
  );

  return (
    <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden mb-6">
      {/* En-tête avec filtres */}
      <div className="p-4 border-b border-outline-variant/10 space-y-3">
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <h2 className="text-sm font-bold font-headline text-primary">Détail par UE</h2>
          <button onClick={() => exportUeCSV(filtered)}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-lg text-xs font-semibold hover:bg-primary/20 transition-all">
            <FiDownload size={12} /> Exporter CSV ({filtered.length})
          </button>
        </div>
        <div className="flex flex-wrap gap-2">
          {/* Recherche */}
          <div className="relative flex-1 min-w-[160px]">
            <FiSearch className="absolute left-2 top-1/2 -translate-y-1/2 text-on-surface-variant" size={12} />
            <input value={ueSearch} onChange={e => setUeSearch(e.target.value)}
              placeholder="Rechercher code ou intitulé..."
              className="w-full pl-7 pr-2 py-1.5 bg-surface-container-high rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-primary/30 text-on-surface" />
          </div>
          {/* Filtre semestre */}
          <select value={ueSemFilter} onChange={e => { setUeSemFilter(e.target.value); persistFilter('ue_sem', e.target.value); setUePage(1); }}
            className="px-2 py-1.5 bg-surface-container-high rounded-lg text-xs focus:outline-none text-on-surface">
            <option value="">Tous semestres</option>
            {semestresDispos.map(s => <option key={s} value={s}>S{s}</option>)}
          </select>
          {/* Filtre filière */}
          {filieresDispos.length > 0 && (
            <select value={ueFiliereFilter} onChange={e => { setUeFiliereFilter(e.target.value); persistFilter('ue_fil', e.target.value); setUePage(1); }}
              className="px-2 py-1.5 bg-surface-container-high rounded-lg text-xs focus:outline-none text-on-surface">
              <option value="">Toutes filières</option>
              {filieresDispos.map(f => <option key={f.code} value={f.code}>{f.code}</option>)}
            </select>
          )}
        </div>
      </div>

      {/* Tableau */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-on-surface-variant uppercase tracking-wider bg-surface-container-low/30">
              <SortTh col="code" label="Code" />
              <SortTh col="intitule" label="Intitulé" />
              <SortTh col="semestre" label="Semestre" right />
              <SortTh col="total_evenements" label="Séances" right />
              <SortTh col="total_presences" label="Présences" right />
              <SortTh col="total_etudiants" label="Étudiants" right />
              <SortTh col="taux" label="Taux" right />
            </tr>
          </thead>
          <tbody>
            {paginated.length === 0 ? (
              <tr><td colSpan={7} className="p-8 text-center text-on-surface-variant text-xs">Aucune UE ne correspond aux filtres</td></tr>
            ) : paginated.map((ue, i) => (
              <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-3 font-mono text-xs">{ue.code}</td>
                <td className="p-3">{ue.intitule}</td>
                <td className="p-3 text-right text-on-surface-variant">S{ue.semestre}</td>
                <td className="p-3 text-right">{ue.total_evenements}</td>
                <td className="p-3 text-right">{ue.total_presences}</td>
                <td className="p-3 text-right text-on-surface-variant">{ue.total_etudiants ?? '—'}</td>
                <td className="p-3 text-right font-bold" style={{ color: ue.taux >= 80 ? '#2E7D32' : ue.taux >= 50 ? '#F57F17' : '#C62828' }}>
                  {ue.taux}%
                </td>
              </tr>
            ))}
          </tbody>
          {/* Ligne totaux */}
          {filtered.length > 0 && (
            <tfoot>
              <tr className="border-t-2 border-outline-variant/20 bg-surface-container-low/50 text-xs font-bold text-on-surface-variant uppercase">
                <td className="p-3" colSpan={2}>{filtered.length} UE</td>
                <td className="p-3 text-right">—</td>
                <td className="p-3 text-right">{totaux.seances}</td>
                <td className="p-3 text-right">{totaux.presences}</td>
                <td className="p-3 text-right">{totaux.etudiants || '—'}</td>
                <td className="p-3 text-right" style={{ color: totaux.taux >= 80 ? '#2E7D32' : totaux.taux >= 50 ? '#F57F17' : '#C62828' }}>
                  moy. {totaux.taux}%
                </td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between px-4 py-3 border-t border-outline-variant/10">
          <span className="text-xs text-on-surface-variant">{filtered.length} UE · page {uePage}/{totalPages}</span>
          <div className="flex gap-1">
            <button onClick={() => setUePage(p => Math.max(1, p - 1))} disabled={uePage === 1}
              className="px-3 py-1 text-xs rounded-lg bg-surface-container-high hover:bg-surface-container-higher disabled:opacity-40 transition-all">‹ Préc</button>
            {Array.from({ length: totalPages }, (_, i) => i + 1).map(p => (
              <button key={p} onClick={() => setUePage(p)}
                className={`px-3 py-1 text-xs rounded-lg transition-all ${p === uePage ? 'bg-primary text-on-primary' : 'bg-surface-container-high hover:bg-surface-container-higher'}`}>{p}</button>
            ))}
            <button onClick={() => setUePage(p => Math.min(totalPages, p + 1))} disabled={uePage === totalPages}
              className="px-3 py-1 text-xs rounded-lg bg-surface-container-high hover:bg-surface-container-higher disabled:opacity-40 transition-all">Suiv ›</button>
          </div>
        </div>
      )}
    </div>
  );
});
UeTable.displayName = 'UeTable';

const ReportsPage = () => {
  //  FILTRES 
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const [ues, setUes] = useState([]);
  const [ecs, setEcs] = useState([]);

  const [filiereId, setFiliereId] = useState('');
  const [anneeId, setAnneeId] = useState('');
  const [semestre, setSemestre] = useState('');
  const [ueId, setUeId] = useState('');
  const [ecId, setEcId] = useState('');
  const [jours, setJours] = useState(30);
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');

  //  DONNEES 
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [initialLoading, setInitialLoading] = useState(true);
  const [exporting, setExporting] = useState(null);

  //Comparaisons
  const [semComp, setSemComp] = useState(null);
  const [filiereStats, setFiliereStats] = useState(null);
  const [yearStats, setYearStats] = useState(null);
  const [loadingSem, setLoadingSem] = useState(false);
  const [loadingFiliere, setLoadingFiliere] = useState(false);
  const [loadingYear, setLoadingYear] = useState(false);

  //Sélecteurs propres aux sections comparaisons
  const [semFiliereId, setSemFiliereId] = useState('');
  const [semAnneeId, setSemAnneeId] = useState('');
  const [compFiliereAnneeId, setCompFiliereAnneeId] = useState('');

  //Sections repliables
  const [showSemComp, setShowSemComp] = useState(false);
  const [showFiliereComp, setShowFiliereComp] = useState(false);
  const [showYearComp, setShowYearComp] = useState(false);

  //  CHARGEMENT INITIAL 
  useEffect(() => {
    const init = async () => {
      try {
        const [filRes, anRes, ueRes] = await Promise.all([
          api.get('/admin/filieres'),
          api.get('/admin/annees-academiques'),
          api.get('/admin/ues'),
        ]);
        const filList = filRes.data?.data || filRes.data || [];
        const anList = anRes.data?.data || anRes.data || [];
        const ueList = ueRes.data?.data || ueRes.data || [];
        if (Array.isArray(filList)) setFilieres(filList);
        if (Array.isArray(anList)) {
          setAnnees(anList);
          const active = anList.find(y => y.active);
          const activeId = active ? String(active.id) : '';
          setAnneeId(activeId);
          setSemAnneeId(activeId);
          setCompFiliereAnneeId(activeId);
        }
        if (Array.isArray(ueList)) setUes(ueList);
      } catch {
        // silencieux
      } finally {
        setInitialLoading(false);
      }
    };
    init();
  }, []);

  //ECs dynamiques
  useEffect(() => {
    if (!ueId) { setEcs([]); setEcId(''); return; }
    const ue = ues.find(u => String(u.id) === ueId);
    setEcs(ue?.ecs || []);
    setEcId('');
  }, [ueId, ues]);

  //  CHARGEMENT STATS FILTREES 
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = {};
      if (filiereId) params.filiere_id = filiereId;
      if (anneeId) params.annee_id = anneeId;
      if (semestre) params.semestre = semestre;
      if (ueId) params.ue_id = ueId;
      if (ecId) params.ec_id = ecId;
      if (jours) params.jours = jours;
      if (dateDebut) params.date_debut = dateDebut;
      if (dateFin) params.date_fin = dateFin;

      const { data: res } = await api.get('/admin/reports/filtered', { params });
      setData(res.data || res);
    } catch {
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [filiereId, anneeId, semestre, ueId, ecId, jours, dateDebut, dateFin]);

  const debouncedFiliereId = useDebounce(filiereId, 400);
  const debouncedAnneeId = useDebounce(anneeId, 400);
  const debouncedSemestre = useDebounce(semestre, 400);
  const debouncedUeId = useDebounce(ueId, 400);
  const debouncedEcId = useDebounce(ecId, 400);

  //Chargement auto au demarrage et à chaque changement de filtre (debounced)
  useEffect(() => {
    if (!initialLoading) { setUePage(1); loadData(); }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialLoading, debouncedFiliereId, debouncedAnneeId, debouncedSemestre, debouncedUeId, debouncedEcId]);

  //  COMPARAISON SEMESTRES 
  const loadSemesterComp = useCallback(async () => {
    if (!semFiliereId || !semAnneeId) return;
    setLoadingSem(true);
    try {
      const { data: res } = await api.get('/admin/reports/semester-comparison', {
        params: { filiere_id: semFiliereId, annee_id: semAnneeId },
      });
      setSemComp(res.data || res);
    } catch {
      setSemComp(null);
    } finally {
      setLoadingSem(false);
    }
  }, [semFiliereId, semAnneeId]);

  //  COMPARAISON FILIERES 
  const loadFiliereComp = useCallback(async () => {
    if (!compFiliereAnneeId) return;
    setLoadingFiliere(true);
    try {
      const { data: res } = await api.get('/admin/reports/filiere-stats', {
        params: { annee_id: compFiliereAnneeId },
      });
      const list = (res.data || res);
      if (Array.isArray(list)) {
        const sorted = list
          .sort((a, b) => (b.taux || 0) - (a.taux || 0))
          .map((f, i) => ({ ...f, rank: i + 1 }));
        setFiliereStats(sorted);
      } else {
        setFiliereStats([]);
      }
    } catch {
      setFiliereStats([]);
    } finally {
      setLoadingFiliere(false);
    }
  }, [compFiliereAnneeId]);

  //  COMPARAISON ANNEES 
  useEffect(() => {
    const fetch = async () => {
      setLoadingYear(true);
      try {
        const { data: anRes } = await api.get('/admin/annees-academiques');
        const anList = anRes.data || anRes;
        if (!Array.isArray(anList)) { setYearStats([]); setLoadingYear(false); return; }

        const yearData = await Promise.all(
          anList.map(async (a) => {
            try {
              const { data: res } = await api.get(`/admin/reports/semester/${a.id}`);
              const stats = res.data || res;
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
        setYearStats(yearData);
      } catch {
        setYearStats([]);
      } finally {
        setLoadingYear(false);
      }
    };
    fetch();
  }, []);

  //Chargement des comparaisons à l'ouverture
  useEffect(() => {
    if (showSemComp && semFiliereId && semAnneeId && !semComp && !loadingSem) loadSemesterComp();
  }, [showSemComp, semFiliereId, semAnneeId, semComp, loadingSem, loadSemesterComp]);

  useEffect(() => {
    if (showFiliereComp && compFiliereAnneeId && !filiereStats && !loadingFiliere) loadFiliereComp();
  }, [showFiliereComp, compFiliereAnneeId, filiereStats, loadingFiliere, loadFiliereComp]);

  //EXPORTS 
  const exportReport = async (type) => {
    setExporting(type);
    try {
      let url = '';
      let filename = '';
      switch (type) {
        case 'presences-csv':
          url = '/admin/reports/excel/export';
          filename = `presences_${Date.now()}.csv`;
          break;
        case 'filiere-pdf':
          url = filiereId ? `/admin/reports/department/${filiereId}` : '/admin/reports/department/1';
          filename = `rapport_filiere_${Date.now()}.pdf`;
          break;
        default:
          return;
      }
      const params = {};
      if (filiereId) params.filiere_id = filiereId;
      if (dateDebut) params.date_debut = dateDebut;
      if (dateFin) params.date_fin = dateFin;
      const { data: blobData } = await api.get(url, { params, responseType: 'blob' });
      const blob = new Blob([blobData]);
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = filename;
      link.click();
      URL.revokeObjectURL(link.href);
    } catch {
      // silencieux
    } finally {
      setExporting(null);
    }
  };

  // PAGINATION, FILTRES & TRI TABLEAU UE
  const [ueSemFilter, setUeSemFilter] = useState(() => sessionStorage.getItem('ue_sem') || '');
  const [ueFiliereFilter, setUeFiliereFilter] = useState(() => sessionStorage.getItem('ue_fil') || '');
  const [ueSearch, setUeSearch] = useState('');
  const [uePage, setUePage] = useState(1);
  const [ueSort, setUeSort] = useState(() => JSON.parse(sessionStorage.getItem('ue_sort') || '{"col":"semestre","dir":"asc"}'));
  const UE_PER_PAGE = 10;
  const debouncedUeSearch = useDebounce(ueSearch, 300);

  const persistFilter = (key, val) => sessionStorage.setItem(key, val);
  const persistSort = (sort) => sessionStorage.setItem('ue_sort', JSON.stringify(sort));

  const handleUeSort = (col) => {
    const next = ueSort.col === col && ueSort.dir === 'asc' ? { col, dir: 'desc' } : { col, dir: 'asc' };
    setUeSort(next); persistSort(next);
  };

  const exportUeCSV = (rows) => {
    const header = 'Code,Intitulé,Semestre,Séances,Présences,Étudiants,Taux';
    const lines = rows.map(u =>
      `${u.code},"${u.intitule}",S${u.semestre},${u.total_evenements},${u.total_presences},${u.total_etudiants ?? ''},${u.taux}%`
    );
    const blob = new Blob([header + '\n' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `detail_ue_${Date.now()}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  };

  //  HELPERS (mémoïsés)
  const d = data || {};
  const evolution = useMemo(() => Array.isArray(d.evolution) ? d.evolution : [], [d.evolution]);
  const statsParUe = useMemo(() => Array.isArray(d.stats_par_ue) ? d.stats_par_ue : [], [d.stats_par_ue]);

  const chartData = useMemo(() => evolution.map(e => ({
    label: typeof e.date === 'string' ? e.date.slice(5, 10) : '',
    value: e.total || 0,
  })), [evolution]);

  const ueChartData = useMemo(() => statsParUe.map(ue => ({
    label: ue.code || '',
    value: ue.taux || 0,
    name: ue.intitule || '',
  })), [statsParUe]);

  const resetFilters = () => {
    setFiliereId(''); setAnneeId(''); setSemestre('');
    setUeId(''); setEcId('');
    setJours(30); setDateDebut(''); setDateFin('');
  };

  //  RENDU 
  if (initialLoading) {
    return <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  }

  return (
    <div>
      {/*  EN-TETE  */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Rapports de Présence</h1>
          
        </div>
        <div className="flex gap-2">
          <button onClick={resetFilters}
            className="px-3 py-2 text-xs font-semibold text-on-surface-variant bg-surface-container-high rounded-xl hover:bg-surface-container-higher transition-all">
            Réinitialiser
          </button>
          <button onClick={loadData} disabled={loading}
            className="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-50">
            {loading ? <FiLoader className="animate-spin" /> : <FiRefreshCw />}
            Appliquer
          </button>
        </div>
      </div>

      {/*  FILTRES  */}
      <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10 mb-4">
        <div className="flex items-center gap-2 mb-3">
          <FiFilter className="text-primary" size={16} />
          <span className="text-sm font-bold text-primary">Filtres</span>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2.5">
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Filière</label>
            <select value={filiereId} onChange={e => setFiliereId(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
            </select>
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Année</label>
            <select value={anneeId} onChange={e => setAnneeId(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
            </select>
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Semestre</label>
            <select value={semestre} onChange={e => setSemestre(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Tous</option>
              {SEMESTRES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
            </select>
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">UE</label>
            <select value={ueId} onChange={e => setUeId(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {ues.map(u => <option key={u.id} value={u.id}>{u.code}</option>)}
            </select>
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">EC</label>
            <select value={ecId} onChange={e => setEcId(e.target.value)} disabled={!ueId}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface disabled:opacity-40">
              <option value="">Tous</option>
              {ecs.map(e => <option key={e.id} value={e.id}>{e.code || e.intitule}</option>)}
            </select>
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Jours</label>
            <input type="number" min="1" max="365" value={jours}
              onChange={e => setJours(Math.max(1, parseInt(e.target.value) || 30))}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Du</label>
            <input type="date" value={dateDebut} onChange={e => setDateDebut(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Au</label>
            <input type="date" value={dateFin} onChange={e => setDateFin(e.target.value)}
              className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
        </div>
      </div>

      {/*  CONTENU PRINCIPAL  */}
      {loading ? (
        <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>
      ) : !data ? (
        <div className="text-center py-16 text-on-surface-variant bg-surface-container-lowest rounded-2xl border border-outline-variant/10 mb-6">
          <FiBarChart2 className="mx-auto text-4xl mb-3 opacity-40" />
          <p>Appliquez des filtres pour voir les données.</p>
        </div>
      ) : (
        <>
          {/*  KPIS  */}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Taux Global</p>
              <p className="text-2xl font-bold font-headline" style={{ color: d.taux_global >= 80 ? '#2E7D32' : d.taux_global >= 50 ? '#F57F17' : '#C62828' }}>
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

          {/*  EXPORTS  */}
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
          </div>

          {/*  GRAPHIQUES  */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
              <h2 className="text-sm font-bold font-headline text-primary mb-3">Évolution ({jours} jours)</h2>
              <p className="text-[10px] text-on-surface-variant mb-2">Modifiez le nombre de jours dans les filtres</p>
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

          {/*  JAUGE  */}
          {(d.taux_global ?? null) !== null && (
            <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10 mb-6 flex flex-col items-center">
              <h2 className="text-sm font-bold font-headline text-primary mb-3">Taux Global de Présence</h2>
              <GaugeChart value={d.taux_global} max={100} size={160} label="Présence" />
            </div>
          )}

          {/*  TABLEAU UE  */}
          {statsParUe.length > 0 && (
            <UeTable
              statsParUe={statsParUe}
              filieres={filieres}
              ueSemFilter={ueSemFilter} setUeSemFilter={setUeSemFilter}
              ueFiliereFilter={ueFiliereFilter} setUeFiliereFilter={setUeFiliereFilter}
              ueSearch={ueSearch} setUeSearch={setUeSearch}
              debouncedUeSearch={debouncedUeSearch}
              uePage={uePage} setUePage={setUePage}
              ueSort={ueSort} handleUeSort={handleUeSort}
              exportUeCSV={exportUeCSV}
              persistFilter={persistFilter}
              UE_PER_PAGE={UE_PER_PAGE}
            />
          )}
        </>
      )}

      {/* =*/}
      {/*  SECTIONS COMPARAISONS  */}
      {/* =*/}

      <div className="space-y-4 mt-6 border-t border-outline-variant/10 pt-6">
        <h2 className="text-lg font-bold text-primary font-headline">Comparaisons</h2>

        {/*1. Comparaison Semestrielle*/}
        <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden">
          <button onClick={() => setShowSemComp(!showSemComp)}
            className="flex items-center justify-between w-full px-5 py-3.5 hover:bg-surface-container-high/50 transition-all">
            <div className="text-left">
              <span className="font-bold text-primary text-sm">Comparaison Semestrielle</span>
              <p className="text-[11px] text-on-surface-variant mt-0.5">Comparez les taux de présence entre semestres</p>
            </div>
            {showSemComp ? <FiChevronUp className="text-primary" /> : <FiChevronDown className="text-primary" />}
          </button>

          {showSemComp && (
            <div className="px-5 pb-5 border-t border-outline-variant/10 pt-4">
              {/* Sélecteurs propres à la section */}
              <div className="flex flex-wrap gap-3 mb-4">
                <div className="w-48">
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Filière</label>
                  <select value={semFiliereId} onChange={e => setSemFiliereId(e.target.value)}
                    className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
                    <option value="">Sélectionner</option>
                    {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
                  </select>
                </div>
                <div className="w-48">
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Année</label>
                  <select value={semAnneeId} onChange={e => setSemAnneeId(e.target.value)}
                    className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
                    <option value="">Sélectionner</option>
                    {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
                  </select>
                </div>
                <div className="self-end">
                  <button onClick={loadSemesterComp} disabled={!semFiliereId || !semAnneeId || loadingSem}
                    className="px-3 py-1.5 bg-primary text-on-primary rounded-lg text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-40 flex items-center gap-1.5">
                    {loadingSem ? <FiLoader className="animate-spin" /> : <FiRefreshCw />}
                    Charger
                  </button>
                </div>
              </div>

              {loadingSem ? (
                <div className="flex justify-center p-6"><FiLoader className="animate-spin text-primary w-6 h-6" /></div>
              ) : !semFiliereId || !semAnneeId ? (
                <p className="text-on-surface-variant text-sm py-4 text-center">Sélectionnez une filière et une année pour voir la comparaison.</p>
              ) : semComp?.semestres?.length > 0 ? (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <BarChart data={semComp.semestres.map(s => ({ label: s.label, value: s.taux }))} bars="value" height={200} />
                  <div className="space-y-2">
                    {semComp.semestres.map(s => (
                      <div key={s.semestre} className="flex items-center justify-between p-3 bg-surface-container-high rounded-lg">
                        <span className="font-bold text-primary text-sm">{s.label}</span>
                        <span className={`font-bold ${s.taux >= 80 ? 'text-success' : s.taux >= 50 ? 'text-warning' : 'text-error'}`}>
                          {s.taux}% <span className="text-xs text-on-surface-variant font-normal">({s.total_presences} prés.)</span>
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="text-on-surface-variant text-sm py-4 text-center">Aucune donnée de semestre disponible pour cette sélection.</p>
              )}
            </div>
          )}
        </div>

        {/*2. Comparaison Filières*/}
        <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden">
          <button onClick={() => setShowFiliereComp(!showFiliereComp)}
            className="flex items-center justify-between w-full px-5 py-3.5 hover:bg-surface-container-high/50 transition-all">
            <div className="text-left">
              <span className="font-bold text-primary text-sm">Comparaison Filières (classement)</span>
              <p className="text-[11px] text-on-surface-variant mt-0.5">Classement des filières par taux de présence</p>
            </div>
            {showFiliereComp ? <FiChevronUp className="text-primary" /> : <FiChevronDown className="text-primary" />}
          </button>

          {showFiliereComp && (
            <div className="px-5 pb-5 border-t border-outline-variant/10 pt-4">
              <div className="flex flex-wrap gap-3 mb-4">
                <div className="w-48">
                  <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-0.5">Année</label>
                  <select value={compFiliereAnneeId} onChange={e => setCompFiliereAnneeId(e.target.value)}
                    className="w-full px-2 py-1.5 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
                    <option value="">Sélectionner</option>
                    {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
                  </select>
                </div>
                <div className="self-end">
                  <button onClick={loadFiliereComp} disabled={!compFiliereAnneeId || loadingFiliere}
                    className="px-3 py-1.5 bg-primary text-on-primary rounded-lg text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-40 flex items-center gap-1.5">
                    {loadingFiliere ? <FiLoader className="animate-spin" /> : <FiRefreshCw />}
                    Charger
                  </button>
                </div>
              </div>

              {loadingFiliere ? (
                <div className="flex justify-center p-6"><FiLoader className="animate-spin text-primary w-6 h-6" /></div>
              ) : !compFiliereAnneeId ? (
                <p className="text-on-surface-variant text-sm py-4 text-center">Sélectionnez une année pour voir le classement.</p>
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
                          <td className="p-2 text-right font-bold" style={{ color: f.taux >= 80 ? '#2E7D32' : f.taux >= 50 ? '#F57F17' : '#C62828' }}>
                            {f.taux}%
                          </td>
                          <td className="p-2 text-right text-on-surface-variant">{f.total_presences}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-on-surface-variant text-sm py-4 text-center">Aucune donnée disponible.</p>
              )}
            </div>
          )}
        </div>

        {/*3. Comparaison Années*/}
        <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden">
          <button onClick={() => setShowYearComp(!showYearComp)}
            className="flex items-center justify-between w-full px-5 py-3.5 hover:bg-surface-container-high/50 transition-all">
            <div className="text-left">
              <span className="font-bold text-primary text-sm">Comparaison Années Académiques</span>
              <p className="text-[11px] text-on-surface-variant mt-0.5">Évolution des présences sur plusieurs années</p>
            </div>
            {showYearComp ? <FiChevronUp className="text-primary" /> : <FiChevronDown className="text-primary" />}
          </button>

          {showYearComp && (
            <div className="px-5 pb-5 border-t border-outline-variant/10 pt-4">
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
                      <div key={i} className="flex items-center justify-between p-3 bg-surface-container-high rounded-lg">
                        <span className="font-bold text-primary text-sm">{y.year}</span>
                        <span className={`font-bold ${y.rate >= 80 ? 'text-success' : y.rate >= 50 ? 'text-warning' : 'text-error'}`}>
                          {y.rate}%
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="text-on-surface-variant text-sm py-4 text-center">Les données de présence ne sont disponibles que pour l'année active.</p>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ReportsPage;
