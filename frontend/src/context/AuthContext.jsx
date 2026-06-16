import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api/axios';

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
        // Ne garder que les champs non-sensibles
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
      }
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    // Récupérer le cookie CSRF avant le login (Sanctum SPA)
    // Note: /sanctum/csrf-cookie est en dehors du prefix /api
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    const res = await api.post('/login', { email, password });
    const result = res.data;

    let userData;

    if (result.data) {
      userData = result.data.user || result.data;
    } else {
      userData = result.user || result;
    }

    // Stocker uniquement les infos UI (pas de token — géré par cookie httpOnly Sanctum)
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
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
