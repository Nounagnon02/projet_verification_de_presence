import { useState, useEffect, useCallback } from 'react';
import { FiFilter, FiLoader, FiRefreshCw, FiBarChart2, FiCalendar, FiUsers, FiCheckCircle, FiAlertTriangle } from 'react-icons/fi';
import api from '../../api/axios';
import BarChart from '../../components/charts/BarChart';
import GaugeChart from '../../components/charts/GaugeChart';

const TRIMESTRES = [
  { value: 1, label: 'T1 (Sep-Nov)' },
  { value: 2, label: 'T2 (Déc-Fév)' },
  { value: 3, label: 'T3 (Mar-Mai)' },
  { value: 4, label: 'T4 (Jun-Aoû)' },
];

const SEMESTRES = [
  { value: 1, label: 'S1' }, { value: 2, label: 'S2' },
  { value: 3, label: 'S3' }, { value: 4, label: 'S4' },
  { value: 5, label: 'S5' }, { value: 6, label: 'S6' },
  { value: 7, label: 'S7' }, { value: 8, label: 'S8' },
  { value: 9, label: 'S9' }, { value: 10, label: 'S10' },
];

export default function FilteredReportsPage() {
  // ---- Filtres ----
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const [ues, setUes] = useState([]);
  const [ecs, setEcs] = useState([]);

  const [filiereId, setFiliereId] = useState('');
  const [anneeId, setAnneeId] = useState('');
  const [semestre, setSemestre] = useState('');
  const [trimestre, setTrimestre] = useState('');
  const [ueId, setUeId] = useState('');
  const [ecId, setEcId] = useState('');
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');

  // ---- Données ----
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [initialLoading, setInitialLoading] = useState(true);

  // ---- Initialisation : charger listes de filtres ----
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
          if (active && !anneeId) setAnneeId(String(active.id));
        }
        if (Array.isArray(ueList)) setUes(ueList);
      } catch {
        // silencieux
      } finally {
        setInitialLoading(false);
      }
    };
    init();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // ---- Charger les ECs quand l'UE change ----
  useEffect(() => {
    if (!ueId) {
      setEcs([]);
      setEcId('');
      return;
    }
    const ue = ues.find(u => String(u.id) === ueId);
    if (ue?.ecs) {
      setEcs(ue.ecs);
    } else {
      setEcs([]);
      setEcId('');
    }
  }, [ueId, ues]);

  // ---- Charger les stats filtrées ----
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = {};
      if (filiereId) params.filiere_id = filiereId;
      if (anneeId) params.annee_id = anneeId;
      if (semestre) params.semestre = semestre;
      if (trimestre) params.trimestre = trimestre;
      if (ueId) params.ue_id = ueId;
      if (ecId) params.ec_id = ecId;
      if (dateDebut) params.date_debut = dateDebut;
      if (dateFin) params.date_fin = dateFin;

      const { data: res } = await api.get('/admin/reports/filtered', { params });
      setData(res.data || res);
    } catch {
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [filiereId, anneeId, semestre, trimestre, ueId, ecId, dateDebut, dateFin]);

  // ---- Chargement initial auto ----
  useEffect(() => {
    if (!initialLoading) {
      loadData();
    }
  }, [initialLoading, loadData]);

  // ---- Helpers ----
  const d = data || {};
  const evolution = d.evolution && Array.isArray(d.evolution) ? d.evolution : [];
  const statsParUe = d.stats_par_ue && Array.isArray(d.stats_par_ue) ? d.stats_par_ue : [];

  const chartData = evolution.map(e => ({
    label: typeof e.date === 'string' ? e.date.slice(5, 10) : '',
    value: e.total || 0,
  }));

  const ueChartData = statsParUe.map(ue => ({
    label: ue.code || '',
    value: ue.taux || 0,
    name: ue.intitule || '',
  }));

  // ---- Reset ----
  const resetFilters = () => {
    setFiliereId('');
    setAnneeId('');
    setSemestre('');
    setTrimestre('');
    setUeId('');
    setEcId('');
    setDateDebut('');
    setDateFin('');
  };

  if (initialLoading) {
    return <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  }

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Rapports Filtrés</h1>
          <p className="text-sm text-on-surface-variant">Analyse des présences par filière, semestre, UE/EC</p>
        </div>
        <div className="flex gap-2">
          <button onClick={resetFilters}
            className="px-4 py-2 text-xs font-semibold text-on-surface-variant bg-surface-container-high rounded-xl hover:bg-surface-container-higher transition-all">
            Réinitialiser
          </button>
          <button onClick={loadData} disabled={loading}
            className="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-50">
            {loading ? <FiLoader className="animate-spin" /> : <FiRefreshCw />}
            Appliquer
          </button>
        </div>
      </div>

      {/* ---- Barre de filtres ---- */}
      <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10 mb-6">
        <div className="flex items-center gap-2 mb-4">
          <FiFilter className="text-primary" size={16} />
          <span className="text-sm font-bold text-primary">Filtres</span>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
          {/* Filière */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">Filière</label>
            <select value={filiereId} onChange={e => setFiliereId(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
            </select>
          </div>

          {/* Année académique */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">Année</label>
            <select value={anneeId} onChange={e => setAnneeId(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
            </select>
          </div>

          {/* Semestre */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">Semestre</label>
            <select value={semestre} onChange={e => setSemestre(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Tous</option>
              {SEMESTRES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
            </select>
          </div>

          {/* Trimestre */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">Trimestre</label>
            <select value={trimestre} onChange={e => setTrimestre(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Tous</option>
              {TRIMESTRES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
            </select>
          </div>

          {/* UE */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">UE</label>
            <select value={ueId} onChange={e => setUeId(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface">
              <option value="">Toutes</option>
              {ues.map(u => <option key={u.id} value={u.id}>{u.code}</option>)}
            </select>
          </div>

          {/* EC */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">EC</label>
            <select value={ecId} onChange={e => setEcId(e.target.value)} disabled={!ueId}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface disabled:opacity-40">
              <option value="">Tous</option>
              {ecs.map(e => <option key={e.id} value={e.id}>{e.code || e.intitule}</option>)}
            </select>
          </div>

          {/* Date début */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">Du</label>
            <input type="date" value={dateDebut} onChange={e => setDateDebut(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>

          {/* Date fin */}
          <div>
            <label className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant block mb-1">Au</label>
            <input type="date" value={dateFin} onChange={e => setDateFin(e.target.value)}
              className="w-full px-2.5 py-2 bg-surface-container-high rounded-lg border-b-2 border-transparent focus:border-primary text-xs focus:outline-none text-on-surface" />
          </div>
        </div>
      </div>

      {/* ---- Contenu : loading ou données ---- */}
      {loading ? (
        <div className="flex justify-center p-16"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>
      ) : !data ? (
        <div className="text-center py-16 text-on-surface-variant bg-surface-container-lowest rounded-2xl border border-outline-variant/10">
          <FiBarChart2 className="mx-auto text-4xl mb-3 opacity-40" />
          <p>Appliquez des filtres pour voir les données.</p>
        </div>
      ) : (
        <>
          {/* ---- KPIs ---- */}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Taux Global</p>
              <p className="text-2xl font-bold font-headline" style={{ color: d.taux_global >= 80 ? '#2E7D32' : d.taux_global >= 50 ? '#F57F17' : '#C62828' }}>
                {d.taux_global ?? '—'}%
              </p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">
                <FiUsers className="inline mr-1" />Présences
              </p>
              <p className="text-2xl font-bold font-headline text-primary">{d.total_presences ?? '—'}</p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">
                <FiCalendar className="inline mr-1" />Séances
              </p>
              <p className="text-2xl font-bold font-headline text-primary">{d.total_evenements ?? '—'}</p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">
                <FiCheckCircle className="inline mr-1" />Valides
              </p>
              <p className="text-2xl font-bold font-headline text-success">{d.presences_valides ?? '—'}</p>
            </div>
            <div className="bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
              <p className="text-[10px] text-on-surface-variant font-semibold uppercase tracking-wider mb-1">
                <FiAlertTriangle className="inline mr-1" />Suspectes
              </p>
              <p className="text-2xl font-bold font-headline" style={{ color: (d.presences_suspectes || 0) > 0 ? '#C62828' : '#2E7D32' }}>
                {d.presences_suspectes ?? 0}
              </p>
            </div>
          </div>

          {/* ---- Graphiques ---- */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {/* Évolution */}
            <div className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
              <h2 className="text-sm font-bold font-headline text-primary mb-4">Évolution (30 jours)</h2>
              {chartData.length > 0 ? (
                <BarChart data={chartData.slice(-20)} bars="value" height={180} />
              ) : (
                <div className="h-[180px] flex items-center justify-center text-on-surface-variant text-sm">Aucune donnée récente</div>
              )}
            </div>

            {/* Stats par UE */}
            <div className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
              <h2 className="text-sm font-bold font-headline text-primary mb-4">Taux par UE</h2>
              {ueChartData.length > 0 ? (
                <BarChart data={ueChartData} bars="value" height={180} />
              ) : (
                <div className="h-[180px] flex items-center justify-center text-on-surface-variant text-sm">Aucune UE trouvée</div>
              )}
            </div>
          </div>

          {/* ---- Tableau détaillé par UE ---- */}
          {statsParUe.length > 0 && (
            <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden">
              <div className="p-4 border-b border-outline-variant/10">
                <h2 className="text-sm font-bold font-headline text-primary">Détail par UE</h2>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-on-surface-variant uppercase tracking-wider">
                      <th className="p-4 font-semibold">Code</th>
                      <th className="p-4 font-semibold">Intitulé</th>
                      <th className="p-4 font-semibold text-right">Semestre</th>
                      <th className="p-4 font-semibold text-right">Séances</th>
                      <th className="p-4 font-semibold text-right">Présences</th>
                      <th className="p-4 font-semibold text-right">Taux</th>
                    </tr>
                  </thead>
                  <tbody>
                    {statsParUe.map((ue, i) => (
                      <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                        <td className="p-4 font-mono text-xs">{ue.code}</td>
                        <td className="p-4">{ue.intitule}</td>
                        <td className="p-4 text-right text-on-surface-variant">S{ue.semestre}</td>
                        <td className="p-4 text-right">{ue.total_evenements}</td>
                        <td className="p-4 text-right">{ue.total_presences}</td>
                        <td className="p-4 text-right font-bold" style={{ color: ue.taux >= 80 ? '#2E7D32' : ue.taux >= 50 ? '#F57F17' : '#C62828' }}>
                          {ue.taux}%
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
