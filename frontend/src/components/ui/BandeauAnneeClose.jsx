import { Link } from 'react-router-dom';
import { FiLock } from 'react-icons/fi';

/**
 * Une année antérieure à l'année active de l'établissement est close : on la
 * consulte, on n'y modifie plus la structure (le serveur refuse en 409). Le
 * bandeau le dit avant le clic, et indique comment y revenir pour corriger.
 */
export default function BandeauAnneeClose({ annee }) {
  if (!annee) return null;

  return (
    <div role="status" className="flex items-start gap-3 rounded-xl bg-surface-container-high px-4 py-3 text-sm text-on-surface">
      <FiLock className="shrink-0 mt-0.5 text-on-surface-variant" aria-hidden="true" />
      <p>
        <strong>{annee.libelle} est close</strong> pour votre établissement : consultation seulement. Pour y corriger
        quelque chose, repassez temporairement sur cette année depuis{' '}
        <Link to="/settings/academic-years" className="font-semibold text-primary hover:underline">Années académiques</Link>.
      </p>
    </div>
  );
}
