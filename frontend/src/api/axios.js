import axios from 'axios';
import { invalidateApiCache } from './cache';

const TOKEN_KEY = 'auth_token';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
});

// Injection du token Bearer dans chaque requête
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error),
);

api.interceptors.response.use(
  (res) => {
    // Toute écriture réussie peut avoir modifié des données de référence
    // cachées (filières, années, salles…). On vide le cache plutôt que de
    // demander à chaque page de penser à l'invalider.
    if (res.config?.method && res.config.method.toLowerCase() !== 'get') {
      invalidateApiCache();
    }
    return res;
  },
  (err) => {
    if (err.response?.status === 401) {
      invalidateApiCache();
      localStorage.removeItem('presence_user');
      localStorage.removeItem(TOKEN_KEY);
      window.location.href = '/login';
    } else if (err.response?.status === 403 && err.response?.data?.code === 'two_factor_setup_required') {
      // Le groupe /super-admin exige désormais la 2FA (RequireTwoFactorForSuperAdmin).
      // Le compte reste authentifié : on ne le déconnecte pas, on le renvoie
      // vers l'écran où il peut l'activer. /profile est gardée par
      // role="faculte_admin" : y envoyer un super admin le faisait rebondir
      // aussitôt sur /super-admin, sans jamais voir l'écran d'activation.
      let role = null;
      try {
        role = JSON.parse(localStorage.getItem('presence_user') || 'null')?.role ?? null;
      } catch {
        // role reste null : destination par défaut /profile.
      }
      const cible = role === 'super_admin' ? '/super-admin/profile' : '/profile';
      window.location.href = `${cible}?securite=requise`;
    }
    return Promise.reject(err);
  },
);

export { TOKEN_KEY };
export default api;
