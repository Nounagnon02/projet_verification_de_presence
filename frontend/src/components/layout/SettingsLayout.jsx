import { NavLink, Outlet } from 'react-router-dom';
import { FiCalendar, FiBook, FiMapPin, FiClock } from 'react-icons/fi';

/**
 * Paramètres : la configuration de l'établissement — années, filières, salles, calendrier.
 *
 * La sécurité du compte (mot de passe, double authentification) est passée dans
 * Profil : elle concerne la personne connectée, pas l'établissement.
 */
const onglets = [
  { to: '/settings/academic-years', label: 'Années académiques', icon: FiCalendar },
  { to: '/settings/filieres', label: 'Filières', icon: FiBook },
  { to: '/settings/salles', label: 'Salles', icon: FiMapPin },
  { to: '/settings/calendrier', label: 'Calendrier', icon: FiClock },
];

const classeLien = ({ isActive }) =>
  `flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold transition-all whitespace-nowrap ${
    isActive
      ? 'bg-primary text-white shadow-sm'
      : 'text-on-surface-variant hover:text-primary hover:bg-primary/5'
  }`;

/**
 * Des liens, et non des onglets ARIA : chaque entrée est une page, que NavLink
 * signale par aria-current. Les rôles tab/tablist annonçaient « non
 * sélectionné » partout, et un second <main id="main-content"> doublait celui
 * de la mise en page.
 */
export default function SettingsLayout() {
  return (
    <div>
      <nav
        aria-label="Paramètres"
        className="flex items-center gap-1 bg-surface-container-lowest rounded-xxl p-1.5 shadow-sm border border-outline-variant/5 mb-6 overflow-x-auto"
      >
        {onglets.map(({ to, label, icon: Icon }) => (
          <NavLink key={to} to={to} end className={classeLien}>
            <Icon size={16} aria-hidden="true" />
            <span>{label}</span>
          </NavLink>
        ))}
      </nav>
      <Outlet />
    </div>
  );
}
