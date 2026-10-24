import { Link, useLocation } from 'react-router-dom';
import { FiHome } from 'react-icons/fi';

const NotFoundPage = () => {
  // Montée dans les deux espaces : /dashboard n'existe pas pour un super admin.
  const accueil = useLocation().pathname.startsWith('/super-admin') ? '/super-admin' : '/dashboard';

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] text-center">
      <div className="w-20 h-20 bg-primary/10 rounded-3xl flex items-center justify-center mb-6">
        <span className="text-4xl font-bold text-primary">404</span>
      </div>
      <h1 className="text-2xl font-bold text-primary font-headline mb-2">Page introuvable</h1>
      <p className="text-sm text-on-surface-variant max-w-md mb-8">
        La page que vous recherchez n'existe pas ou a été déplacée. Vérifiez l'URL ou retournez au tableau de bord.
      </p>
      <Link
        to={accueil}
        className="flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-xl font-semibold text-sm hover:opacity-90 transition-all shadow-sm"
      >
        <FiHome /> Retour au tableau de bord
      </Link>
    </div>
  );
};

export default NotFoundPage;
