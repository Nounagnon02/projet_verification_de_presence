import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
  withCredentials: true,  // Cookies httpOnly Sanctum (session + CSRF)
  withXSRFToken: true,    // Protection CSRF via cookie XSRF-TOKEN
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      // Session expirée — nettoyer et rediriger
      localStorage.removeItem('presence_user');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default api;
