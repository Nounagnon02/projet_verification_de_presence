import { NavLink, Outlet } from 'react-router-dom';

const tabs = [
  { to: '/attendance/validate', label: 'Valider' },
  { to: '/attendance/alerts', label: 'Anomalies' },
  { to: '/attendance/history', label: 'Historique' },
];

const tabLinkClass = ({ isActive }) =>
  `flex-1 sm:flex-none text-center px-4 py-2 rounded-lg text-sm font-bold transition-all ${
    isActive
      ? 'bg-primary text-white shadow-sm'
      : 'text-on-surface-variant hover:text-primary hover:bg-primary/5'
  }`;

export default function AttendanceLayout() {
  return (
    <div>
      <div className="flex items-center gap-1 bg-surface-container-lowest rounded-xl p-1 shadow-sm border border-outline-variant/10 mb-6 overflow-x-auto">
        {tabs.map((tab) => (
          <NavLink key={tab.to} to={tab.to} end className={tabLinkClass}>
            {tab.label}
          </NavLink>
        ))}
      </div>
      <Outlet />
    </div>
  );
}
