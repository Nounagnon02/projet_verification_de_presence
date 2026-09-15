/* eslint-disable react-refresh/only-export-components --
 * Le hook d'accès au contexte est exporté depuis le même fichier que son
 * fournisseur. C'est l'idiome React le plus répandu, et le plus lisible : on
 * trouve le contexte, son fournisseur et son accesseur au même endroit.
 *
 * La règle demande de les séparer pour que le rechargement à chaud préserve
 * l'état des composants pendant le développement. Le bénéfice est réel mais
 * strictement ergonomique — aucun effet à l'exécution — alors que la séparation
 * imposerait de modifier les imports de 13 fichiers et les doublures de test.
 * Écart assumé : le coût dépasse le gain.
 */
import { createContext, useContext, useState } from 'react';
import api, { TOKEN_KEY } from '../api/axios';
import { invalidateApiCache } from '../api/cache';

const AuthContext = createContext(null);

const USER_KEY = 'presence_user';

/**
 * Infos utilisateur minimales conservées localement, pour afficher le menu sans
 * attendre le réseau. Une entrée illisible est purgée avec le token : mieux vaut
 * une reconnexion qu'une session à moitié restaurée.
 */
function lireUtilisateurStocke() {
  const stored = localStorage.getItem(USER_KEY);

  if (!stored) {
    return null;
  }

  try {
    const parsed = JSON.parse(stored);

    if (parsed && parsed.id) {
      return {
        id: parsed.id,
        name: parsed.name,
        email: parsed.email,
        role: parsed.role,
      };
    }
  } catch {
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem(TOKEN_KEY);
  }

  return null;
}

export function AuthProvider({ children }) {
  // Initialisation paresseuse plutôt que restauration dans un effet : la lecture
  // est synchrone et locale, la faire après le premier rendu n'apportait rien et
  // provoquait un rendu supplémentaire — visible sous la forme d'un menu qui
  // apparaissait après coup.
  const [user, setUser] = useState(lireUtilisateurStocke);

  // Conservé dans le contexte car App.jsx s'en sert pour retarder le rendu des
  // routes. La restauration étant désormais synchrone, il n'y a plus rien à
  // attendre : la valeur reste false, et l'écran d'attente ne clignote plus.
  const loading = false;

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
    // Sans ce vidage, l'utilisateur suivant sur le même navigateur verrait
    // les données mises en cache par le précédent.
    invalidateApiCache();
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
