import { useState, useEffect } from 'react';
import { FiDownload, FiFileText, FiBarChart2, FiCalendar, FiUsers, FiLoader } from 'react-icons/fi';
import { Link } from 'react-router-dom';
import api from '../../api/axios';
import BarChart from '../../components/charts/BarChart';
import GaugeChart from '../../components/charts/GaugeChart';

const ReportsPage = () => {
  const [period, setPeriod] = useState('yearly');
  const [generating, setGenerating] = useState(null);
  const [stats, setStats] = useState(null);
  const [trend, setTrend] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [statsRes, trendRes] = await Promise.all([
          api.get('/admin/dashboard'),
          api.get('/admin/dashboard/attendance-trend'),
        ]);

        const s = statsRes.data?.data || statsRes.data;
        setStats(s);

        const t = trendRes.data?.data || trendRes.data;
        setTrend(Array.isArray(t) ? t : []);
      } catch {
        // Silencieux — les sections vides s'afficheront
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  const s = stats || {};
  const globalRate = s.taux_presence_global !== undefined ? s.taux_presence_global : null;

  // Données d'évolution par jour (30 derniers jours)
  const trendData = trend.map(t => ({
    label: typeof t.date === 'string' ? t.date?.slice(5, 10) : '',
    value: t.total || 0,
  }));

  const reports = [
    { id: 1, title: "Rapport d'assiduité global", desc: 'Taux de présence par cours et par étudiant', icon: <FiBarChart2 />, type: 'global' },
    { id: 2, title: 'Liste des présences', desc: 'Étudiants ayant validé leur présence', icon: <FiUsers />, type: 'presences' },
    { id: 3, title: 'Rapport par filière', desc: 'Assiduité détaillée par filière', icon: <FiFileText />, type: 'department' },
    { id: 4, title: 'Statistiques de la période', desc: 'Évolution de la présence dans le temps', icon: <FiCalendar />, type: 'weekly' },
  ];

  const exportReport = async (type) => {
    setGenerating(type);
    try {
      let url = '/admin/reports/presence/1/pdf';
      if (type === 'department') url = `/admin/reports/department/1`;
      const { data } = await api.get(url, { responseType: 'blob' });
      const blob = new Blob([data]);
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `rapport_${type}_${Date.now()}.pdf`;
      link.click();
      URL.revokeObjectURL(link.href);
    } catch {
      // Erreur silencieuse
    } finally {
      setGenerating(null);
    }
  };

  const periodLabels = { weekly: 'Cette semaine', monthly: 'Ce mois', yearly: 'Cette année' };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <FiLoader className="animate-spin text-primary w-8 h-8" />
      </div>
    );
  }

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Rapports</h1>
          <p className="text-sm text-on-surface-variant">Génération et suivi des présences</p>
        </div>
        <div className="flex bg-surface-container-high rounded-xl p-1">
          {['weekly', 'monthly', 'yearly'].map((p) => (
            <button key={p} onClick={() => setPeriod(p)}
              className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${period === p ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
              {p === 'weekly' ? 'Semaine' : p === 'monthly' ? 'Mois' : 'Année'}
            </button>
          ))}
        </div>
      </div>

      {/* Cartes de statistiques clés */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
          <p className="text-xs text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Taux global</p>
          <p className="text-2xl font-bold font-headline text-primary">
            {globalRate !== null ? `${globalRate}%` : '—'}
          </p>
        </div>
        <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
          <p className="text-xs text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Étudiants</p>
          <p className="text-2xl font-bold font-headline text-primary">{s.total_etudiants ?? '—'}</p>
        </div>
        <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
          <p className="text-xs text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Cours aujourd'hui</p>
          <p className="text-2xl font-bold font-headline text-primary">{s.cours_du_jour ?? '—'}</p>
        </div>
        <div className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10">
          <p className="text-xs text-on-surface-variant font-semibold uppercase tracking-wider mb-1">Alertes</p>
          <p className="text-2xl font-bold font-headline" style={{ color: (s.fraudes_suspectees || 0) > 0 ? '#C62828' : '#2E7D32' }}>
            {s.fraudes_suspectees ?? 0}
          </p>
        </div>
      </div>

      {/* Graphique d'évolution et jauge */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div className="lg:col-span-2 bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
          <h2 className="text-sm font-bold font-headline text-primary mb-4">Évolution des Présences (30 jours)</h2>
          {trendData.length > 0 ? (
            <BarChart data={trendData.slice(-20)} bars="value" height={180} />
          ) : (
            <div className="h-[180px] flex items-center justify-center text-on-surface-variant text-sm">
              Données non disponibles
            </div>
          )}
        </div>

        <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10 flex flex-col items-center justify-center">
          <h2 className="text-sm font-bold font-headline text-primary mb-4">Taux Global</h2>
          {globalRate !== null ? (
            <GaugeChart value={globalRate} max={100} size={160} label="Présence" />
          ) : (
            <div className="h-[160px] flex items-center justify-center text-on-surface-variant text-sm">—</div>
          )}
        </div>
      </div>

      {/* Rapports exportables */}
      <h2 className="text-lg font-bold font-headline text-primary mb-4">Exports</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {reports.map((r) => (
          <div key={r.id} className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10 hover:shadow-md transition-all">
            <div className="flex items-start gap-4">
              <div className="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary shrink-0">
                {r.icon}
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="font-bold text-primary text-sm">{r.title}</h3>
                <p className="text-xs text-on-surface-variant mt-1">{r.desc}</p>
                <div className="flex items-center gap-3 mt-4">
                  <button onClick={() => exportReport(r.type)} disabled={generating === r.type}
                    className="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-50">
                    <FiDownload /> {generating === r.type ? 'Génération...' : 'PDF'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Liens vers les analyses détaillées */}
      <div className="mt-10">
        <h2 className="text-lg font-bold font-headline text-primary mb-4">Analyses détaillées</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Link to="/dashboard/reports/comparison/semester"
            className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10 hover:border-primary/30 hover:shadow-md transition-all block">
            <p className="font-bold text-primary text-sm mb-1">Comparaison Semestrielle</p>
            <p className="text-xs text-on-surface-variant">Comparez les taux de présence entre semestres par filière</p>
          </Link>
          <Link to="/dashboard/reports/comparison/filiere"
            className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10 hover:border-primary/30 hover:shadow-md transition-all block">
            <p className="font-bold text-primary text-sm mb-1">Comparaison Filières</p>
            <p className="text-xs text-on-surface-variant">Classement des filières par taux de présence</p>
          </Link>
          <Link to="/dashboard/reports/comparison/year"
            className="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/10 hover:border-primary/30 hover:shadow-md transition-all block">
            <p className="font-bold text-primary text-sm mb-1">Années Académiques</p>
            <p className="text-xs text-on-surface-variant">Évolution des présences sur plusieurs années</p>
          </Link>
        </div>
      </div>
    </div>
  );
};

export default ReportsPage;
