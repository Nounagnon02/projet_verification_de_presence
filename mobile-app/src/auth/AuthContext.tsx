import {
  createContext,
  useContext,
  useReducer,
  useEffect,
  useCallback,
  useMemo,
  type ReactNode,
} from 'react';
import apiClient from '../api/client';
import {
  getToken,
  setToken,
  getCachedUser,
  setCachedUser,
  clearAuth,
} from '../utils/token-storage';
import type { ApiUser } from '../types';

// ─── State ───

interface AuthState {
  user: ApiUser | null;
  token: string | null;
  isLoading: boolean;
}

type AuthAction =
  | { type: 'RESTORE'; token: string; user: ApiUser }
  | { type: 'LOGIN'; token: string; user: ApiUser }
  | { type: 'LOGOUT' }
  | { type: 'LOADING_DONE' };

function authReducer(state: AuthState, action: AuthAction): AuthState {
  switch (action.type) {
    case 'RESTORE':
    case 'LOGIN':
      return { user: action.user, token: action.token, isLoading: false };
    case 'LOGOUT':
      return { user: null, token: null, isLoading: false };
    case 'LOADING_DONE':
      return { ...state, isLoading: false };
    default:
      return state;
  }
}

// ─── Context ───

export interface AuthContextType {
  /** Utilisateur connecté (null si non authentifié) */
  user: ApiUser | null;
  /** Token Bearer actif */
  token: string | null;
  /** true pendant la restauration de session au démarrage */
  isLoading: boolean;
  /** true si l'utilisateur est authentifié */
  isAuthenticated: boolean;
  /** Connecte l'étudiant avec email et identifiant unique */
  login: (email: string, identifiantUnique: string) => Promise<void>;
  /** Déconnecte et nettoie le stockage local */
  logout: () => Promise<void>;
  /** Rafraîchit les infos utilisateur depuis l'API */
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// ─── Provider ───

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(authReducer, {
    user: null,
    token: null,
    isLoading: true,
  });

  // Restaure la session au montage
  useEffect(() => {
    let cancelled = false;

    async function restoreSession() {
      try {
        const storedToken = await getToken();
        if (!storedToken) {
          if (!cancelled) dispatch({ type: 'LOADING_DONE' });
          return;
        }

        // Essaie de valider le token auprès de l'API
        const cached = await getCachedUser();
        try {
          const { data: user } = await apiClient.get('/user');
          if (!cancelled) {
            await setCachedUser(user);
            dispatch({ type: 'RESTORE', token: storedToken, user });
          }
        } catch {
          // Token invalide — utilise le cache si disponible
          if (cancelled) return;
          if (cached) {
            dispatch({ type: 'RESTORE', token: storedToken, user: cached });
          } else {
            await clearAuth();
            dispatch({ type: 'LOADING_DONE' });
          }
        }
      } catch {
        if (!cancelled) dispatch({ type: 'LOADING_DONE' });
      }
    }

    restoreSession();
    return () => { cancelled = true; };
  }, []);

  const login = useCallback(async (email: string, identifiantUnique: string) => {
    const { data } = await apiClient.post('/auth/student/login', { email, identifiant_unique: identifiantUnique });
    if (!data.success) {
      throw new Error(data.message || 'Identifiants invalides.');
    }
    const { user, token } = data.data;
    await Promise.all([setToken(token), setCachedUser(user)]);
    dispatch({ type: 'LOGIN', token, user });
  }, []);

  const logout = useCallback(async () => {
    try {
      await apiClient.post('/logout');
    } catch {
      // Même si l'API échoue, on nettoie localement
    }
    await clearAuth();
    dispatch({ type: 'LOGOUT' });
  }, []);

  const refreshUser = useCallback(async () => {
    const storedToken = await getToken();
    if (!storedToken) return;
    try {
      const { data: user } = await apiClient.get('/user');
      await setCachedUser(user);
      dispatch({ type: 'LOGIN', token: storedToken, user });
    } catch {
      // Ignore les échecs de rafraîchissement silencieux
    }
  }, []);

  const value = useMemo<AuthContextType>(
    () => ({
      user: state.user,
      token: state.token,
      isLoading: state.isLoading,
      isAuthenticated: !!state.token && !!state.user,
      login,
      logout,
      refreshUser,
    }),
    [state, login, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// ─── Hook ───

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth doit être utilisé à l\'intérieur d\'un <AuthProvider>.');
  }
  return context;
}