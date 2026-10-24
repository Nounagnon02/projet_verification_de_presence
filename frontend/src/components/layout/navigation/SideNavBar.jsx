import {
  MdDashboard,
  MdGroup,
  MdBook,
  MdCalendarMonth,
  MdHowToReg,
  MdAssessment,
  MdSettings,
  MdEvent,
} from 'react-icons/md';
import { BarreLaterale, NavigationMobile } from './NavigationAdmin';

// Pas de « end » sur ces liens : il imposait une correspondance EXACTE, si
// bien que « Présences » et « Paramètres » ne s'allumaient jamais — leur index
// redirige vers une sous-route. Aucun chemin n'est préfixe d'un autre : la
// correspondance par préfixe ne peut pas allumer deux entrées.
const LIENS_FACULTE = [
  { to: '/dashboard', icon: <MdDashboard />, label: 'Dashboard' },
  { to: '/students', icon: <MdGroup />, label: 'Étudiants' },
  { to: '/courses', icon: <MdBook />, label: 'Cours & UE/EC' },
  { to: '/schedules/weekly', icon: <MdCalendarMonth />, label: 'Emploi du temps' },
  { to: '/schedules/events', icon: <MdEvent />, label: 'Séances' },
  // Mène à la file d'attente ; les onglets de AttendanceLayout donnent accès
  // au reste. Pointait auparavant vers /attendance/validate, page étudiante.
  { to: '/attendance', icon: <MdHowToReg />, label: 'Présences' },
  { to: '/reports', icon: <MdAssessment />, label: 'Rapports' },
  { to: '/settings', icon: <MdSettings />, label: 'Paramètres' },
];

const SOUS_TITRE_FACULTE = 'Portail Académique';

export default function SideNavBar() {
  return <BarreLaterale liens={LIENS_FACULTE} sousTitre={SOUS_TITRE_FACULTE} />;
}

// Même menu sous 768 px, dans un tiroir : la barre du bas n'en montre que cinq entrées.
export function SideNavBarMobile() {
  return <NavigationMobile liens={LIENS_FACULTE} sousTitre={SOUS_TITRE_FACULTE} />;
}
