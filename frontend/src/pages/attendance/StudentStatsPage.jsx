import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { FiCalendar, FiCheckCircle, FiX, FiClock, FiLoader } from 'react-icons/fi';
import GaugeChart from '../../components/charts/GaugeChart';
import ProgressBar from '../../components/charts/ProgressBar';
import Badge from '../../components/ui/Badge';
import Tabs from '../../components/ui/Tabs';
import useApi from '../../hooks/useApi';

export default function StudentStatsPage() {
  const { studentId } = useParams();
  const [period, setPeriod] = useState('monthly');
  const { data: stats, loading } = useApi(`/admin/students/${studentId}/stats`);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <FiLoader className="animate-spin text-primary w-8 h-8" />
      </div>
    );
  }

  const s = stats || {};
  const student = s.etudiant || {};
  const courseStats = Array.isArray(s.stats_par_cours) ? s.stats_par_cours : [];
  const history = Array.isArray(s.recent_history) ? s.recent_history : [];

  return (
    <div>
      {/* Student Header */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm mb-8">
        <div className="flex items-center gap-6">
          <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-2xl font-bold text-primary font-headline">
            {student.nom ? student.nom.charAt(0) : '?'}
          </div>
          <div>
            <h1 className="text-xl font-bold font-headline text-primary">
              {student.prenom || ''} {student.nom || ''}
            </h1>
            <p className="text-sm text-on-surface-variant font-mono">{student.matricule || '—'}</p>
            <div className="flex items-center gap-3 mt-1">
              <Badge variant="info">{student.filiere || 'N/A'}</Badge>
            </div>
          </div>
        </div>
      </div>

      {/* Gauge + Stats Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm flex flex-col items-center">
          <GaugeChart value={s.taux_presence || 0} label="Assiduité" size={180} />
        </div>

        <div className="lg:col-span-2 grid grid-cols-3 gap-4">
          {[
            { label: 'Présences', value: s.total_presences || 0, icon: FiCheckCircle, color: 'text-secondary' },
            { label: 'Absences', value: s.total_absences || 0, icon: FiX, color: 'text-warning' },
            { label: 'Total cours', value: s.total_evenements || 0, icon: FiClock, color: 'text-primary' },
          ].map((item, i) => (
            <div key={i} className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm">
              <item.icon className={`${item.color} mb-3`} size={20} />
              <p className="text-2xl font-bold font-headline text-primary">{item.value}</p>
              <p className="text-xs text-on-surface-variant">{item.label}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Performance by Course */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm mb-8">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-bold font-headline text-primary">Performance par Cours</h2>
          <Tabs
            tabs={[
              { key: 'weekly', label: 'Semaine' },
              { key: 'monthly', label: 'Mois' },
              { key: 'yearly', label: 'Année' },
            ]}
            activeTab={period}
            onChange={setPeriod}
          />
        </div>
        <div className="space-y-4">
          {courseStats.length > 0 ? courseStats.map((c, i) => (
            <ProgressBar
              key={i}
              label={`${c.cours || 'N/A'} (${c.total || 0} séances)`}
              value={c.total || 0}
              max={Math.max(...courseStats.map(x => x.total || 0), 1)}
              color={c.total >= 5 ? 'success' : c.total >= 3 ? 'warning' : 'error'}
            />
          )) : (
            <p className="text-sm text-on-surface-variant text-center py-4">Aucune donnée de cours disponible</p>
          )}
        </div>
      </div>

      {/* Recent History Timeline */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
        <h2 className="text-lg font-bold font-headline text-primary mb-6">Historique Récent</h2>
        <div className="space-y-3">
          {history.length > 0 ? history.map((h, i) => (
            <div key={i} className="flex items-center justify-between py-2 border-b border-outline-variant/5 last:border-0">
              <div>
                <p className="text-sm font-medium text-primary">{h.cours || 'N/A'}</p>
                <p className="text-xs text-on-surface-variant">{h.date || ''}</p>
              </div>
              <Badge variant={h.statut === 'valide' ? 'success' : h.statut === 'suspect' ? 'warning' : 'error'}>
                {h.statut === 'valide' ? 'Présent' : h.statut === 'suspect' ? 'Suspect' : h.statut === 'en_retard' ? 'Retard' : 'Absent'}
              </Badge>
            </div>
          )) : (
            <p className="text-sm text-on-surface-variant text-center py-4">Aucun historique disponible</p>
          )}
        </div>
      </div>
    </div>
  );
}
