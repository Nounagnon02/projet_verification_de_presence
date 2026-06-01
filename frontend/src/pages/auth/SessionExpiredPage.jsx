import { Link } from 'react-router-dom';
import { FiClock } from 'react-icons/fi';

export default function SessionExpiredPage() {
  return (
    <div className="min-h-screen bg-surface flex items-center justify-center p-6">
      <div className="text-center max-w-md">
        <div className="w-20 h-20 bg-warning/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <FiClock className="text-warning" size={40} />
        </div>
        <h1 className="text-2xl font-bold font-headline text-primary mb-3">Session expirée</h1>
        <p className="text-on-surface-variant mb-8">
          Votre session a expiré pour des raisons de sécurité. Veuillez vous reconnecter pour continuer.
        </p>
        <Link
          to="/login"
          className="inline-block bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all"
        >
          Se reconnecter
        </Link>
      </div>
    </div>
  );
}
