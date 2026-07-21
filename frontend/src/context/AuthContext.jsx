import { createContext, useContext, useState, useEffect } from 'react';
import api, { TOKEN_KEY } from '../api/axios';

const AuthContext = createContext(null);

const USER_KEY = 'presence_user';

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Restaure les infos utilisateur minimales depuis localStorage
    // pour un affichage immédiat du menu (pas de flash)
    const stored = localStorage.getItem(USER_KEY);
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        if (parsed && parsed.id) {
          setUser({
            id: parsed.id,
            name: parsed.name,
            email: parsed.email,
            role: parsed.role,
          });
        }
      } catch {
        localStorage.removeItem(USER_KEY);
        localStorage.removeItem(TOKEN_KEY);
      }
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    const res = await api.post('/login', { email, password }, {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    });
    const result = res.data;

    let userData;
    let token;

    if (result.data) {
      userData = result.data.user || result.data;
      token = result.data.token;
    } else {
      userData = result.user || result;
      token = result.token;
    }

    // Stocker le token Bearer pour les appels API
    if (token) {
      localStorage.setItem(TOKEN_KEY, token);
    }

    // Stocker uniquement les infos UI
    const uiUser = {
      id: userData.id,
      name: userData.name,
      email: userData.email,
      role: userData.role,
    };

    setUser(uiUser);
    localStorage.setItem(USER_KEY, JSON.stringify(uiUser));

    return uiUser;
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch { /* ignore */ }
    setUser(null);
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem(TOKEN_KEY);
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
