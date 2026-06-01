import { useAuth } from '../../../context/AuthContext';
import { FiBell, FiLogOut } from 'react-icons/fi';

const TopNavBar = () => {
  const { user, logout } = useAuth();

  return (
    <header className="md:hidden fixed top-0 left-0 right-0 z-40 bg-surface/80 backdrop-blur-xl border-b border-outline-variant/10 px-4 py-3 flex items-center justify-between">
      <div>
        <h1 className="text-base font-bold text-primary font-headline">UAC Présence</h1>
        <p className="text-[10px] text-on-surface-variant">{user?.name || 'Administrateur'}</p>
      </div>
      <div className="flex items-center gap-2">
        <button className="p-2 hover:bg-surface-container-high rounded-xl transition-colors relative">
          <FiBell className="text-outline" />
          <span className="absolute top-1 right-1 w-2 h-2 bg-error rounded-full"></span>
        </button>
        <button onClick={logout} className="p-2 hover:bg-surface-container-high rounded-xl transition-colors">
          <FiLogOut className="text-outline" />
        </button>
      </div>
    </header>
  );
};

export default TopNavBar;
