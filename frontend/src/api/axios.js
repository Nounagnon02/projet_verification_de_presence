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
  (error) => Promise.reject(error)
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
    }
    return Promise.reject(err);
  }
);

export { TOKEN_KEY };
export default api;
