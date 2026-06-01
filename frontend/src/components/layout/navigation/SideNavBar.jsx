import { NavLink } from 'react-router-dom';
import {
  MdDashboard,
  MdGroup,
  MdBook,
  MdCalendarMonth,
  MdHowToReg,
  MdAssessment,
  MdCloudUpload,
  MdSettings,
  MdHelp,
  MdSupportAgent,
  MdAccountCircle
} from 'react-icons/md';

const links = [
  { to: '/dashboard', icon: <MdDashboard />, label: 'Dashboard' },
  { to: '/students', icon: <MdGroup />, label: 'Étudiants' },
  { to: '/courses', icon: <MdBook />, label: 'Cours' },
  { to: '/schedules/weekly', icon: <MdCalendarMonth />, label: 'Emploi du temps' },
  { to: '/attendance/validate', icon: <MdHowToReg />, label: 'Présences' },
  { to: '/reports', icon: <MdAssessment />, label: 'Rapports' },
  { to: '/import', icon: <MdCloudUpload />, label: 'Import' },
  { to: '/settings', icon: <MdSettings />, label: 'Paramètres' },
];

const linkClass = ({ isActive }) =>
  `flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all ${
    isActive
      ? 'bg-white text-[#011549] font-semibold shadow-sm translate-x-1'
      : 'text-slate-500 hover:bg-[#e6e8ec] hover:text-[#011549] hover:translate-x-1'
  }`;

export default function SideNavBar() {
  return (
    <aside className="hidden md:flex flex-col h-screen p-5 fixed left-0 top-0 bg-[#f7f9fd] w-64 z-50">
      <div className="mb-8 px-2">
        <h1 className="text-lg font-bold text-[#011549] tracking-tight font-headline">
          UAC Présence
        </h1>
        <p className="text-[10px] text-slate-500 font-medium tracking-widest uppercase">
          Academic Portal
        </p>
      </div>

      <nav className="flex-1 space-y-0.5">
        {links.map((link) => (
          <NavLink key={link.to} to={link.to} end className={linkClass}>
            <span className="text-lg">{link.icon}</span>
            <span>{link.label}</span>
          </NavLink>
        ))}
      </nav>

      <div className="pt-4 border-t border-outline-variant/10 space-y-0.5">
        <NavLink to="/help" end className={linkClass}>
          <MdHelp className="text-lg" />
          <span>Aide</span>
        </NavLink>
        <NavLink to="/support/tickets" end className={linkClass}>
          <MdSupportAgent className="text-lg" />
          <span>Support</span>
        </NavLink>
        <NavLink to="/profile" end className={linkClass}>
          <MdAccountCircle className="text-lg" />
          <span>Profil</span>
        </NavLink>
      </div>
    </aside>
  );
}
