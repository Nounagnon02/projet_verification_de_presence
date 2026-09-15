import { FiAlertTriangle, FiArrowRight } from 'react-icons/fi';
import { Link } from 'react-router-dom';

/**
 * Bandeau d'alerte affiché en tête du tableau de bord : les scans suspects
 * qui attendent une décision dans la file d'attente.
 *
 * « Voir la liste » était un <button> sans gestionnaire : il ne menait nulle
 * part. C'est un lien de navigation, pas une action — un <Link> lui rend le
 * clavier, le clic du milieu et l'ouverture dans un nouvel onglet, gratuitement.
 *
 * @param {Array<{title: string, message: string}>} alerts
 * @param {string} [to] Destination de la liste complète.
 */
const AlertsBanner = ({ alerts, to = '/attendance/queue' }) => {
  const premiere = alerts[0];
  const autres = Math.max(alerts.length - 1, 0);

  return (
    <div className="mb-8 bg-error-container rounded-xl p-4 flex items-center justify-between gap-4 border-l-4 border-error">
      {/* Fond plein : translucide, il laissait voir le fond de page blanc en thème
          sombre, et le texte rouge clair devenait illisible sur du rose. */}
      <div className="flex items-center gap-4 min-w-0">
        <div className="bg-error text-white p-2 rounded-lg shrink-0">
          <FiAlertTriangle />
        </div>
        <div className="min-w-0">
          <h3 className="text-sm font-bold text-on-error-container">{premiere?.title}</h3>
          <p className="text-xs text-on-error-container/80">{premiere?.message}</p>
          {/* Le bandeau ne montre que la première anomalie. Sans ce décompte,
              rien n'indiquait qu'il en restait d'autres à traiter. */}
          {autres > 0 && (
            <p className="text-[11px] font-semibold text-error mt-1">
              et {autres} autre{autres > 1 ? 's' : ''} anomalie{autres > 1 ? 's' : ''} ouverte{autres > 1 ? 's' : ''}
            </p>
          )}
        </div>
      </div>

      <Link
        to={to}
        className="text-xs font-bold text-error hover:underline px-4 py-2 rounded-lg shrink-0 flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-error focus:ring-offset-2"
      >
        Voir la liste
        <FiArrowRight size={13} />
      </Link>
    </div>
  );
};

export default AlertsBanner;
