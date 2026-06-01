import { useState, useEffect } from 'react';
import { FiCheckCircle, FiAlertTriangle, FiLoader, FiHelpCircle } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';
import api from '../../api/axios';

const PresenceValidationPage = () => {
  const [matricule, setMatricule] = useState('');
  const [loading, setLoading] = useState(false);
  const [validated, setValidated] = useState(null);
  const [error, setError] = useState('');
  const [cours, setCours] = useState(null);
  const [coursLoading, setCoursLoading] = useState(true);

  useEffect(() => {
    const fetchCoursEnCours = async () => {
      try {
        const { data } = await api.get('/admin/evenements', {
          params: { statut: 'en_cours', date: new Date().toISOString().split('T')[0] }
        });
        const evts = data.success ? data.data : (data.data || data);
        if (Array.isArray(evts) && evts.length > 0) {
          setCours(evts[0]);
        }
      } catch {
        // Pas de cours en cours
      } finally {
        setCoursLoading(false);
      }
    };
    fetchCoursEnCours();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!matricule.trim()) {
      setError('Veuillez saisir votre matricule');
      return;
    }

    setLoading(true);
    setError('');
    setValidated(null);

    try {
      const { data } = await api.post('/presence/scan', {
        identifiant_unique: matricule.trim(),
        token: cours?.qr_code?.token || '00000000-0000-0000-0000-000000000000',
        device_fingerprint: navigator.userAgent || 'unknown',
      });

      if (data.success) {
        setValidated({
          success: true,
          course: data.data?.cours || cours?.ec?.intitule || 'Cours',
          time: `${cours?.heure_debut || '--:--'} - ${cours?.heure_fin || '--:--'}`,
          message: data.message || 'Présence validée avec succès!'
        });
      } else {
        setValidated({ success: false, message: data.message || 'Erreur de validation' });
      }
    } catch (err) {
      const status = err.response?.status;
      const msg = err.response?.data?.message;
      if (status === 410) {
        setValidated({ success: false, message: 'Session expirée. Veuillez scanner un nouveau QR code.' });
      } else if (status === 409) {
        setValidated({ success: false, message: 'Présence déjà validée pour ce cours.' });
      } else if (status === 403) {
        setValidated({ success: false, message: msg || 'Vous n\'êtes pas inscrit à ce cours ou la fenêtre de validation est fermée.' });
      } else if (status === 404) {
        setValidated({ success: false, message: msg || 'Identifiant étudiant inconnu.' });
      } else {
        setValidated({ success: false, message: msg || 'Erreur lors de la validation. Veuillez réessayer.' });
      }
    } finally {
      setLoading(false);
    }
  };

  const reset = () => {
    setMatricule('');
    setValidated(null);
    setError('');
  };

  return (
    <main className="bg-surface text-on-surface font-body min-h-screen flex flex-col items-center">
      {/* TopAppBar */}
      <header className="w-full top-0 sticky z-50 bg-[#f7f9fd] transition-all duration-200 ease-in-out flex items-center justify-between px-6 py-6">
        <div className="flex items-center gap-3 mx-auto">
          <div className="flex items-center justify-center w-10 h-10 bg-primary rounded-xl">
            <MdAccountBalance />
          </div>
          <div className="flex flex-col">
            <span className="font-headline font-black text-[#1A2B5E] text-xl leading-none">UAC</span>
            <span className="font-headline font-bold text-[#1A2B5E] tracking-tight text-sm opacity-80">Présence</span>
          </div>
        </div>
      </header>

      <main className="flex-1 w-full max-w-md px-6 pt-4 pb-24 flex flex-col items-center">
        {/* Hero Illustration / Visual Context */}
        <div className="w-full mb-8 relative aspect-[16/10] overflow-hidden rounded-3xl">
          <img
            className="w-full h-full object-cover"
            alt="Amphithéâtre universitaire moderne"
            src="https://lh3.googleusercontent.com/aida-public/AB6AXuAMVUYdUtdevU6je4s_NiWDnFTpzoj8T39uMa0v8U8W2s1u9TbcEHkJc15uIY4yrOCZlmKfYzAs2ee5PYarGMiKZV1qE90fFCT80zMPFwylTuJA5lrT1pHGDJszFexIQm3bcAg1IxBOEW8ZlWngQ0tr4OgDerqfK2-3KiPufEoUkG3w8gv1a-3wz4wrk0FavmUDpVv3bUFJp-4AL1zDhCvJM7Er6L75UVSDDAxrB4h-7SHYsbwDAq0fxYqJJoQijfAnqn8QBTHO5A"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-primary/40 to-transparent"></div>
          <div className="absolute bottom-4 left-6 right-6">
            <div className="bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-full inline-flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
              <span className="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant font-technical">
                Session Active
              </span>
            </div>
          </div>
        </div>

        {/* Title Section */}
        <section className="w-full text-center mb-8">
          <h1 className="font-headline font-bold text-2xl text-on-surface mb-3 tracking-tight">
            Valider votre présence
          </h1>
          {coursLoading ? (
            <div className="bg-surface-container-high rounded-2xl p-5 text-center">
              <FiLoader className="animate-spin mx-auto text-primary" />
            </div>
          ) : cours ? (
            <div className="bg-surface-container-high rounded-2xl p-5 text-center">
              <p className="font-body font-semibold text-primary mb-1 text-sm">
                {cours.ec?.intitule || cours.cours || 'Cours'}
              </p>
              <p className="font-technical font-medium text-xs text-on-surface-variant">
                {cours.heure_debut || '--:--'} - {cours.heure_fin || '--:--'}
              </p>
              <p className="font-technical font-medium text-xs text-on-surface-variant mt-1">
                {cours.salle ? `Salle ${cours.salle}` : ''} {cours.filiere?.code ? `• ${cours.filiere.code}` : ''}
              </p>
            </div>
          ) : (
            <div className="bg-surface-container-high rounded-2xl p-5 text-center">
              <p className="font-body font-semibold text-on-surface-variant text-sm">
                Aucun cours en cours actuellement
              </p>
            </div>
          )}
        </section>

        {/* Form Section */}
        <section className="w-full space-y-6">
          {error && (
            <div className="bg-error-container/30 rounded-xl p-4 flex items-start gap-2 text-on-error-container">
              <FiAlertTriangle className="text-lg" />
              <p>{error}</p>
            </div>
          )}

          {validated && validated.success && (
            <div className="bg-secondary-container/30 rounded-xl p-6 flex items-start gap-4 text-on-secondary-container">
              <FiCheckCircle className="text-2xl" />
              <div>
                <h2 className="font-bold text-2xl text-on-surface mb-2">Présence validée !</h2>
                <p className="text-surface-variant">{validated.course}</p>
                <p className="text-surface-variant">{validated.time}</p>
                <p className="font-semibold text-primary mt-2">{validated.message}</p>
                <button onClick={reset} className="mt-4 text-sm text-primary font-semibold hover:underline">
                  Valider une autre présence
                </button>
              </div>
            </div>
          )}

          {validated && !validated.success && (
            <div className="bg-error-container/30 rounded-xl p-4 flex items-start gap-2 text-on-error-container">
              <FiAlertTriangle className="text-lg" />
              <p>{validated.message}</p>
              <button onClick={reset} className="ml-auto text-sm font-semibold hover:underline">Réessayer</button>
            </div>
          )}

          {!validated && (
            <form onSubmit={handleSubmit} className="w-full space-y-6">
              <div className="space-y-2">
                <label className="block text-xs font-semibold text-on-surface-variant ml-1 uppercase tracking-wider" htmlFor="matricule">
                  Identifiant unique
                </label>
                <div className="relative group">
                  <input
                    className="w-full bg-surface-container-lowest border-none rounded-xl px-4 py-4 text-lg font-technical focus:ring-0 transition-all duration-200 peer"
                    id="matricule"
                    placeholder="Ex: 22-UAC-1234"
                    value={matricule}
                    onChange={(e) => setMatricule(e.target.value)}
                    type="text"
                    disabled={loading}
                  />
                  {loading && (
                    <div className="absolute inset-y-0 right-4 flex items-center text-primary">
                      <FiLoader className="h-4 w-4 animate-spin" />
                    </div>
                  )}
                  <div className="absolute bottom-0 left-0 h-0.5 w-0 bg-primary transition-all duration-300 peer-focus:w-full"></div>
                </div>
                <p className="text-[11px] text-on-surface-variant/70 italic ml-1">
                  Saisissez votre matricule étudiant officiel.
                </p>
              </div>

              <button
                type="submit"
                className={`w-full bg-gradient-to-br from-[#011549] to-[#1a2b5e] text-white py-4 px-6 rounded-xl font-headline font-bold text-lg shadow-[0_8px_20px_rgba(1,21,73,0.2)] active:scale-95 transition-transform flex items-center justify-center gap-3 ${loading ? 'opacity-70' : ''}`}
                disabled={loading}
              >
                <FiCheckCircle style={{ fontVariationSettings: '"FILL" 1' }} />
                Valider ma présence
              </button>

              <div className="flex items-center justify-center gap-4 pt-4">
                <div className="h-[1px] flex-1 bg-outline-variant opacity-20"></div>
                <span className="text-[10px] font-bold text-outline uppercase tracking-widest">Aide</span>
                <div className="h-[1px] flex-1 bg-outline-variant opacity-20"></div>
              </div>
              <button
                type="button"
                className="w-full py-3 text-sm font-medium text-on-surface-variant hover:bg-surface-container-low rounded-xl transition-colors flex items-center justify-center gap-2"
              >
                <FiHelpCircle className="text-sm" />
                Besoin d'assistance ?
              </button>
            </form>
          )}
        </section>
      </main>

      <footer className="w-full py-8 mt-auto flex flex-col items-center border-t border-outline-variant/10">
        <p className="text-[10px] font-technical uppercase tracking-widest text-on-surface-variant">
          UAC — Système de Gestion de Présence
        </p>
      </footer>
    </main>
  );
};

export default PresenceValidationPage;
