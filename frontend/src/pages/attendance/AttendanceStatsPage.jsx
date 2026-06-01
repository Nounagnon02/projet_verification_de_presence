import { useState } from 'react';
import { FiBarChart2, FiCalendar, FiDownload, FiUsers, FiCheckCircle, FiX, FiActivity, FiLoader } from 'react-icons/fi';
import useApi from '../../hooks/useApi';

const AttendanceStatsPage = () => {
  const [period, setPeriod] = useState('weekly');
  const { data: stats, loading } = useApi('/admin/presence/stats');

  const s = stats || {};

  const global = s.taux_global || '—';
  const presentCount = s.total_presences || 0;
  const absences = s.total_evenements && s.total_etudiants
    ? Math.max(0, (s.total_evenements * s.total_etudiants) - s.total_presences)
    : 0;

  const weeklyData = Array.isArray(s.presences_par_jour)
    ? s.presences_par_jour.slice(-7).map(d => ({
        day: new Date(d.date).toLocaleDateString('fr-FR', { weekday: 'short' }).replace('.', ''),
        rate: d.total_evenements ? Math.round((d.valides / d.total_evenements) * 100) : 0,
      }))
    : [];

  const courses = Array.isArray(s.stats_par_filiere)
    ? s.stats_par_filiere.map(f => ({
        name: f.intitule || f.code || 'N/A',
        rate: s.total_presences > 0 ? Math.round((parseInt(f.total_presences) / s.total_presences) * 100) : 0,
        present: parseInt(f.total_presences) || 0,
        absent: 0,
        total: s.total_presences || 0,
      }))
    : [];

  const periodLabels = { weekly: 'Cette semaine', monthly: 'Ce mois', yearly: 'Cette année' };

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Statistiques d'Assiduité</h1>
          <p className="text-sm text-on-surface-variant">{periodLabels[period]}</p>
        </div>
        <div className="flex items-center gap-3">
          <div className="flex bg-surface-container-high rounded-xl p-1">
            {['weekly', 'monthly', 'yearly'].map((p) => (
              <button key={p} onClick={() => setPeriod(p)}
                className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${period === p ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
                {p === 'weekly' ? 'Semaine' : p === 'monthly' ? 'Mois' : 'Année'}
              </button>
            ))}
          </div>
          <button className="flex items-center gap-2 px-4 py-2 bg-surface-container-low rounded-xl text-sm text-on-surface-variant hover:bg-surface-container-high transition-colors">
            <FiDownload /> Exporter
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <FiLoader className="animate-spin text-primary w-8 h-8" />
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            {[
              { label: 'Taux global', value: typeof global === 'number' ? `${global}%` : global, icon: <FiActivity />, color: 'text-primary' },
              { label: 'Présences', value: presentCount, icon: <FiCheckCircle />, color: 'text-secondary' },
              { label: 'Absences', value: absences, icon: <FiX />, color: 'text-error' },
              { label: 'Événements', value: s.total_evenements || 0, icon: <FiCalendar />, color: 'text-tertiary' },
              { label: 'Étudiants', value: s.total_etudiants || 0, icon: <FiUsers />, color: 'text-on-surface-variant' },
            ].map((kpi, i) => (
              <div key={i} className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
                <div className={`${kpi.color} mb-2`}>{kpi.icon}</div>
                <p className="text-2xl font-bold text-on-surface">{kpi.value}</p>
                <p className="text-xs text-on-surface-variant mt-1">{kpi.label}</p>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div className="lg:col-span-2 bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-sm font-bold text-primary mb-6">Taux de présence par jour</h2>
              <div className="flex items-end justify-around h-48 gap-4">
                {weeklyData.length > 0 ? weeklyData.slice(0, 7).map((d, i) => (
                  <div key={i} className="flex flex-col items-center gap-2 flex-1">
                    <span className="text-xs font-semibold" style={{ color: d.rate >= 85 ? '#006d43' : d.rate >= 70 ? '#1a2b5e' : '#ba1a1a' }}>{d.rate}%</span>
                    <div className="w-full bg-surface-container-high rounded-lg overflow-hidden" style={{ height: '120px' }}>
                      <div className="w-full bg-primary/20 rounded-lg transition-all duration-500" style={{ height: `${d.rate}%`, marginTop: `${100 - d.rate}%` }} />
                    </div>
                    <span className="text-[10px] text-on-surface-variant font-medium">{d.day}</span>
                  </div>
                )) : (
                  <p className="text-sm text-on-surface-variant">Aucune donnée disponible</p>
                )}
              </div>
            </div>

            <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-sm font-bold text-primary mb-6">Résumé par filière</h2>
              <div className="space-y-4">
                {courses.length > 0 ? courses.map((c, i) => (
                  <div key={i}>
                    <div className="flex justify-between text-xs mb-1">
                      <span className="font-medium text-on-surface truncate">{c.name}</span>
                      <span className="font-semibold" style={{ color: c.rate >= 85 ? '#006d43' : c.rate >= 70 ? '#1a2b5e' : '#ba1a1a' }}>{c.rate}%</span>
                    </div>
                    <div className="h-1.5 bg-surface-container-high rounded-full overflow-hidden">
                      <div className="h-full bg-primary rounded-full" style={{ width: `${c.rate}%` }} />
                    </div>
                    <p className="text-[10px] text-on-surface-variant mt-0.5">{c.present}P</p>
                  </div>
                )) : (
                  <p className="text-sm text-on-surface-variant">Aucune donnée</p>
                )}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default AttendanceStatsPage;
