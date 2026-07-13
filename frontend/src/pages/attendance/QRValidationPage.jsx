import { useState, useEffect } from 'react';
import { FiCamera, FiCheckCircle, FiAlertTriangle, FiLoader, FiSmartphone, FiUser } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';
import { useSearchParams } from 'react-router-dom';
import api from '../../api/axios';
import { useFingerprint } from '../../hooks/useFingerprint';

export default function QRValidationPage() {
  const [searchParams] = useSearchParams();
  const tokenFromUrl = searchParams.get('token') || '';
  const [mode, setMode] = useState('scan');
  const [matricule, setMatricule] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [cours, setCours] = useState(null);
  const qrToken = tokenFromUrl;

  // Device fingerprinting pour anti-fraude
  const { visitorId, fingerprint, loading: fpLoading, createScanChallenge, isReady } = useFingerprint();

  useEffect(() => {
    const fetchCourseInfo = async () => {
      if (!qrToken) return;
      try {
        const { data } = await api.get(`/presence/course-by-token/${qrToken}`);
        if (data.success && data.data) {
          setCours(data.data);
        }
      } catch { /* token invalide */ }
    };
    fetchCourseInfo();
  }, [qrToken]);

  const handleManualSubmit = async (e) => {
    e.preventDefault();
    if (!matricule.trim()) return;

    // Attendre que le fingerprint soit prêt
    if (!isReady && fpLoading) {
      // Attendre un peu que le fingerprint soit prêt
      await new Promise(resolve => setTimeout(resolve, 1000));
    }

    setLoading(true);
    try {
      // Générer un challenge de scan avec fingerprint
      const { challenge, visitorId: fpVisitorId } = await createScanChallenge();

      const { data } = await api.post('/presence/scan', {
        identifiant_unique: matricule.trim(),
        token: qrToken || '00000000-0000-0000-0000-000000000000',
        device_fingerprint: fpVisitorId || 'unknown',
        scan_challenge: challenge,
      });
      setResult({ success: true, ...data.data });
      setMode('success');
    } catch (err) {
      const status = err.response?.status;
      if (status === 410) {
        setResult({ success: false, message: 'Session expirée. Veuillez scanner un nouveau QR code.' });
      } else if (status === 409) {
        setResult({ success: false, message: 'Présence déjà validée pour ce cours.' });
      } else if (status === 403) {
        setResult({ success: false, message: err.response?.data?.message || 'Appareil non reconnu. Veuillez contacter l\'administration.' });
      } else {
        setResult({ success: false, message: err.response?.data?.message || 'Matricule invalide. Veuillez vérifier votre saisie.' });
      }
      setMode('error');
    } finally {
      setLoading(false);
    }
  };

  const reset = () => {
    setMode('scan');
    setMatricule('');
    setResult(null);
  };

  if (mode === 'success') {
    return (
      <div className="min-h-screen bg-surface flex flex-col items-center justify-center p-6">
        <div className="w-20 h-20 bg-secondary/10 rounded-full flex items-center justify-center mb-6 animate-in zoom-in-95 duration-300">
          <FiCheckCircle className="text-secondary" size={40} />
        </div>
        <h1 className="text-2xl font-bold font-headline text-primary mb-2">Présence validée !</h1>
        <p className="text-on-surface-variant text-center mb-8">Votre présence a été enregistrée avec succès.</p>
        <button onClick={reset} className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all">
          Valider une autre présence
        </button>
      </div>
    );
  }

  if (mode === 'error') {
    return (
      <div className="min-h-screen bg-surface flex flex-col items-center justify-center p-6">
        <div className="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mb-6">
          <FiAlertTriangle className="text-error" size={40} />
        </div>
        <h1 className="text-2xl font-bold font-headline text-primary mb-2">Validation échouée</h1>
        <p className="text-on-surface-variant text-center mb-8">{result?.message}</p>
        <button onClick={reset} className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all">
          Réessayer
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-surface">
      <header className="flex items-center justify-between px-6 py-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white">
            <MdAccountBalance />
          </div>
          <div>
            <span className="font-headline font-bold text-primary text-xl leading-none">Présence</span>
          </div>
        </div>
        <div className="bg-secondary/10 px-3 py-1.5 rounded-full flex items-center gap-2">
          <span className="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
          <span className="text-[10px] font-bold uppercase tracking-widest text-secondary">Session Active</span>
        </div>
      </header>

      <div className="max-w-md mx-auto px-6 pb-24">
        <div className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm mb-8">
          <p className="text-xs text-on-surface-variant uppercase tracking-wider mb-1">Cours en cours</p>
          <p className="text-base font-bold text-primary">{cours?.cours || (qrToken ? 'Cours' : 'Scanner un QR code')}</p>
          {cours && (
            <div className="flex items-center gap-4 mt-2 text-xs text-on-surface-variant">
              <span>{cours.salle ? `Salle ${cours.salle}` : ''}</span>
              <span>{cours.heure_debut || ''} - {cours.heure_fin || ''}</span>
            </div>
          )}
        </div>

        <div className="flex flex-col items-center mb-8">
          <div className="relative w-64 h-64 mb-6">
            <div className="absolute inset-0 border-2 border-primary/30 rounded-3xl animate-pulse"></div>
            <div className="absolute inset-2 border-2 border-primary/20 rounded-2xl"></div>
            <div className="absolute inset-4 border-2 border-primary/10 rounded-xl"></div>
            <div className="absolute left-8 right-8 h-0.5 bg-gradient-to-r from-transparent via-primary to-transparent animate-[scan_2s_ease-in-out_infinite] top-1/2"></div>
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="p-6 bg-surface-container-high rounded-full">
                <FiCamera className="text-primary" size={48} />
              </div>
            </div>
          </div>
          <p className="text-sm text-on-surface-variant text-center mb-4">
            Placez le QR code étudiant dans le cadre
          </p>
          <button
            onClick={() => setMode('manual')}
            className="flex items-center gap-2 text-sm text-primary font-semibold hover:underline"
          >
            <FiSmartphone /> Saisir le matricule manuellement
          </button>
        </div>
      </div>

      {mode === 'manual' && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-4">
          <div className="bg-surface w-full max-w-md rounded-2xl p-6 animate-in slide-in-from-bottom-2 duration-200">
            <div className="flex items-center gap-3 mb-6">
              <div className="p-2 bg-primary/10 rounded-xl">
                <FiUser className="text-primary" />
              </div>
              <div>
                <h2 className="text-lg font-bold font-headline text-primary">Saisie manuelle</h2>
                <p className="text-xs text-on-surface-variant">Entrez le matricule de l'étudiant</p>
              </div>
            </div>

            <form onSubmit={handleManualSubmit} className="space-y-4">
              <input
                className="w-full px-4 py-3 bg-surface-container-high rounded-xl text-lg font-mono focus:outline-none border-b-2 border-transparent focus:border-primary transition-all"
                placeholder="22-XXXX-XXXX"
                value={matricule}
                onChange={(e) => setMatricule(e.target.value)}
                disabled={loading}
                autoFocus
              />
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setMode('scan')}
                  className="flex-1 py-3 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  disabled={loading || !matricule.trim()}
                  className="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {loading ? <FiLoader className="animate-spin" /> : null}
                  Valider
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <style>{`
        @keyframes scan {
          0%, 100% { top: 25%; }
          50% { top: 75%; }
        }
      `}</style>
    </div>
  );
}
