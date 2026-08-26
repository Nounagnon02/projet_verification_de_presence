import { NavLink, Outlet } from 'react-router-dom';
import { FiFileText } from 'react-icons/fi';
import { MdPictureAsPdf, MdSchool } from 'react-icons/md';

/**
 * Onglets de la section Imports, sur le modèle d'AttendanceLayout.
 *
 * Les cinq imports existaient déjà, mais leurs onglets vivaient DANS la page,
 * sous un titre et dans une colonne de 672 px : la section ne ressemblait à
 * aucune autre, et l'onglet actif ne se lisait pas dans l'URL. Chaque import a
 * désormais sa route, ce qui le rend partageable et permet d'y revenir
 * directement.
 */
// L'import des etudiants ne figure PAS ici : il vit dans la page de gestion des
// etudiants, ou l'on voit le resultat de l'import juste apres l'avoir lance.
// Il etait accessible aux deux endroits, avec deux ecrans differents pour le
// meme endpoint — l'un des deux devait partir, et c'est celui qui etait loin
// des donnees concernees.
const tabs = [
  { to: '/import/cours-csv',    label: 'Cours (CSV)', icon: FiFileText },
  { to: '/import/edt-csv',      label: 'EDT (CSV)',   icon: FiFileText },
  { to: '/import/edt-ia',       label: 'EDT (IA)',    icon: MdPictureAsPdf },
  { to: '/import/cours-ia',     label: 'Cours (IA)',  icon: MdSchool },
];

const tabLinkClass = ({ isActive }) =>
  `flex-1 sm:flex-none text-center px-4 py-2 rounded-lg text-sm font-bold transition-all whitespace-nowrap flex items-center justify-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
    isActive
      ? 'bg-primary text-white shadow-sm'
      : 'text-on-surface-variant hover:text-primary hover:bg-primary/5'
  }`;

export default function ImportLayout() {
  return (
    <div>
      <div
        className="flex items-center gap-1 bg-surface-container-lowest rounded-xl p-1 shadow-sm border border-outline-variant/10 mb-6 overflow-x-auto"
        role="tablist"
        aria-label="Onglets d'importation de données"
      >
        {tabs.map((tab) => (
          <NavLink key={tab.to} to={tab.to} end className={tabLinkClass} role="tab" aria-selected={false}>
            <tab.icon size={14} className="shrink-0" />
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
