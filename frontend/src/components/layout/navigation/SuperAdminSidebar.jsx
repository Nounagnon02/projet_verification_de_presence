import { NavLink } from 'react-router-dom';
import { MdDashboard, MdBusiness, MdCloudUpload, MdSettings, MdHelp, MdAccountCircle, MdLogout } from 'react-icons/md';
import { useAuth } from '../../../context/AuthContext';

const links = [
  { to: '/super-admin', icon: <MdDashboard />, label: 'Dashboard UAC', end: true },
  { to: '/super-admin/etablissements', icon: <MdBusiness />, label: 'Facultés / Écoles' },
  { to: '/super-admin/import', icon: <MdCloudUpload />, label: 'Import CSV' },
  { to: '/super-admin/settings', icon: <MdSettings />, label: 'Paramètres' },
];

const linkClass = ({ isActive }) =>
  `flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all ${
    isActive
      ? 'bg-white text-[#011549] font-semibold shadow-sm translate-x-1'
      : 'text-slate-500 hover:bg-[#e6e8ec] hover:text-[#011549] hover:translate-x-1'
  }`;

export default function SuperAdminSidebar() {
  const { user, logout } = useAuth();

  const handleLogout = async () => {
    await logout();
  };

  return (
    <aside className="hidden md:flex flex-col h-screen p-5 fixed left-0 top-0 bg-[#f7f9fd] w-64 z-50">
      <div className="mb-8 px-2">
        <div className="flex items-center gap-2 mb-1">
          <div className="w-8 h-8 bg-[#011549] rounded-xl flex items-center justify-center">
            <MdBusiness size={16} className="text-white" />
          </div>
          <h1 className="text-lg font-bold text-[#011549] tracking-tight font-headline">
            Super Admin
          </h1>
        </div>
        <p className="text-[10px] text-slate-500 font-medium tracking-widest uppercase">
          Portail UAC
        </p>
      </div>

      <nav className="flex-1 space-y-0.5">
        {links.map((link) => (
          <NavLink key={link.to} to={link.to} end={link.end} className={linkClass}>
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
        <NavLink to="/profile" end className={linkClass}>
          <MdAccountCircle className="text-lg" />
          <span>Profil</span>
        </NavLink>
        <button
          onClick={handleLogout}
          className="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all w-full text-left text-slate-500 hover:bg-red-50 hover:text-red-600"
        >
          <MdLogout className="text-lg" />
          <span>Déconnexion</span>
        </button>
      </div>
    </aside>
  );
}
