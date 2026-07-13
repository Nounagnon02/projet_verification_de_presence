import { NavLink, Outlet } from 'react-router-dom';
import { FiCalendar, FiBook, FiShield, FiMapPin } from 'react-icons/fi';

const tabs = [
  { to: '/settings/academic-years', label: 'Années académiques', icon: FiCalendar },
  { to: '/settings/filieres', label: 'Filières', icon: FiBook },
  { to: '/settings/salles', label: 'Salles', icon: FiMapPin },
  { to: '/settings/security', label: 'Sécurité', icon: FiShield },
];

const tabLinkClass = ({ isActive }) =>
  `flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold transition-all whitespace-nowrap ${
    isActive
      ? 'bg-primary text-white shadow-sm'
      : 'text-on-surface-variant hover:text-primary hover:bg-primary/5'
  }`;

export default function SettingsLayout() {
  return (
    <div>
      <div className="flex items-center gap-1 bg-surface-container-lowest rounded-xxl p-1.5 shadow-sm border border-outline-variant/5 mb-6 overflow-x-auto" role="tablist" aria-label="Onglets de configuration">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          return (
            <NavLink key={tab.to} to={tab.to} end className={tabLinkClass} role="tab" aria-selected={false}>
              <Icon size={16} aria-hidden="true" />
              <span>{tab.label}</span>
            </NavLink>
          );
        })}
      </div>
      <main id="main-content" tabIndex={-1}>
        <Outlet />
      </main>
    </div>
  );
}
