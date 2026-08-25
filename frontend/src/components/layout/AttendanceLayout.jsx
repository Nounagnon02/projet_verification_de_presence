import { NavLink, Outlet } from 'react-router-dom';

// Onglets du travail de l'administrateur sur les presences.
//
// « Valider » pointait vers la page publique de l'etudiant, concue plein ecran
// pour un smartphone : elle s'affichait mal ici et n'avait de toute facon rien
// a faire derriere une authentification. Elle est remplacee par la saisie
// manuelle, qui existait deja sur /attendance/scan sans etre atteignable.
const tabs = [
  { to: '/attendance/queue', label: "File d'attente" },
  { to: '/attendance/alerts', label: 'Anomalies' },
  { to: '/attendance/history', label: 'Historique' },
  { to: '/attendance/scan', label: 'Saisie manuelle' },
];

const tabLinkClass = ({ isActive }) =>
  `flex-1 sm:flex-none text-center px-4 py-2 rounded-lg text-sm font-bold transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
    isActive
      ? 'bg-primary text-white shadow-sm'
      : 'text-on-surface-variant hover:text-primary hover:bg-primary/5'
  }`;

export default function AttendanceLayout() {
  return (
    <div>
      <div className="flex items-center gap-1 bg-surface-container-lowest rounded-xl p-1 shadow-sm border border-outline-variant/10 mb-6 overflow-x-auto" role="tablist" aria-label="Onglets de gestion des présences">
        {tabs.map((tab) => (
          <NavLink
            key={tab.to}
            to={tab.to}
            end
            className={tabLinkClass}
            role="tab"
            aria-selected={false}
          >
            {tab.label}
          </NavLink>
        ))}
      </div>
      <main id="main-content" tabIndex={-1}>
        <Outlet />
      </main>
    </div>
  );
}
