import { MdDashboard, MdBusiness, MdCalendarMonth, MdEventBusy, MdSettings } from 'react-icons/md';
import { BarreLaterale, NavigationMobile } from './NavigationAdmin';

const LIENS_SUPER_ADMIN = [
  { to: '/super-admin', icon: <MdDashboard />, label: 'Dashboard UAC', end: true },
  // L'import CSV se fait depuis un bouton de la page Facultés, à côté de
  // « Ajouter une faculté », plus depuis une entrée de menu séparée.
  { to: '/super-admin/etablissements', icon: <MdBusiness />, label: 'Facultés / Écoles' },
  { to: '/super-admin/annees', icon: <MdCalendarMonth />, label: 'Années académiques' },
  { to: '/super-admin/jours-feries', icon: <MdEventBusy />, label: 'Jours fériés' },
  { to: '/super-admin/settings', icon: <MdSettings />, label: 'Paramètres' },
];

const SOUS_TITRE_SUPER_ADMIN = 'Super Admin : Portail UAC';

export default function SuperAdminSidebar() {
  return <BarreLaterale liens={LIENS_SUPER_ADMIN} prefixe="/super-admin" sousTitre={SOUS_TITRE_SUPER_ADMIN} />;
}

// Sous 768 px, la barre latérale est masquée : sans ce tiroir, l'espace super
// admin n'avait aucune navigation sur téléphone.
export function SuperAdminSidebarMobile() {
  return <NavigationMobile liens={LIENS_SUPER_ADMIN} prefixe="/super-admin" sousTitre={SOUS_TITRE_SUPER_ADMIN} />;
}
