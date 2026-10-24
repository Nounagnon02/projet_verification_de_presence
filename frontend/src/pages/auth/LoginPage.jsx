import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiMail, FiLock, FiAlertTriangle, FiLoader, FiArrowLeft } from 'react-icons/fi';
import { MdQrCodeScanner, MdAutoAwesome, MdGroups } from 'react-icons/md';
import { useAuth } from '../../context/AuthContext';
import { assets } from '../../utils/assets';
import Logo from '../../components/ui/Logo';

function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  // Le mot de passe seul a été accepté, mais le compte a la 2FA activée :
  // AuthContext.login() lève alors une erreur marquée twoFactorRequired,
  // et c'est ce second passage dans le formulaire qui collecte le code.
  const [requiresCode, setRequiresCode] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();

  // Parse error from URL params once (avoids useSearchParams which causes
  // React Router v7 SyncExternalStore re-render cascade in React 19)
  const [initialError] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    const type = params.get('error');
    if (type === 'expired') return 'Votre session a expiré. Veuillez vous reconnecter.';
    if (type === 'invalid') return 'Identifiants invalides. Veuillez réessayer.';
    return '';
  });

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!requiresCode) {
      if (!email.trim() || !password.trim()) {
        setError('Veuillez remplir tous les champs');
        return;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        setError('Veuillez saisir une adresse email valide');
        return;
      }
    } else if (code.length !== 6) {
      setError('Entrez le code à 6 chiffres.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const userData = await login(email, password, requiresCode ? code : undefined);
      // Rediriger selon le rôle
      if (userData?.role === 'super_admin') {
        navigate('/super-admin', { replace: true });
      } else {
        navigate('/dashboard', { replace: true });
      }
    } catch (err) {
      if (err.twoFactorRequired) {
        setRequiresCode(true);
        setCode('');
        setError('');
      } else {
        const data = err.response?.data;
        const msg =
          data?.message ||
          (data?.errors && Object.values(data.errors).flat()[0]) ||
          err.message ||
          'Identifiants invalides. Veuillez réessayer.';
        setError(msg);
        // Un code invalide (422) reste sur l'étape code : revenir au mot de
        // passe ferait recommencer une vérification déjà réussie.
        if (requiresCode) setCode('');
      }
    } finally {
      setLoading(false);
    }
  };

  const retourMotDePasse = () => {
    setRequiresCode(false);
    setCode('');
    setError('');
  };

  return (
    <div className="min-h-screen flex">
      {/* ==PARTIE GAUCHE — Branding ==*/}
      <div className="hidden lg:flex lg:w-[45%] relative overflow-hidden">
        {/* Image de fond */}
        <img
          src={assets.rectoratUac}
          alt="Campus universitaire"
          width={assets.rectoratUacTaille.width}
          height={assets.rectoratUacTaille.height}
          loading="lazy"
          decoding="async"
          className="absolute inset-0 w-full h-full object-cover"
        />
        {/* Overlay */}
        <div className="absolute inset-0 bg-gradient-to-br from-[#011549]/90 via-[#011549]/75 to-[#0a1a3a]/90"></div>

        {/* Contenu */}
        <div className="relative flex flex-col justify-between p-12 w-full">
          {/* Logo */}
          <div className="flex items-center gap-3">
            <img src="/images/logo-blanc.png" alt="UAC Présences" className="h-9 w-auto" />
            <div>
              <p className="text-[10px] font-medium text-white/50 uppercase tracking-[0.2em]">Portail Académique</p>
            </div>
          </div>

          {/* Texte central */}
          <div className="space-y-6">
            <h2 className="text-3xl font-bold text-white font-headline leading-tight">
              Plateforme de gestion
              <br />
              des présences académiques
            </h2>
            <p className="text-white/60 text-sm leading-relaxed max-w-sm">
              Accédez au tableau de bord pour gérer les cours, suivre les présences en temps réel et analyser les
              statistiques.
            </p>

            {/* Petites cartes d'info */}
            <div className="space-y-3 pt-4">
              {[
                { icon: MdQrCodeScanner, text: 'Validation des présences par QR Code' },
                { icon: MdAutoAwesome, text: 'Import intelligent des emplois du temps' },
                { icon: MdGroups, text: 'Suivi personnalisé par étudiant' },
              ].map((item, i) => (
                <div key={i} className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center shrink-0">
                    <item.icon size={16} className="text-white/80" />
                  </div>
                  <span className="text-sm text-white/70">{item.text}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Footer */}
          <p className="text-xs text-white/30">© {new Date().getFullYear()}. Tous droits réservés</p>
        </div>
      </div>

      {/* ==PARTIE DROITE — Formulaire de connexion ==*/}
      <div className="flex-1 flex flex-col bg-surface">
        {/* Header mobile/tablette */}
        <div className="lg:hidden w-full px-6 py-5 flex items-center justify-between border-b border-outline-variant/10">
          <div className="flex items-center gap-3">
            <Logo className="h-7 w-auto" />
          </div>
          <span className="text-xs text-on-surface-variant">Portail académique</span>
        </div>

        {/* Contenu principal centré */}
        <main className="flex-1 flex items-center justify-center px-6 py-8 relative">
          {/* Décoration subtile */}
          <div className="absolute inset-0 pointer-events-none overflow-hidden">
            <div className="absolute top-1/4 -right-32 w-96 h-96 bg-primary/3 rounded-full blur-3xl"></div>
            <div className="absolute bottom-1/4 -left-32 w-96 h-96 bg-secondary/3 rounded-full blur-3xl"></div>
          </div>

          <div className="relative w-full max-w-md">
            {/* Lien retour */}
            <a
              href="/"
              className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary mb-8 transition-colors"
            >
              <FiArrowLeft size={14} /> Retour à l'accueil
            </a>

            <div className="bg-surface-container-lowest rounded-3xl p-10 shadow-[0_12px_32px_rgba(25,28,31,0.04)] border border-outline-variant/10">
              <div className="mb-8">
                <h1 className="text-2xl font-bold text-primary tracking-tight mb-1.5">Accès Administrateur</h1>
                <p className="text-sm text-on-surface-variant">Renseignez vos identifiants pour accéder au portail.</p>
              </div>

              {(error || initialError) && (
                <div className="flex items-start gap-2.5 p-4 bg-statut-erreur-fond rounded-xl text-statut-erreur border border-error/10 mb-5">
                  <FiAlertTriangle className="text-lg shrink-0 mt-0.5" />
                  <p className="text-sm">{error || initialError}</p>
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-5">
                {!requiresCode ? (
                  <>
                    <div className="space-y-1.5">
                      <label
                        className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1"
                        htmlFor="email"
                      >
                        Email académique
                      </label>
                      <div className="relative group">
                        <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none text-on-surface-variant">
                          <FiMail size={16} />
                        </div>
                        <input
                          className="w-full pl-11 pr-4 py-3.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-colors text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none text-sm"
                          id="email"
                          placeholder="nom.prenom@uac.bj"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          type="email"
                          disabled={loading}
                          autoComplete="email"
                        />
                      </div>
                    </div>

                    <div className="space-y-1.5">
                      <div className="flex justify-between items-center">
                        <label
                          className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1"
                          htmlFor="password"
                        >
                          Mot de passe
                        </label>
                        <button
                          type="button"
                          onClick={() => navigate('/forgot-password')}
                          className="text-xs font-semibold text-primary hover:underline cursor-pointer"
                        >
                          Mot de passe oublié?
                        </button>
                      </div>
                      <div className="relative group">
                        <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none text-on-surface-variant">
                          <FiLock size={16} />
                        </div>
                        <input
                          className="w-full pl-11 pr-4 py-3.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-colors text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none text-sm"
                          id="password"
                          placeholder="••••••••••••"
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                          type="password"
                          disabled={loading}
                          autoComplete="current-password"
                        />
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="space-y-1.5">
                    <div className="flex justify-between items-center">
                      <label
                        className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1"
                        htmlFor="code"
                      >
                        Code de vérification
                      </label>
                      <button
                        type="button"
                        onClick={retourMotDePasse}
                        className="text-xs font-semibold text-primary hover:underline cursor-pointer flex items-center gap-1"
                      >
                        <FiArrowLeft size={12} /> Retour
                      </button>
                    </div>
                    <p className="text-xs text-on-surface-variant">
                      Entrez le code à 6 chiffres de votre application d'authentification.
                    </p>
                    <input
                      className="w-full px-4 py-3.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-colors text-on-surface text-center text-2xl font-mono tracking-widest focus:outline-none"
                      id="code"
                      placeholder="000000"
                      value={code}
                      onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                      type="text"
                      inputMode="numeric"
                      maxLength={6}
                      disabled={loading}
                      autoComplete="one-time-code"
                      autoFocus
                    />
                  </div>
                )}

                <button
                  className={`w-full bg-gradient-to-br from-marque to-marque-fonce text-white py-3.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:scale-[0.99] active:scale-[0.98] transition-all flex items-center justify-center gap-2 ${loading ? 'opacity-70 cursor-not-allowed' : ''}`}
                  type="submit"
                  disabled={loading || (requiresCode && code.length !== 6)}
                >
                  {loading ? <FiLoader className="animate-spin" /> : null}
                  {requiresCode ? 'Vérifier' : 'Se connecter'}
                </button>
              </form>
            </div>

            <p className="text-center text-xs text-on-surface-variant mt-6">
              En vous connectant, vous acceptez les{' '}
              <Link to="/terms" className="underline hover:text-primary">
                Conditions d'Utilisation
              </Link>
              .
            </p>
          </div>
        </main>
      </div>
    </div>
  );
}

export default LoginPage;
