import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import { FiBell, FiLogOut } from 'react-icons/fi';
import api from '../../../api/axios';

const TopNavBar = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    const fetchUnreadCount = async () => {
      try {
        const { data } = await api.get('/admin/notifications/unread-count');
        if (data.success && data.data) {
          setUnreadCount(data.data.count ?? 0);
        }
      } catch { /* silencieux */ }
    };
    fetchUnreadCount();
    const interval = setInterval(fetchUnreadCount, 30000);
    return () => clearInterval(interval);
  }, []);

  return (
    <header className="md:hidden fixed top-0 left-0 right-0 z-40 bg-surface/80 backdrop-blur-xl border-b border-outline-variant/10 px-4 py-3 flex items-center justify-between">
      <div>
        <h1 className="text-base font-bold text-primary font-headline">Présence</h1>
        <p className="text-[10px] text-on-surface-variant">{user?.name || 'Administrateur'}</p>
      </div>
      <div className="flex items-center gap-2">
        <button onClick={() => navigate('/notifications')} aria-label="Notifications" className="p-2 hover:bg-surface-container-high rounded-xl transition-colors relative">
          <FiBell className="text-outline" />
          {unreadCount > 0 && (
            <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-error text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1">
              {unreadCount > 9 ? '9+' : unreadCount}
            </span>
          )}
        </button>
        <button onClick={logout} className="p-2 hover:bg-surface-container-high rounded-xl transition-colors">
          <FiLogOut className="text-outline" />
        </button>
      </div>
    </header>
  );
};

export default TopNavBar;
