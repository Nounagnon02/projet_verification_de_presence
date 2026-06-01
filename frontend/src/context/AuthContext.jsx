import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api/axios';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const storedUser = localStorage.getItem('user');
    const storedToken = localStorage.getItem('api_token');
    if (storedUser) {
      try { setUser(JSON.parse(storedUser)); } catch { localStorage.removeItem('user'); }
    }
    if (storedToken) {
      setToken(storedToken);
      api.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    try {
      // Laisser le backend gérer les sessions via Sanctum si disponible
    } catch { /* ignore */ }

    const res = await api.post('/login', { email, password });
    const result = res.data;

    let userData, apiToken = null;

    if (result.data) {
      // Nouveau format avec token : { success, data: { user, token } }
      userData = result.data.user || result.data;
      apiToken = result.data.token || null;
    } else {
      // Ancien format : { success, user }
      userData = result.user || result;
    }

    setUser(userData);
    localStorage.setItem('user', JSON.stringify(userData));

    if (apiToken) {
      setToken(apiToken);
      localStorage.setItem('api_token', apiToken);
      api.defaults.headers.common['Authorization'] = `Bearer ${apiToken}`;
    }

    return userData;
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch { /* ignore */ }
    setUser(null);
    setToken(null);
    localStorage.removeItem('user');
    localStorage.removeItem('api_token');
    delete api.defaults.headers.common['Authorization'];
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, token }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
