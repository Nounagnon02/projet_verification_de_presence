import { FiAlertTriangle, FiArrowRight } from 'react-icons/fi';
import { Link } from 'react-router-dom';
import useApi from '../../hooks/useApi';

/**
 * Semestres sans période déclarée pour l'année active : leurs séances ne sont
 * pas générées depuis l'emploi du temps. La génération s'en tenait là, sans
 * que rien ne le dise.
 */
export default function AlerteCalendrier({ className = '' }) {
  const { data } = useApi('/admin/calendrier');
  const alertes = Array.isArray(data?.alertes) ? data.alertes : [];

  if (alertes.length === 0) return null;

  return (
    <div role="alert" className={`flex flex-col sm:flex-row sm:items-start gap-3 rounded-xl px-4 py-3 text-sm text-on-surface bg-warning-container ${className}`}>
      <div className="flex items-start gap-3 flex-1 min-w-0">
        <FiAlertTriangle className="shrink-0 mt-0.5" aria-hidden="true" />
        <div className="space-y-1">
          {alertes.map((a) => <p key={a.parite}>{a.message}</p>)}
        </div>
      </div>
      <Link to="/settings/calendrier" className="shrink-0 flex items-center gap-1 text-xs font-bold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary rounded">
        Déclarer les semestres <FiArrowRight size={13} aria-hidden="true" />
      </Link>
    </div>
  );
}
