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

// ─── Erreur de connexion ───

/**
 * Erreur de connexion porteuse du code métier renvoyé par le serveur.
 *
 * Le serveur distingue deux refus que l'écran doit traiter différemment : des
 * identifiants faux (422, message générique) et un étudiant à qui aucun code
 * d'accès n'a encore été envoyé (409, code « code_absent »). Sans ce code, axios
 * ne remontait que « Request failed with status code 409 » et l'écran ne pouvait
 * pas dire à l'étudiant de réclamer son code à l'administration.
 */
export class ErreurConnexion extends Error {
  readonly codeMetier?: string;

  constructor(message: string, codeMetier?: string) {
    super(message);
    this.name = 'ErreurConnexion';
    this.codeMetier = codeMetier;
  }
}

/** Traduit l'échec HTTP de la connexion en ErreurConnexion exploitable. */
function traduireEchecConnexion(err: unknown): ErreurConnexion {
  const reponse = (err as { response?: { data?: { message?: string; code?: string } } })?.response;
  const donnees = reponse?.data;
  return new ErreurConnexion(
    donnees?.message ?? 'Identifiants invalides.',
    donnees?.code,
  );
}

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
  /** Connecte l'étudiant avec email, identifiant unique et code d'accès à 6 chiffres */
  login: (email: string, identifiantUnique: string, code: string) => Promise<void>;
  /** Déconnecte et nettoie le stockage local */
  logout: () => Promise<void>;
  /**
   * Clôt la session localement, sans appeler /logout.
   * Utilisée quand le serveur a déjà répondu 401 : le jeton est révoqué, lui
   * repasser une requête ne ferait qu'ajouter un aller-retour voué au même 401.
   */
  sessionExpiree: () => Promise<void>;
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

  const login = useCallback(async (email: string, identifiantUnique: string, code: string) => {
    let data;
    try {
      // Le code d'accès à 6 chiffres est le secret de l'étudiant : l'email et
      // l'identifiant unique figurent sur sa carte et circulent en clair, ils
      // ne prouvaient rien à eux seuls.
      ({ data } = await apiClient.post('/auth/student/login', {
        email,
        identifiant_unique: identifiantUnique,
        code,
      }));
    } catch (err: unknown) {
      throw traduireEchecConnexion(err);
    }
    if (!data.success) {
      throw new ErreurConnexion(data.message || 'Identifiants invalides.', data.code);
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

  const sessionExpiree = useCallback(async () => {
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
      sessionExpiree,
      refreshUser,
    }),
    [state, login, logout, sessionExpiree, refreshUser],
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