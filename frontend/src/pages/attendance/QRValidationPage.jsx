import { useState, useEffect } from 'react';
import { FiCheckCircle, FiAlertTriangle, FiLoader, FiUser } from 'react-icons/fi';
import { useSearchParams } from 'react-router-dom';
import api from '../../api/axios';
import { useFingerprint } from '../../hooks/useFingerprint';

export default function QRValidationPage() {
  const [searchParams] = useSearchParams();
  const tokenFromUrl = searchParams.get('token') || '';
  // 'scan' : formulaire de saisie ; 'success' / 'error' : ecran de resultat.
  const [mode, setMode] = useState('scan');
  const [matricule, setMatricule] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [cours, setCours] = useState(null);
  const qrToken = tokenFromUrl;

  // Le defi anti-fraude est emis par le serveur (course-by-token) ; l'empreinte
  // d'appareil sert a la detection d'appareil partage.
  const { visitorId } = useFingerprint();

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

    if (!cours?.scan_challenge) {
      setResult({ success: false, message: 'QR Code invalide ou expiré. Veuillez scanner un nouveau code.' });
      setMode('error');
      return;
    }

    setLoading(true);
    try {
      const { data } = await api.post('/presence/scan', {
        identifiant_unique: matricule.trim(),
        token: qrToken,
        device_fingerprint: visitorId || 'unknown',
        scan_challenge: cours.scan_challenge,
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
    // Cet ecran est imbrique dans la coquille d'administration : ni plein ecran,
    // ni logo propre — la barre laterale et l'en-tete les fournissent deja.
    <div>
      <div className="flex items-start justify-between gap-4 mb-6">
        <div>
          <h1 className="text-xl font-bold font-headline text-primary">Saisie manuelle</h1>
          <p className="text-sm text-on-surface-variant mt-0.5">
            Enregistrer une présence pour un étudiant dont l'appareil ne peut pas
            scanner : panne, batterie vide, absence de connexion.
          </p>
        </div>
        {qrToken && (
          <div className="bg-secondary/10 px-3 py-1.5 rounded-full flex items-center gap-2 shrink-0">
            <span className="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>
            <span className="text-[10px] font-bold uppercase tracking-widest text-secondary">Session active</span>
          </div>
        )}
      </div>

      <div className="max-w-2xl">
        <div className="bg-surface-container-lowest rounded-2xl p-5 shadow-sm mb-6 border border-outline-variant/10">
          <p className="text-xs text-on-surface-variant uppercase tracking-wider mb-1">Séance concernée</p>
          <p className="text-base font-bold text-primary">
            {cours?.cours ?? (qrToken ? 'Chargement…' : 'Aucune séance sélectionnée')}
          </p>
          {cours && (
            <div className="flex items-center gap-4 mt-2 text-xs text-on-surface-variant">
              <span>{cours.salle ? `Salle ${cours.salle}` : ''}</span>
              <span>{cours.heure_debut || ''} - {cours.heure_fin || ''}</span>
            </div>
          )}
        </div>

        {/* Le formulaire est directement sur la page. Il etait auparavant
            derriere un faux viseur d'appareil photo — decoratif, il ne scannait
            rien — et une modale : deux clics pour atteindre la seule action que
            cet ecran propose. */}
        <form onSubmit={handleManualSubmit} className="bg-surface-container-lowest rounded-2xl p-5 shadow-sm border border-outline-variant/10 space-y-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-xl">
              <FiUser className="text-primary" size={18} />
            </div>
            <div>
              <h2 className="text-sm font-bold text-primary">Identifiant de l'étudiant</h2>
              <p className="text-xs text-on-surface-variant">
                Format NOM_PRENOM_MATRICULE_FILIERE_ANNEE
              </p>
            </div>
          </div>

          <input
            className="w-full px-4 py-3 bg-surface-container-high rounded-xl text-base font-mono focus:outline-none border-b-2 border-transparent focus:border-primary transition-all disabled:opacity-60"
            placeholder="Ex : DOE_JOHN_22A1234_GLT_L3"
            value={matricule}
            onChange={(e) => setMatricule(e.target.value)}
            disabled={loading}
            autoComplete="off"
          />

          {!qrToken && (
            <p className="text-xs text-warning flex items-start gap-1.5">
              <FiAlertTriangle size={13} className="shrink-0 mt-0.5" />
              Aucun QR Code actif : générez-en un depuis la fiche de l'événement,
              puis revenez ici avec son lien.
            </p>
          )}

          <button
            type="submit"
            disabled={loading || !matricule.trim() || !qrToken}
            className="w-full py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
          >
            {loading ? <FiLoader className="animate-spin" size={16} /> : <FiCheckCircle size={16} />}
            {loading ? 'Enregistrement…' : 'Enregistrer la présence'}
          </button>
        </form>
      </div>

    </div>
  );
}
