import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiMail, FiArrowLeft, FiCheckCircle, FiAlertTriangle, FiLoader } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';
import api from '../../api/axios';
import { assets } from '../../utils/assets';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email.trim()) { setError('Veuillez saisir votre adresse email.'); return; }

    setLoading(true);
    setError('');
    try {
      const { data } = await api.post('/forgot-password', { email });
      if (data.success) {
        setSent(true);
      } else {
        setError(data.message || 'Erreur lors de l\'envoi du lien.');
      }
    } catch (err) {
      const msg = err.response?.data?.message
        || (err.response?.data?.errors?.email ? err.response.data.errors.email.join(', ') : null)
        || 'Erreur lors de l\'envoi du lien. Veuillez réessayer.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-surface flex">
      {/* Bannière gauche */}
      <div className="hidden lg:flex lg:w-[45%] relative overflow-hidden">
        <img src={assets.rectoratUac} alt="Campus universitaire"
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
            <h2 className="text-3xl font-bold text-white font-headline leading-tight">Mot de passe oublié ?</h2>
            <p className="text-white/60 text-sm mt-3 max-w-sm">
              Saisissez votre adresse email académique. Un lien de réinitialisation vous sera envoyé.
            </p>
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
              {sent ? (
                <>
                  <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
                    <FiCheckCircle className="text-secondary" size={32} />
                  </div>
                  <h1 className="text-2xl font-bold text-primary text-center mb-3">Email envoyé !</h1>
                  <p className="text-sm text-on-surface-variant text-center mb-8">
                    Si un compte existe avec cette adresse, vous recevrez un email contenant un lien pour réinitialiser votre mot de passe.
                  </p>
                  <button onClick={() => navigate('/login')}
                    className="w-full py-3 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all">
                    Retour à la connexion
                  </button>
                </>
              ) : (
                <>
                  <div className="mb-8">
                    <h1 className="text-2xl font-bold text-primary mb-1.5">Mot de passe oublié</h1>
                    <p className="text-sm text-on-surface-variant">
                      Saisissez votre email pour recevoir un lien de réinitialisation.
                    </p>
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
                          autoComplete="email"
                        />
                      </div>
                    </div>

                    <button type="submit" disabled={loading}
                      className="w-full bg-gradient-to-br from-primary to-primary-container text-white py-3.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:scale-[0.99] active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-70">
                      {loading ? <FiLoader className="animate-spin" /> : null}
                      {loading ? 'Envoi en cours...' : 'Envoyer le lien'}
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
