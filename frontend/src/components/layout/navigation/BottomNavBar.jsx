import { NavLink } from 'react-router-dom';
import {
  MdHome,
  MdGroup,
  MdDashboard,
  MdCalendarMonth,
  MdHowToReg,
  MdSettings,
  MdPerson
} from 'react-icons/md';

const links = [
  { to: '/dashboard', icon: <MdHome />, label: 'Accueil' },
  { to: '/students', icon: <MdGroup />, label: 'Étudiants' },
  { to: '/attendance/validate', icon: <MdHowToReg />, label: 'Présence' },
  { to: '/schedules/weekly', icon: <MdCalendarMonth />, label: 'Emploi du temps' },
  { to: '/settings', icon: <MdSettings />, label: 'Paramètres' },
];

export default function BottomNavBar() {
  return (
    <nav className="md:hidden fixed bottom-0 left-0 w-full flex justify-around items-center px-4 pb-6 pt-3 bg-white/80 backdrop-blur-xl z-50 rounded-t-3xl shadow-[0_-4px_20px_rgba(0,0,0,0.05)] border-t border-slate-100">
      {links.map((link) => (
        <NavLink
          key={link.to}
          to={link.to}
          end
          className={({ isActive }) =>
            `flex flex-col items-center justify-center p-2 transition-colors ${
              isActive ? 'text-primary' : 'text-slate-400'
            }`
          }
        >
          <span className="text-xl">{link.icon}</span>
          <span className="text-[10px] font-medium font-label">{link.label}</span>
        </NavLink>
      ))}
    </nav>
  );
}
