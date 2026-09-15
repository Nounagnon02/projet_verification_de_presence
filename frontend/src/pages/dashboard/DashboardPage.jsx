import { FiAlertTriangle, FiCheckCircle, FiBarChart2, FiActivity } from 'react-icons/fi';
import { MdOutlineGroup, MdOutlineCalendarToday } from 'react-icons/md';
import { Link } from 'react-router-dom';
import AlertsBanner from '../../components/ui/AlertsBanner';
import AlerteCalendrier from '../../components/ui/AlerteCalendrier';
import KPICard from '../../components/cards/KPICard';
import BarChart from '../../components/charts/BarChart';
import ProgressBar from '../../components/charts/ProgressBar';
import TodaysEvents from '../../components/TodaysEvents';
import RecentQRScans from '../../components/RecentQRScans';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import useApi from '../../hooks/useApi';

const DashboardPage = () => {
  const { data: dashData, loading } = useApi('/admin/dashboard');
  const { data: trendData } = useApi('/admin/dashboard/attendance-trend');
  const { data: topAbsencesData } = useApi('/admin/dashboard/top-absences');
  const { data: todayEventsData } = useApi('/admin/dashboard/today-events');

  const kpis = [
    { label: 'Total Étudiants', value: dashData?.total_etudiants ?? '—', change: null, icon: <MdOutlineGroup size={20} />, trend: 'up' },
    { label: 'Présences Aujourd\'hui', value: dashData?.presences_aujourd_hui ?? '—', change: null, icon: <FiCheckCircle size={20} />, trend: 'up' },
    { label: 'Taux Présence Global', value: dashData?.taux_presence_global ? `${dashData.taux_presence_global}%` : '—', change: null, icon: <FiBarChart2 size={20} />, trend: 'up' },
    // Les refus du jour sont donnés pour information : aucune présence n'a été
    // créée, il n'y a rien à trancher. Seuls les scans suspects attendent une décision.
    { label: 'Scans à arbitrer', value: dashData?.scans_a_arbitrer ?? '—', change: dashData ? `${dashData.scans_refuses_du_jour ?? 0} refus aujourd'hui` : null, icon: <FiAlertTriangle size={20} />, trend: 'neutral' },
  ];

  const attendanceData = (Array.isArray(trendData) ? trendData : []).map(item => ({
    label: item.date ? new Date(item.date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }).toUpperCase() : '',
    value: item.total,
  }));

  const topAbsences = Array.isArray(topAbsencesData) ? topAbsencesData.slice(0, 3) : [];

  const events = (Array.isArray(todayEventsData) ? todayEventsData : []).map(item => ({
    time: `${item.heure_debut || '--:--'} - ${item.heure_fin || '--:--'}`,
    title: item.cours || 'N/A',
    location: `Salle ${item.salle || 'N/A'} — ${item.filiere || ''}`,
    status: item.statut === 'termine' ? 'Terminé' : item.statut === 'en_cours' ? 'En cours' : 'À venir',
    statusColor: item.statut === 'termine' ? 'secondary-container' : item.statut === 'en_cours' ? 'primary' : 'surface-container-high',
    progress: item.statut === 'en_cours' ? 45 : undefined,
  }));

  const scans = (Array.isArray(todayEventsData) ? todayEventsData : []).filter(e => e.statut === 'en_cours').slice(0, 4).map(e => ({
    name: `${e.filiere || ''}`,
    course: e.cours || 'N/A',
    time: e.heure_debut || '',
    status: 'SUCCÈS',
    statusColor: '#006d43',
    image: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"%3E%3Crect width="32" height="32" fill="%23e0e0e0" rx="16"/%3E%3Ctext x="16" y="20" text-anchor="middle" font-size="14" fill="%23999"%3E%3F%3C/text%3E%3C/svg%3E',
  }));

  // Bandeau : les scans suspects attendent une décision dans la file d'attente.
  // Il reprenait toutes les anomalies ouvertes, scans refusés compris, qui ne
  // demandent aucune décision et ne se fermaient jamais.
  const aArbitrer = Number(dashData?.scans_a_arbitrer ?? 0);
  const alerts = aArbitrer > 0 ? [{
    title: `${aArbitrer} scan${aArbitrer > 1 ? 's' : ''} suspect${aArbitrer > 1 ? 's' : ''} à arbitrer`,
    message: 'Un même téléphone a servi à plusieurs étudiants pendant une séance.',
  }] : [];

  if (loading) return <LoadingSkeleton type="card" cols={4} />;

  return (
    <div>
      {alerts.length > 0 && <AlertsBanner alerts={alerts} to="/attendance/queue" />}
      <AlerteCalendrier className="mb-8" />

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {kpis.map((kpi, i) => (<KPICard key={i} {...kpi} />))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div className="lg:col-span-8 space-y-8">
          <div className="bg-surface-container-lowest p-6 rounded-xxl shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <div>
                <h2 className="text-lg font-bold font-headline text-primary">Taux de Présence (30 jours)</h2>
                <p className="text-xs text-on-surface-variant">Évolution de la participation académique</p>
              </div>
            </div>
            <BarChart data={attendanceData.length > 0 ? attendanceData : [{ label: 'Aucune donnée', value: 0 }]} bars="value" height={200} />
          </div>

          <div className="bg-surface-container-lowest p-6 rounded-xxl shadow-sm">
            <h2 className="text-lg font-bold font-headline text-primary mb-6">Top Absences par Étudiant</h2>
            <div className="space-y-5">
              {topAbsences.length > 0 ? topAbsences.map((item, i) => (
                <ProgressBar
                  key={i}
                  label={`${item.prenom || ''} ${item.nom || ''} (${item.matricule || ''})`}
                  value={item.absences || 0}
                  max={(topAbsences[0]?.absences || 10) * 1.2}
                  color={i === 0 ? 'error' : i === 1 ? 'warning' : 'primary'}
                />
              )) : (
                <p className="text-sm text-on-surface-variant text-center py-4">Aucune donnée d'absence disponible</p>
              )}
            </div>
          </div>
        </div>

        <div className="lg:col-span-4 space-y-8">
          <div className="bg-surface-container-lowest p-6 rounded-xxl shadow-sm border-t-4 border-primary">
            <h2 className="text-sm font-bold font-headline text-primary mb-6 flex items-center gap-2">
              <MdOutlineCalendarToday /> Cours du Jour
            </h2>
            {events.length > 0 ? <TodaysEvents events={events} /> : (
              <p className="text-sm text-on-surface-variant text-center py-4">Aucun cours aujourd'hui</p>
            )}
          </div>

          <div className="bg-surface-container-lowest p-6 rounded-xxl shadow-sm">
            <h2 className="text-sm font-bold font-headline text-primary mb-6 flex items-center gap-2">
              <FiActivity /> Derniers Scans QR
            </h2>
            {scans.length > 0 ? <RecentQRScans scans={scans} /> : (
              <p className="text-sm text-on-surface-variant text-center py-4">Aucun scan récent</p>
            )}
            {/* Un <button> sans gestionnaire : le clic ne menait nulle part.
                C'est une navigation, donc un lien — qui gagne au passage le
                clavier et l'ouverture dans un nouvel onglet. */}
            <Link
              to="/attendance/history"
              className="block w-full mt-6 py-3 border border-outline-variant/20 rounded-xl text-xs font-bold text-primary text-center hover:bg-surface-container-low transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
              Voir l'historique complet
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DashboardPage;
