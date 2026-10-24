import { NavLink, Outlet } from 'react-router-dom';

// Onglets du travail de l'administrateur sur les presences.
//
// « Valider » pointait vers la page publique de l'etudiant, concue plein ecran
// pour un smartphone : elle s'affichait mal ici et n'avait de toute facon rien
// a faire derriere une authentification. Elle est remplacee par la saisie
// manuelle, qui existait deja sur /attendance/scan sans etre atteignable.
//
// Cette saisie etait elle-meme un formulaire de scan exigeant un QR en cours :
// ouverte depuis ce menu, elle n'aboutissait jamais. Elle enregistre
// desormais un etudiant qui n'a pas pu scanner, seance par seance.
const tabs = [
  { to: '/attendance/queue', label: "File d'attente" },
  { to: '/attendance/alerts', label: 'Scans refusés' },
  { to: '/attendance/history', label: 'Historique' },
  { to: '/attendance/scan', label: 'Saisie manuelle' },
];

const tabLinkClass = ({ isActive }) =>
  `whitespace-nowrap text-center px-3 sm:px-4 py-2 rounded-lg text-sm font-bold transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
    isActive ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary hover:bg-primary/5'
  }`;

// Une navigation entre routes, pas des onglets ARIA : role="tab" sans panneau
// ni flèches annonçait un widget absent, et aria-selected restait faux partout.
// NavLink pose aria-current="page" sur l'entrée active. Le <main> de MainLayout
// englobe déjà cette page : un second <main id="main-content"> le dupliquait.
export default function AttendanceLayout() {
  return (
    <div>
      {/* Grille 2×2 sous 640 px : en ligne, « Saisie manuelle » était coupé
          à 390 px et les libellés passaient sur deux lignes. */}
      <nav
        className="grid grid-cols-2 gap-1 sm:flex sm:items-center bg-surface-container-lowest rounded-xl p-1 shadow-sm border border-outline-variant/10 mb-6"
        aria-label="Gestion des présences"
      >
        {tabs.map((tab) => (
          <NavLink key={tab.to} to={tab.to} end className={tabLinkClass}>
            {tab.label}
          </NavLink>
        ))}
      </nav>
      <Outlet />
    </div>
  );
}
