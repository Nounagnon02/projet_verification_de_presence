import { useState, useEffect, useRef } from 'react';
import {
  FiCheckCircle, FiAlertTriangle, FiLoader,
  FiSmartphone, FiUser, FiArrowRight, FiMail, FiLock, FiLogOut,
  FiClock, FiMapPin, FiBookOpen
} from 'react-icons/fi';
import { MdVerified } from 'react-icons/md';
import { useSearchParams } from 'react-router-dom';
import { recupererCoursParToken } from '../../api/resources/presences';
import apiEtudiant, { enregistrerJetonEtudiant, effacerJetonEtudiant, aUnJetonEtudiant } from '../../api/etudiant';
import { useFingerprint } from '../../hooks/useFingerprint';

/** Longueur exacte du code d'accès tiré et envoyé par l'administration. */
const LONGUEUR_CODE = 6;

const PresenceValidationPage = () => {
  const [searchParams] = useSearchParams();
  const tokenFromUrl = searchParams.get('token') || '';

  const [step, setStep] = useState(tokenFromUrl ? 'scan' : 'idle');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [cours, setCours] = useState(null);
  const [coursLoading, setCoursLoading] = useState(!!tokenFromUrl);
  const inputRef = useRef(null);

  const qrToken = tokenFromUrl;

  // ─── Authentification étudiante ───
  //
  // Le scan exige désormais un jeton étudiant (POST /auth/student/login,
  // email + identifiant unique + code d'accès) : l'identifiant unique seul
  // est déterministe (NOM_PRENOM_MATRICULE_FILIERE_ANNEE) et ne prouvait rien
  // — n'importe quel camarade de promotion pouvait le reconstituer. Le jeton
  // est conservé sous une clé DISTINCTE de celle de l'administrateur
  // (src/api/etudiant.js) : un même navigateur peut servir aux deux sans que
  // l'un n'écrase la session de l'autre.
  const [connecte, setConnecte] = useState(aUnJetonEtudiant());
  const [email, setEmail] = useState('');
  const [identifiantUnique, setIdentifiantUnique] = useState('');
  const [code, setCode] = useState('');
  const [loginErrors, setLoginErrors] = useState({});
  const [loginLoading, setLoginLoading] = useState(false);
  // Message d'aide affiché quand le serveur répond « code_absent » : l'étudiant
  // ne peut rien corriger lui-même, il doit réclamer son code.
  const [codeAbsent, setCodeAbsent] = useState(false);

  // L'empreinte d'appareil sert à la détection d'appareil partagé côté serveur.
  const { visitorId } = useFingerprint();

  // Position de l'appareil. Sans elle, toute salle géolocalisée refuse le scan :
  // le serveur ne recevait aucune coordonnée et répondait « position non
  // transmise ». La page ne demandait pourtant jamais l'autorisation.
  const [position, setPosition] = useState(null);
  const [positionRefusee, setPositionRefusee] = useState(false);

  // Charger les infos du cours depuis le token QR. Annulable : le QR étant
  // renouvelé régulièrement, un étudiant peut rescanner avant la fin de la
  // requête précédente, et c'est la réponse du dernier code scanné qui doit
  // faire foi. Point public : pas besoin d'être connecté pour voir le cours.
  useEffect(() => {
    let annule = false;

    (async () => {
      if (!qrToken) {
        if (!annule) setCoursLoading(false);
        return;
      }

      try {
        const data = await recupererCoursParToken(qrToken);

        if (annule) return;

        if (data.success && data.data) {
          setCours(data.data);
          setStep('scan');
        } else {
          setError('QR Code invalide ou expiré.');
          setStep('error');
        }
      } catch {
        if (!annule) {
          setError('QR Code invalide ou expiré. Veuillez scanner un nouveau code.');
          setStep('error');
        }
      } finally {
        if (!annule) setCoursLoading(false);
      }
    })();

    return () => { annule = true; };
  }, [qrToken]);

  // Demande de la position, uniquement si la salle du cours l'exige — inutile
  // d'ouvrir une invite d'autorisation quand le serveur n'en fera rien.
  useEffect(() => {
    if (!cours?.verification?.gps_requis || position || positionRefusee) return;
    let annule = false;

    (async () => {
      try {
        if (!navigator.geolocation) throw new Error('geolocalisation indisponible');

        const { coords } = await new Promise((resoudre, rejeter) =>
          navigator.geolocation.getCurrentPosition(resoudre, rejeter, {
            // Haute precision : le rayon de georeperage d'une salle est de
            // l'ordre de 50 m, une position approchee au reseau ne suffit pas.
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 30000,
          }),
        );
        if (!annule) setPosition({ latitude: coords.latitude, longitude: coords.longitude });
      } catch {
        // Autorisation refusee, delai depasse, ou materiel indisponible : le
        // serveur enoncera lui-meme le motif du refus.
        if (!annule) setPositionRefusee(true);
      }
    })();

    return () => { annule = true; };
  }, [cours, position, positionRefusee]);

  // Focus automatique sur le premier champ pertinent (connexion ou validation).
  useEffect(() => {
    if (step === 'scan' && inputRef.current) {
      inputRef.current.focus();
    }
  }, [step, connecte]);

  const handleLogin = async (e) => {
    e.preventDefault();
    const erreurs = {};
    if (!email.trim()) erreurs.email = "L'email est requis.";
    if (!identifiantUnique.trim()) erreurs.identifiantUnique = "L'identifiant unique est requis.";
    // Contrôle local : le serveur répond avec un message volontairement
    // générique, qui ne dirait pas à l'étudiant que son code est incomplet.
    if (code.trim().length !== LONGUEUR_CODE) {
      erreurs.code = `Le code d'accès comporte ${LONGUEUR_CODE} chiffres.`;
    }
    if (Object.keys(erreurs).length > 0) {
      setLoginErrors(erreurs);
      return;
    }
    setLoginErrors({});
    setCodeAbsent(false);
    setLoginLoading(true);

    try {
      const { data } = await apiEtudiant.post('/auth/student/login', {
        email: email.trim(),
        identifiant_unique: identifiantUnique.trim(),
        code: code.trim(),
      });

      if (!data.success) {
        throw Object.assign(new Error(data.message || 'Identifiants invalides.'), {
          response: { data },
        });
      }

      enregistrerJetonEtudiant(data.data.token);
      setConnecte(true);
      setCode('');
    } catch (err) {
      const donnees = err.response?.data;
      if (donnees?.code === 'code_absent') {
        setCodeAbsent(true);
      } else {
        setLoginErrors({ general: donnees?.message || 'Identifiants invalides.' });
      }
    } finally {
      setLoginLoading(false);
    }
  };

  const handleLogout = () => {
    effacerJetonEtudiant();
    setConnecte(false);
    setEmail('');
    setIdentifiantUnique('');
    setCode('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    setLoading(true);
    setError('');
    setResult(null);

    try {
      // L'étudiant est désigné par le jeton (apiEtudiant l'injecte) : plus
      // d'identifiant posté dans le corps, et plus de défi anti-fraude — voir
      // le docblock de connexion ci-dessus.
      const { data } = await apiEtudiant.post('/presence/scan', {
        token: qrToken,
        device_fingerprint: visitorId || navigator.userAgent || 'unknown',
        // Omises plutot qu'envoyees a null : le serveur distingue « position
        // absente » de « position hors zone », et les messages diffèrent.
        ...(position ? { latitude: position.latitude, longitude: position.longitude } : {}),
      });

      if (data.success) {
        setResult({
          success: true,
          course: data.data?.cours || cours?.cours || 'Cours',
          time: `${cours?.heure_debut || '--:--'} - ${cours?.heure_fin || '--:--'}`,
          message: data.message || 'Présence validée avec succès !',
          student: data.data?.etudiant || '',
        });
        setStep('success');
      } else {
        setError(data.message || 'Erreur de validation.');
        setStep('error');
      }
    } catch (err) {
      const status = err.response?.status;
      const msg = err.response?.data?.message;
      if (status === 401) {
        // Jeton étudiant expiré ou révoqué : retour à l'écran de connexion,
        // pas à celui de l'administrateur — ce sont deux sessions distinctes.
        effacerJetonEtudiant();
        setConnecte(false);
        setError('Votre session a expiré. Reconnectez-vous pour valider votre présence.');
      } else if (status === 410) {
        setError('Session expirée. Veuillez scanner un nouveau QR code.');
      } else if (status === 409) {
        setError('Présence déjà validée pour ce cours.');
      } else if (status === 403) {
        setError(msg || "Vous n'êtes pas inscrit à ce cours ou la fenêtre de validation est fermée.");
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
            {result.student && (
              <div className="bg-success/5 rounded-xl p-3 flex items-center gap-3">
                <FiUser className="text-success shrink-0" size={16} />
                <div>
                  <p className="text-xs text-on-surface-variant">Étudiant</p>
                  <p className="text-sm font-semibold text-on-surface">{result.student}</p>
                </div>
              </div>
            )}
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

  // ─── SCREEN: CONNEXION / SCAN (PRINCIPAL) ─────────────
  return (
    <div className="min-h-screen bg-surface flex flex-col">
      {/* TopBar */}
      <header className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-outline-variant/10">
        <div className="max-w-md mx-auto px-5 py-3">
          <div className="flex items-center justify-between gap-3 mb-2">
            <img src="/images/logo-couleur-compact.png" alt="UAC Présences"
              className="h-6 w-auto shrink-0" />
            <div className="flex items-center gap-2 shrink-0">
              {qrToken && (
                <div className="bg-secondary/10 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                  <span className="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
                  <span className="text-[9px] font-bold uppercase tracking-widest text-secondary">Session active</span>
                </div>
              )}
              {connecte && (
                <button onClick={handleLogout} title="Se déconnecter"
                  className="p-1.5 rounded-full hover:bg-surface-container-high text-on-surface-variant transition-colors">
                  <FiLogOut size={14} />
                </button>
              )}
            </div>
          </div>
          <h1 className="font-headline font-bold text-primary text-base leading-snug">
            Enregistrement de présence
          </h1>
          <p className="text-[11px] text-on-surface-variant font-medium leading-snug">
            {connecte
              ? 'Confirmez votre présence pour ce cours'
              : 'Connectez-vous pour confirmer votre présence'}
          </p>
        </div>
      </header>

      <main className="flex-1 max-w-md mx-auto w-full px-5 pt-6 pb-12">
        {/* Infos cours */}
        {cours && (
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
        )}

        <div className="animate-[fadeIn_0.3s_ease-out_0.1s_both]">
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
            </div>
          )}

          {cours?.verification?.wifi_requis && (
            <div className="bg-warning/10 rounded-xl p-3.5 flex items-start gap-2.5 border border-warning/20 mb-5">
              <FiSmartphone className="text-warning shrink-0 mt-0.5" size={16} />
              <div className="text-sm text-on-surface">
                <p className="font-semibold">Validation par l'application mobile</p>
                <p className="text-on-surface-variant text-xs mt-0.5">
                  Cette salle vérifie le réseau Wi-Fi, une information qu'un
                  navigateur ne peut pas lire. Utilisez l'application UAC
                  Présences pour valider votre présence.
                </p>
              </div>
            </div>
          )}

          {/* ─── Étape A : connexion (email + identifiant + code) ─────── */}
          {!connecte && (
            <form onSubmit={handleLogin} className="space-y-5">
              <p className="text-xs text-on-surface-variant text-center -mt-1 mb-1">
                Identifiant et code reçus par e-mail lors de votre inscription.
              </p>

              {loginErrors.general && (
                <div className="bg-error/10 rounded-xl p-3.5 flex items-start gap-2.5 border border-error/10 animate-[shake_0.4s_ease-out]">
                  <FiAlertTriangle className="text-error shrink-0 mt-0.5" size={16} />
                  <p className="text-sm text-error font-medium">{loginErrors.general}</p>
                </div>
              )}

              {codeAbsent && (
                <div className="rounded-xl border border-warning/20 bg-warning/10 p-3.5 flex items-start gap-2.5">
                  <FiAlertTriangle className="text-warning shrink-0 mt-0.5" size={16} />
                  <p className="text-sm text-on-surface">
                    Aucun code d'accès n'a encore été envoyé pour ce compte.
                    Demandez-le à votre administration : il vous parviendra par
                    e-mail avec vos identifiants.
                  </p>
                </div>
              )}

              <div className="space-y-2">
                <label className="block text-xs font-semibold text-on-surface-variant ml-1 uppercase tracking-wider" htmlFor="email">
                  Email
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-outline">
                    <FiMail size={16} />
                  </div>
                  <input
                    ref={inputRef}
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="votre.email@uac.bj"
                    disabled={loginLoading}
                    autoComplete="email"
                    className="w-full bg-surface-container-lowest border-2 border-outline-variant/20 rounded-xl pl-11 pr-4 py-3.5 text-base focus:border-primary focus:outline-none transition-all disabled:opacity-60"
                  />
                </div>
                {loginErrors.email && <p className="text-xs text-error ml-1">{loginErrors.email}</p>}
              </div>

              <div className="space-y-2">
                <label className="block text-xs font-semibold text-on-surface-variant ml-1 uppercase tracking-wider" htmlFor="identifiant">
                  Identifiant unique
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-outline">
                    <FiUser size={16} />
                  </div>
                  <input
                    id="identifiant"
                    type="text"
                    value={identifiantUnique}
                    onChange={(e) => setIdentifiantUnique(e.target.value)}
                    placeholder="Ex: DOE_JOHN_22A1234_GLT_L3"
                    disabled={loginLoading}
                    autoComplete="off"
                    className="w-full bg-surface-container-lowest border-2 border-outline-variant/20 rounded-xl pl-11 pr-4 py-3.5 text-base font-mono focus:border-primary focus:outline-none transition-all disabled:opacity-60"
                  />
                </div>
                {loginErrors.identifiantUnique && <p className="text-xs text-error ml-1">{loginErrors.identifiantUnique}</p>}
              </div>

              <div className="space-y-2">
                <label className="block text-xs font-semibold text-on-surface-variant ml-1 uppercase tracking-wider" htmlFor="code">
                  Code d'accès
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-outline">
                    <FiLock size={16} />
                  </div>
                  <input
                    id="code"
                    type="password"
                    inputMode="numeric"
                    value={code}
                    onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, LONGUEUR_CODE))}
                    placeholder="••••••"
                    disabled={loginLoading}
                    maxLength={LONGUEUR_CODE}
                    autoComplete="one-time-code"
                    className="w-full bg-surface-container-lowest border-2 border-outline-variant/20 rounded-xl pl-11 pr-4 py-3.5 text-base font-mono tracking-widest focus:border-primary focus:outline-none transition-all disabled:opacity-60"
                  />
                </div>
                {loginErrors.code && <p className="text-xs text-error ml-1">{loginErrors.code}</p>}
              </div>

              <button
                type="submit"
                disabled={loginLoading}
                className="w-full bg-gradient-to-br from-primary to-primary-container text-white py-4 rounded-xl font-headline font-bold text-base shadow-lg shadow-primary/20 active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-70 hover:shadow-xl hover:shadow-primary/30"
              >
                {loginLoading ? <FiLoader className="animate-spin" size={20} /> : <FiArrowRight size={20} />}
                {loginLoading ? 'Connexion...' : 'Se connecter'}
              </button>
            </form>
          )}

          {/* ─── Étape B : validation (connecté) ───────────────────────── */}
          {connecte && (
            <form onSubmit={handleSubmit} className="space-y-5">
              {error && (
                <div className="bg-error/10 rounded-xl p-3.5 flex items-start gap-2.5 border border-error/10 animate-[shake_0.4s_ease-out]">
                  <FiAlertTriangle className="text-error shrink-0 mt-0.5" size={16} />
                  <p className="text-sm text-error font-medium">{error}</p>
                </div>
              )}

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
            </form>
          )}
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
