import axios from 'axios';
import { CONFIG } from '../constants/config';
import { getToken, clearAuth } from '../utils/token-storage';

const apiClient = axios.create({
  baseURL: CONFIG.API_URL,
  timeout: CONFIG.DEFAULT_TIMEOUT,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// ─── Request interceptor : attache le token Bearer ───
apiClient.interceptors.request.use(
  async (config) => {
    const token = await getToken();
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// ─── Response interceptor : 401 → nettoyage local ───
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Token expiré ou révoqué — nettoie le stockage
      await clearAuth();
      // La navigation vers login est gérée par AuthContext
    }
    return Promise.reject(error);
  },
);

export default apiClient;