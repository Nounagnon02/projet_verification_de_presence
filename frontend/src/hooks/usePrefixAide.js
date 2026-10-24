import { useAuth } from '../context/AuthContext';

/**
 * Les pages d'aide/FAQ/support sont montées deux fois : à la racine (coquille
 * MainLayout, faculte_admin) et sous /super-admin (coquille SuperAdminLayout).
 * Leurs liens internes, jusqu'ici tous en dur vers la racine, doivent donc se
 * recalculer selon le rôle courant — sinon un super admin qui clique plus
 * loin que la première page retombe dans le même rebond vers /super-admin
 * que celui qui touchait /help et /profile avant leur propre correction.
 */
export function usePrefixAide() {
  const { user } = useAuth();
  return user?.role === 'super_admin' ? '/super-admin' : '';
}
