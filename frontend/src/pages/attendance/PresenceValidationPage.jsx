import { useState, useEffect, useRef } from 'react';
import {
  FiCheckCircle, FiAlertTriangle, FiLoader, FiHelpCircle,
  FiSmartphone, FiCamera, FiUser, FiArrowRight, FiShield,
  FiClock, FiMapPin, FiBookOpen
} from 'react-icons/fi';
import { MdAccountBalance, MdVerified } from 'react-icons/md';
import { useSearchParams } from 'react-router-dom';
import api from '../../api/axios';

const PresenceValidationPage = () => {
  const [searchParams] = useSearchParams();
  const tokenFromUrl = searchParams.get('token') || '';

  const [step, setStep] = useState(tokenFromUrl ? 'scan' : 'idle');
  const [matricule, setMatricule] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [cours, setCours] = useState(null);
  const [coursLoading, setCoursLoading] = useState(!!tokenFromUrl);
  const inputRef = useRef(null);

  const qrToken = tokenFromUrl;

  // Charger les infos du cours depuis le token QR
  useEffect(() => {
    if (!qrToken) {
      setCoursLoading(false);
      return;
    }
    const fetchCourse = async () => {
      try {
        const { data } = await api.get(`/presence/course-by-token/${qrToken}`);
        if (data.success && data.data) {
          setCours(data.data);
          setStep('scan');
        } else {
          setError('QR Code invalide ou expiré.');
          setStep('error');
        }
      } catch {
        setError('QR Code invalide ou expiré. Veuillez scanner un nouveau code.');
        setStep('error');
      } finally {
        setCoursLoading(false);
      }
    };
    fetchCourse();
  }, [qrToken]);

  // Focus automatique sur le champ matricule
  useEffect(() => {
    if (step === 'scan' && inputRef.current) {
      inputRef.current.focus();
    }
  }, [step]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!matricule.trim()) {
      setError('Veuillez saisir votre matricule');
      return;
    }

    setLoading(true);
    setError('');
    setResult(null);

    try {
      const { data } = await api.post('/presence/scan', {
        identifiant_unique: matricule.trim(),
        token: qrToken || '00000000-0000-0000-0000-000000000000',
        device_fingerprint: navigator.userAgent || 'unknown',
      });

      if (data.success) {
        setResult({
          success: true,
          course: data.data?.cours || cours?.cours || 'Cours',
          time: `${cours?.heure_debut || '--:--'} - ${cours?.heure_fin || '--:--'}`,
          message: data.message || 'Présence validée avec succès !',
          student: data.data?.etudiant || matricule.trim(),
        });
        setStep('success');
      } else {
        setError(data.message || 'Erreur de validation.');
        setStep('error');
      }
    } catch (err) {
      const status = err.response?.status;
      const msg = err.response?.data?.message;
      if (status === 410) {
        setError('Session expirée. Veuillez scanner un nouveau QR code.');
      } else if (status === 409) {
        setError('Présence déjà validée pour ce cours.');
      } else if (status === 403) {
        setError(msg || 'Vous n\'êtes pas inscrit à ce cours ou la fenêtre de validation est fermée.');
      } else if (status === 404) {
        setError(msg || 'Identifiant étudiant inconnu. Vérifiez votre matricule.');
      } else {
        setError(msg || 'Erreur lors de la validation. Veuillez réessayer.');
      }
      setStep('error');
    } finally {
      setLoading(false);
    }
  };

  const resetAll = () => {
    setStep('idle');
    setMatricule('');
    setResult(null);
    setError('');
    setCours(null);
  };

  // ─── SCREEN: SUCCÈS ───────────────────────────────────
  if (step === 'success' && result?.success) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#f0fdf4] to-[#dcfce7] flex flex-col">
        <div className="flex-1 flex flex-col items-center justify-center px-6 py-12">
          {/* Animation check */}
          <div className="relative mb-8">
            <div className="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg shadow-success/20 animate-[bounce-in_0.5s_ease-out]">
              <MdVerified className="text-success" size={56} />
            </div>
            <div className="absolute -top-1 -right-1 w-8 h-8 bg-success rounded-full flex items-center justify-center animate-[bounce-in_0.6s_ease-out_0.2s_both]">
              <FiCheckCircle className="text-white" size={18} />
            </div>
          </div>

          <h1 className="text-3xl font-bold font-headline text-on-surface mb-2 text-center">
            Présence validée !
          </h1>
          <p className="text-on-surface-variant text-center mb-8 max-w-xs">
            Votre présence a été enregistrée avec succès.
          </p>

          {/* Course info card */}
          <div className="w-full max-w-sm bg-white/80 backdrop-blur-sm rounded-2xl p-5 shadow-sm border border-success/20 mb-6">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                <FiBookOpen className="text-success" size={20} />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-bold text-sm text-on-surface truncate">
                  {result.course}
                </p>
                <p className="text-xs text-on-surface-variant">
                  {result.time}
                </p>
              </div>
            </div>
            <div className="bg-success/5 rounded-xl p-3 flex items-center gap-3">
              <FiUser className="text-success shrink-0" size={16} />
              <div>
                <p className="text-xs text-on-surface-variant">Étudiant</p>
                <p className="text-sm font-semibold text-on-surface font-mono">{result.student}</p>
              </div>
            </div>
          </div>

          <button onClick={resetAll}
            className="w-full max-w-sm py-3.5 bg-primary text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
            <FiArrowRight size={16} />
            Valider une autre présence
          </button>
        </div>

        <footer className="py-6 text-center">
          <p className="text-[10px] font-technical uppercase tracking-widest text-on-surface-variant/60">
            Système de Gestion de Présence — UAC
          </p>
        </footer>

        <style>{`
          @keyframes bounce-in {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
          }
        `}</style>
      </div>
    );
  }

  // ─── SCREEN: ERREUR ───────────────────────────────────
  if (step === 'error') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#fef2f2] to-[#fee2e2] flex flex-col">
        <div className="flex-1 flex flex-col items-center justify-center px-6 py-12">
          <div className="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg shadow-error/20 mb-8">
            <FiAlertTriangle className="text-error" size={48} />
          </div>

          <h1 className="text-2xl font-bold font-headline text-on-surface mb-2 text-center">
            Validation échouée
          </h1>
          <p className="text-on-surface-variant text-center mb-8 max-w-xs">
            {error}
          </p>

          <div className="flex flex-col w-full max-w-sm gap-3">
            <button onClick={() => { setStep('scan'); setError(''); }}
              className="w-full py-3.5 bg-primary text-white rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:opacity-90 active:scale-[0.98] transition-all">
              Réessayer
            </button>
            <button onClick={resetAll}
              className="w-full py-3 bg-surface-container-high text-on-surface-variant rounded-xl font-semibold text-sm hover:bg-surface-container-higher transition-all">
              Nouveau scan
            </button>
          </div>
        </div>

        <footer className="py-6 text-center">
          <p className="text-[10px] font-technical uppercase tracking-widest text-on-surface-variant/60">
            Système de Gestion de Présence — UAC
          </p>
        </footer>
      </div>
    );
  }

  // ─── SCREEN: CHARGEMENT DU QR ─────────────────────────
  if (coursLoading) {
    return (
      <div className="min-h-screen bg-surface flex flex-col items-center justify-center px-6">
        <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-6">
          <FiLoader className="animate-spin text-primary" size={32} />
        </div>
        <p className="text-on-surface-variant text-sm">Récupération des informations du cours...</p>
      </div>
    );
  }

  // ─── SCREEN: SCAN (PRINCIPAL) ─────────────────────────
  return (
    <div className="min-h-screen bg-surface flex flex-col">
      {/* TopBar */}
      <header className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-outline-variant/10">
        <div className="max-w-md mx-auto px-5 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 bg-gradient-to-br from-primary to-primary-container rounded-xl flex items-center justify-center text-white shadow-sm">
              <MdAccountBalance size={18} />
            </div>
            <div>
              <span className="font-headline font-bold text-primary text-lg leading-none block">Présence</span>
              <span className="text-[10px] text-on-surface-variant font-medium">Validation étudiant</span>
            </div>
          </div>
          {qrToken && (
            <div className="bg-secondary/10 px-3 py-1.5 rounded-full flex items-center gap-1.5">
              <span className="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
              <span className="text-[9px] font-bold uppercase tracking-widest text-secondary">Session active</span>
            </div>
          )}
        </div>
      </header>

      <main className="flex-1 max-w-md mx-auto w-full px-5 pt-6 pb-12">
        {/* Étape 1: Infos cours */}
        {cours ? (
          <div className="mb-6 animate-[fadeIn_0.3s_ease-out]">
            <div className="bg-gradient-to-br from-primary to-primary-container rounded-2xl p-5 text-white shadow-lg shadow-primary/20">
              <p className="text-white/70 text-[10px] font-bold uppercase tracking-wider mb-2">Cours en cours</p>
              <h2 className="text-xl font-bold font-headline mb-3">{cours.cours || 'Cours'}</h2>
              <div className="flex flex-wrap items-center gap-4 text-white/80 text-xs">
                {cours.heure_debut && (
                  <span className="flex items-center gap-1.5">
                    <FiClock size={13} />
                    {cours.heure_debut} - {cours.heure_fin}
                  </span>
                )}
                {cours.salle && (
                  <span className="flex items-center gap-1.5">
                    <FiMapPin size={13} />
                    {cours.salle}
                  </span>
                )}
                {cours.filiere && (
                  <span className="flex items-center gap-1.5">
                    <FiBookOpen size={13} />
                    {cours.filiere}
                  </span>
                )}
              </div>
            </div>
          </div>
        ) : (
          <div className="mb-6">
            <div className="bg-surface-container-high rounded-2xl p-5 text-center border border-outline-variant/10">
              <div className="w-14 h-14 bg-surface-container-lowest rounded-full flex items-center justify-center mx-auto mb-3">
                <FiCamera className="text-on-surface-variant" size={24} />
              </div>
              <p className="text-sm text-on-surface-variant">
                Scannez un QR code pour valider votre présence
              </p>
            </div>
          </div>
        )}

        {/* Étape 2: Formulaire matricule */}
        <div className="animate-[fadeIn_0.3s_ease-out_0.1s_both]">
          {/* Scan visuel */}
          {qrToken && (
            <div className="flex flex-col items-center mb-6">
              <div className="relative w-48 h-48 mb-4">
                <div className="absolute inset-0 border-[3px] border-primary/30 rounded-2xl"></div>
                <div className="absolute inset-3 border-[3px] border-primary/20 rounded-xl"></div>
                <div className="absolute inset-0 flex items-center justify-center">
                  <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center">
                    <FiSmartphone className="text-primary" size={32} />
                  </div>
                </div>
                <div className="absolute left-6 right-6 h-0.5 bg-gradient-to-r from-transparent via-primary to-transparent animate-[scanLine_2s_ease-in-out_infinite]"></div>
              </div>
              <p className="text-xs text-on-surface-variant text-center mb-1">
                Validez votre présence en saisissant votre matricule
              </p>
              <p className="text-[10px] text-on-surface-variant/60 text-center">
                Le matricule se trouve sur votre carte d'étudiant
              </p>
            </div>
          )}

          {/* Formulaire */}
          <form onSubmit={handleSubmit} className="space-y-5">
            {error && (
              <div className="bg-error/10 rounded-xl p-3.5 flex items-start gap-2.5 border border-error/10 animate-[shake_0.4s_ease-out]">
                <FiAlertTriangle className="text-error shrink-0 mt-0.5" size={16} />
                <p className="text-sm text-error font-medium">{error}</p>
              </div>
            )}

            <div className="space-y-2">
              <label className="block text-xs font-semibold text-on-surface-variant ml-1 uppercase tracking-wider" htmlFor="matricule">
                Identifiant unique
              </label>
              <div className="relative group">
                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
                  <FiUser size={16} />
                </div>
                <input
                  ref={inputRef}
                  id="matricule"
                  type="text"
                  value={matricule}
                  onChange={(e) => setMatricule(e.target.value)}
                  placeholder="Ex: 22-XXXX-XXXX"
                  disabled={loading}
                  className="w-full bg-surface-container-lowest border-2 border-outline-variant/20 rounded-xl pl-11 pr-4 py-3.5 text-base font-mono focus:border-primary focus:outline-none transition-all peer disabled:opacity-60"
                  autoComplete="off"
                />
                <div className="absolute bottom-0 left-3 right-3 h-0.5 bg-gradient-to-r from-primary/50 to-primary scale-x-0 peer-focus:scale-x-100 transition-transform duration-300 rounded-full"></div>
              </div>
              <p className="text-[11px] text-on-surface-variant/60 italic ml-1 flex items-center gap-1">
                <FiShield size={10} />
                Saisissez le matricule officiel figurant sur votre carte d'étudiant.
              </p>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-gradient-to-br from-primary to-primary-container text-white py-4 rounded-xl font-headline font-bold text-base shadow-lg shadow-primary/20 active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-70 hover:shadow-xl hover:shadow-primary/30"
            >
              {loading ? (
                <FiLoader className="animate-spin" size={20} />
              ) : (
                <FiCheckCircle size={20} />
              )}
              {loading ? 'Validation...' : 'Valider ma présence'}
            </button>

            <div className="relative my-6">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-outline-variant/10"></div>
              </div>
              <div className="relative flex justify-center">
                <span className="bg-surface px-3 text-[10px] text-outline uppercase tracking-widest">Aide</span>
              </div>
            </div>

            <button
              type="button"
              className="w-full py-3 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              <FiHelpCircle className="text-sm" />
              Besoin d'assistance ? Contactez le support
            </button>
          </form>
        </div>
      </main>

      {/* Footer */}
      <footer className="py-5 border-t border-outline-variant/5">
        <p className="text-[10px] font-technical uppercase tracking-widest text-on-surface-variant/60 text-center">
          Université d'Abomey-Calavi — Système de Gestion de Présence
        </p>
      </footer>

      <style>{`
        @keyframes scanLine {
          0%, 100% { top: 20%; }
          50% { top: 75%; }
        }
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(8px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shake {
          0%, 100% { transform: translateX(0); }
          25% { transform: translateX(-4px); }
          75% { transform: translateX(4px); }
        }
      `}</style>
    </div>
  );
};

export default PresenceValidationPage;
