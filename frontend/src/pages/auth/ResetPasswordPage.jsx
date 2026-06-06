import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { FiLock, FiArrowLeft, FiCheckCircle, FiAlertTriangle, FiLoader } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';
import api from '../../api/axios';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || '';
  const emailParam = searchParams.get('email') || '';
  const navigate = useNavigate();

  const [email, setEmail] = useState(emailParam);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [reset, setReset] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!token) { setError('Token de réinitialisation manquant.'); return; }
    if (password.length < 8) { setError('Le mot de passe doit contenir au moins 8 caractères.'); return; }
    if (password !== passwordConfirmation) { setError('Les mots de passe ne correspondent pas.'); return; }

    setLoading(true);
    setError('');
    try {
      const { data } = await api.post('/reset-password', {
        token, email, password,
        password_confirmation: passwordConfirmation,
      });
      if (data.success) {
        setReset(true);
      } else {
        setError(data.message || 'Erreur lors de la réinitialisation.');
      }
    } catch (err) {
      const msg = err.response?.data?.message
        || (err.response?.data?.errors?.email ? err.response.data.errors.email.join(', ') : null)
        || 'Erreur lors de la réinitialisation. Le lien est peut-être expiré.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-surface flex">
      {/* Bannière gauche */}
      <div className="hidden lg:flex lg:w-[45%] relative overflow-hidden">
        <img src="/images/rectorat-uac.jpg" alt="Campus universitaire"
          className="absolute inset-0 w-full h-full object-cover" />
        <div className="absolute inset-0 bg-gradient-to-br from-[#011549]/90 via-[#011549]/75 to-[#0a1a3a]/90"></div>
        <div className="relative flex flex-col justify-between p-12 w-full">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-white/15 backdrop-blur rounded-xl flex items-center justify-center">
              <MdAccountBalance size={26} className="text-white" />
            </div>
            <div>
              <span className="text-xl font-bold tracking-tight text-white font-headline">Présence</span>
              <p className="text-[10px] font-medium text-white/50 uppercase tracking-[0.2em]">Portail Académique</p>
            </div>
          </div>
          <div>
            <h2 className="text-3xl font-bold text-white font-headline leading-tight">Réinitialisation</h2>
            <p className="text-white/60 text-sm mt-3 max-w-sm">Choisissez un nouveau mot de passe sécurisé pour votre compte.</p>
          </div>
          <p className="text-xs text-white/30">© {new Date().getFullYear()} — Tous droits réservés</p>
        </div>
      </div>

      {/* Partie droite */}
      <div className="flex-1 flex flex-col">
        <div className="lg:hidden w-full px-6 py-5 flex items-center gap-3 border-b border-outline-variant/10">
          <div className="w-9 h-9 bg-primary rounded-xl flex items-center justify-center text-white">
            <MdAccountBalance size={20} />
          </div>
          <span className="text-lg font-bold text-primary font-headline">Présence</span>
        </div>

        <main className="flex-1 flex items-center justify-center px-6 py-8">
          <div className="w-full max-w-md">
            <button onClick={() => navigate('/login')}
              className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary mb-8 transition-colors">
              <FiArrowLeft size={14} /> Retour à la connexion
            </button>

            <div className="bg-surface-container-lowest rounded-3xl p-10 shadow-[0_12px_32px_rgba(25,28,31,0.04)] border border-outline-variant/10">
              {reset ? (
                <>
                  <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
                    <FiCheckCircle className="text-secondary" size={32} />
                  </div>
                  <h1 className="text-2xl font-bold text-primary text-center mb-3">Mot de passe réinitialisé !</h1>
                  <p className="text-sm text-on-surface-variant text-center mb-8">
                    Votre mot de passe a été modifié avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.
                  </p>
                  <button onClick={() => navigate('/login')}
                    className="w-full py-3 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all">
                    Se connecter
                  </button>
                </>
              ) : (
                <>
                  <div className="mb-8">
                    <h1 className="text-2xl font-bold text-primary mb-1.5">Nouveau mot de passe</h1>
                    <p className="text-sm text-on-surface-variant">Créez un mot de passe sécurisé pour votre compte.</p>
                  </div>

                  {error && (
                    <div className="flex items-start gap-2.5 p-4 bg-error/10 rounded-xl text-error border border-error/10 mb-5">
                      <FiAlertTriangle className="text-lg shrink-0 mt-0.5" />
                      <p className="text-sm">{error}</p>
                    </div>
                  )}

                  <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1" htmlFor="email">
                        Email académique
                      </label>
                      <div className="relative group">
                        <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none text-on-surface-variant/60">
                          <FiMail size={16} />
                        </div>
                        <input
                          className="w-full pl-11 pr-4 py-3.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none text-sm"
                          id="email"
                          placeholder="nom.prenom@uac.bj"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          type="email"
                          disabled={loading}
                          required
                          autoComplete="email"
                        />
                      </div>
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1" htmlFor="password">
                        Nouveau mot de passe
                      </label>
                      <div className="relative group">
                        <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none text-on-surface-variant/60">
                          <FiLock size={16} />
                        </div>
                        <input
                          className="w-full pl-11 pr-4 py-3.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none text-sm"
                          id="password"
                          placeholder="Minimum 8 caractères"
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                          type="password"
                          disabled={loading}
                          required
                          minLength={8}
                          autoComplete="new-password"
                        />
                      </div>
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1" htmlFor="password_confirmation">
                        Confirmer le mot de passe
                      </label>
                      <div className="relative group">
                        <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none text-on-surface-variant/60">
                          <FiLock size={16} />
                        </div>
                        <input
                          className="w-full pl-11 pr-4 py-3.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none text-sm"
                          id="password_confirmation"
                          placeholder="Répétez le mot de passe"
                          value={passwordConfirmation}
                          onChange={(e) => setPasswordConfirmation(e.target.value)}
                          type="password"
                          disabled={loading}
                          required
                          autoComplete="new-password"
                        />
                      </div>
                    </div>

                    <button type="submit" disabled={loading}
                      className="w-full bg-gradient-to-br from-primary to-primary-container text-white py-3.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:scale-[0.99] active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-70">
                      {loading ? <FiLoader className="animate-spin" /> : null}
                      {loading ? 'Réinitialisation...' : 'Réinitialiser le mot de passe'}
                    </button>
                  </form>
                </>
              )}
            </div>
          </div>
        </main>
      </div>
    </div>
  );
}
