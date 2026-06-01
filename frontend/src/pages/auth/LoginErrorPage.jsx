import { Link } from 'react-router-dom';
import { FiAlertTriangle } from 'react-icons/fi';

export default function LoginErrorPage() {
  return (
    <div className="min-h-screen bg-surface flex items-center justify-center p-6">
      <div className="text-center max-w-md">
        <div className="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <FiAlertTriangle className="text-error" size={40} />
        </div>
        <h1 className="text-2xl font-bold font-headline text-primary mb-3">Erreur d'identifiants</h1>
        <p className="text-on-surface-variant mb-8">
          Les identifiants fournis sont incorrects ou votre compte a été verrouillé après plusieurs tentatives.
          Veuillez réessayer ou contacter le support.
        </p>
        <div className="flex gap-4 justify-center">
          <Link
            to="/login"
            className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all"
          >
            Réessayer
          </Link>
          <Link
            to="/support/contact"
            className="px-8 py-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors"
          >
            Contacter le support
          </Link>
        </div>
      </div>
    </div>
  );
}
