import { Navigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

/**
 * ProtectedRoute — vérifie l'authentification et optionnellement le rôle.
 *
 * Props :
 *   role ?: 'super_admin' | 'faculte_admin' | 'enseignant'
 *     Si fourni, seul un utilisateur avec ce rôle peut accéder.
 *   children : JSX — le contenu à afficher si autorisé.
 *
 * Comportement :
 *   - Non connecté → redirige vers /login
 *   - Mauvais rôle → redirige vers le dashboard correspondant à son rôle
 *   - OK → affiche children
 */
export default function ProtectedRoute({ children, role }) {
  const { user } = useAuth();

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (role && user.role !== role) {
    // Rediriger vers le bon dashboard selon le rôle
    if (user.role === 'super_admin') {
      return <Navigate to="/super-admin" replace />;
    }
    return <Navigate to="/dashboard" replace />;
  }

  return children;
}
