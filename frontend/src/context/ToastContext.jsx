/* eslint-disable react-refresh/only-export-components --
 * Le hook d'accès au contexte est exporté depuis le même fichier que son
 * fournisseur. C'est l'idiome React le plus répandu, et le plus lisible : on
 * trouve le contexte, son fournisseur et son accesseur au même endroit.
 *
 * La règle demande de les séparer pour que le rechargement à chaud préserve
 * l'état des composants pendant le développement. Le bénéfice est réel mais
 * strictement ergonomique — aucun effet à l'exécution — alors que la séparation
 * imposerait de modifier les imports de 8 fichiers et les doublures de test.
 * Écart assumé : le coût dépasse le gain.
 */
import { createContext, useContext, useState } from 'react';
import Toaster from '../components/ui/Toast';

const ToastContext = createContext(null);
export const useToastCtx = () => useContext(ToastContext);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const addToast = (message, type = 'info', duration = 4000) => {
    const id = Date.now() + Math.random();
    setToasts(prev => [...prev, { id, message, type, duration }]);
    if (duration > 0) {
      setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), duration);
    }
  };

  const removeToast = (id) => setToasts(prev => prev.filter(t => t.id !== id));

  return (
    <ToastContext.Provider value={{ addToast, removeToast }}>
      {children}
      <Toaster toasts={toasts} onRemove={removeToast} />
    </ToastContext.Provider>
  );
}
