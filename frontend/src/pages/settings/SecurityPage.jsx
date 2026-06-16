import { useState, useEffect } from 'react';
import { FiShield, FiLock, FiCheck, FiX, FiCopy, FiAlertTriangle, FiEye, FiEyeOff } from 'react-icons/fi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';

export default function SecurityPage() {
  const [profile, setProfile] = useState(null);
  const { addToast } = useToastCtx();

  // Mot de passe
  const [passwordForm, setPasswordForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [saved, setSaved] = useState(false);
  const [pwError, setPwError] = useState('');
  const [showPw, setShowPw] = useState({ current: false, new: false, confirm: false });

  // 2FA
  const [show2FAModal, setShow2FAModal] = useState(false);
  const [twoFAStep, setTwoFAStep] = useState('start');
  const [qrCodeSvg, setQrCodeSvg] = useState('');
  const [twoFASecret, setTwoFASecret] = useState('');
  const [twoFACode, setTwoFACode] = useState('');
  const [twoFAError, setTwoFAError] = useState('');
  const [twoFALoading, setTwoFALoading] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState([]);
  const [copied, setCopied] = useState(false);

  // Désactiver 2FA
  const [showDisableConfirm, setShowDisableConfirm] = useState(false);
  const [disablePassword, setDisablePassword] = useState('');
  const [disableError, setDisableError] = useState('');

  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const { data: p } = await api.get('/admin/profile');
        setProfile(p.data || p);
      } catch { /* ignore */ }
    };
    fetchProfile();
  }, []);

  const handlePasswordChange = async (e) => {
    e.preventDefault();
    setPwError('');
    if (passwordForm.password !== passwordForm.password_confirmation) {
      setPwError('Les mots de passe ne correspondent pas.');
      return;
    }
    if (passwordForm.password.length < 8) {
      setPwError('Le mot de passe doit faire au moins 8 caractères.');
      return;
    }
    try {
      await api.put('/admin/profile/password', passwordForm);
      setSaved(true);
      addToast?.('Mot de passe modifié avec succès', 'success');
      setPasswordForm({ current_password: '', password: '', password_confirmation: '' });
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      const msg = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(', ')
        : err.response?.data?.message || 'Erreur lors du changement de mot de passe';
      setPwError(msg);
    }
  };

  // --- 2FA: Activation ---
  const handleEnable2FA = async () => {
    setTwoFALoading(true);
    setTwoFAError('');
    try {
      const { data } = await api.post('/admin/profile/2fa/enable');
      setQrCodeSvg(data.data.qr_code);
      setTwoFASecret(data.data.secret);
      setTwoFAStep('qr');
      setShow2FAModal(true);
    } catch (err) {
      setTwoFAError(err.response?.data?.message || "Erreur lors de l'activation.");
    } finally {
      setTwoFALoading(false);
    }
  };

  const handleConfirm2FA = async () => {
    if (twoFACode.length !== 6) {
      setTwoFAError('Le code doit contenir 6 chiffres.');
      return;
    }
    setTwoFALoading(true);
    setTwoFAError('');
    try {
      const { data } = await api.post('/admin/profile/2fa/confirm', { code: twoFACode });
      setRecoveryCodes(data.data.recovery_codes);
      setTwoFAStep('done');
      setProfile(prev => ({ ...prev, two_factor_enabled: true }));
      addToast?.('Authentification à deux facteurs activée !', 'success');
    } catch (err) {
      setTwoFAError(err.response?.data?.message || 'Code invalide. Veuillez réessayer.');
    } finally {
      setTwoFALoading(false);
    }
  };

  const handleCloseModal = () => {
    setShow2FAModal(false);
    setTwoFAStep('start');
    setQrCodeSvg('');
    setTwoFASecret('');
    setTwoFACode('');
    setTwoFAError('');
    setRecoveryCodes([]);
  };

  const copyRecoveryCodes = () => {
    navigator.clipboard.writeText(recoveryCodes.join('\n'));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  // --- 2FA: Désactivation ---
  const handleDisable2FA = async () => {
    if (!disablePassword) {
      setDisableError('Veuillez entrer votre mot de passe actuel.');
      return;
    }
    setTwoFALoading(true);
    setDisableError('');
    try {
      await api.post('/admin/profile/2fa/disable', { current_password: disablePassword });
      setProfile(prev => ({ ...prev, two_factor_enabled: false }));
      setShowDisableConfirm(false);
      setDisablePassword('');
      addToast?.('Authentification à deux facteurs désactivée.', 'success');
    } catch (err) {
      setDisableError(err.response?.data?.message || 'Mot de passe incorrect.');
    } finally {
      setTwoFALoading(false);
    }
  };

  const togglePw = (field) => setShowPw(prev => ({ ...prev, [field]: !prev[field] }));

  const PwInput = ({ value, onChange, placeholder, show, onToggle, autoComplete }) => (
    <div className="relative">
      <input
        type={show ? 'text' : 'password'}
        placeholder={placeholder}
        autoComplete={autoComplete}
        className="w-full px-3 py-2.5 pr-10 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
        value={value}
        onChange={onChange}
        required
        minLength={8}
      />
      <button type="button" onClick={onToggle} className="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors">
        {show ? <FiEyeOff size={16} /> : <FiEye size={16} />}
      </button>
    </div>
  );

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold font-headline text-primary">Sécurité</h1>
        <p className="text-sm text-on-surface-variant">Protégez votre compte</p>
      </div>

      {/* Mot de passe */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/5">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2.5 bg-primary/5 rounded-xl">
            <FiLock className="text-primary" size={20} />
          </div>
          <div>
            <h2 className="text-base font-bold font-headline text-primary">Mot de passe</h2>
            <p className="text-xs text-on-surface-variant">Modifiez votre mot de passe</p>
          </div>
        </div>
        {pwError && (
          <div className="mb-4 flex items-center gap-2 p-3 bg-error/10 rounded-xl text-error text-sm">
            <FiAlertTriangle size={16} />
            <span>{pwError}</span>
          </div>
        )}
        <form onSubmit={handlePasswordChange} className="space-y-3 max-w-md">
          <PwInput
            value={passwordForm.current_password}
            onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
            placeholder="Mot de passe actuel"
            show={showPw.current}
            onToggle={() => togglePw('current')}
            autoComplete="current-password"
          />
          <PwInput
            value={passwordForm.password}
            onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })}
            placeholder="Nouveau mot de passe"
            show={showPw.new}
            onToggle={() => togglePw('new')}
            autoComplete="new-password"
          />
          <PwInput
            value={passwordForm.password_confirmation}
            onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })}
            placeholder="Confirmer le mot de passe"
            show={showPw.confirm}
            onToggle={() => togglePw('confirm')}
            autoComplete="new-password"
          />
          <div className="flex items-center gap-4 pt-1">
            <button type="submit" className="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 transition-all">
              Mettre à jour
            </button>
            {saved && (
              <span className="flex items-center gap-1.5 text-sm text-secondary font-semibold">
                <FiCheck size={16} /> Mot de passe modifié
              </span>
            )}
          </div>
        </form>
      </div>

      {/* 2FA */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/5">
        <div className="flex items-center gap-3">
          <div className={`p-2.5 rounded-xl ${profile?.two_factor_enabled ? 'bg-secondary/10' : 'bg-primary/5'}`}>
            <FiShield className={profile?.two_factor_enabled ? 'text-secondary' : 'text-primary'} size={20} />
          </div>
          <div className="flex-1">
            <h2 className="text-base font-bold font-headline text-primary">Authentification à deux facteurs</h2>
            <p className="text-xs text-on-surface-variant mt-0.5">
              {profile?.two_factor_enabled
                ? "Votre compte est sécurisé par une application d'authentification."
                : "Ajoutez une couche de sécurité supplémentaire à votre compte."}
            </p>
          </div>
          {profile?.two_factor_enabled ? (
            <button
              onClick={() => setShowDisableConfirm(true)}
              className="px-4 py-2 bg-error/10 text-error rounded-xl text-sm font-semibold hover:bg-error/20 transition-colors"
            >
              Désactiver
            </button>
          ) : (
            <button
              onClick={handleEnable2FA}
              disabled={twoFALoading}
              className="px-4 py-2 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50"
            >
              {twoFALoading ? 'Chargement...' : 'Activer'}
            </button>
          )}
        </div>
      </div>

      {/* Modal 2FA — Activation */}
      {show2FAModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" onClick={handleCloseModal}>
          <div className="bg-surface-container-lowest rounded-2xl shadow-2xl max-w-lg w-full p-6 relative max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-lg font-bold text-primary font-headline">
                {twoFAStep === 'qr' && 'Scannez le QR code'}
                {twoFAStep === 'done' && 'Codes de récupération'}
              </h3>
              <button onClick={handleCloseModal} className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-on-surface-variant" />
              </button>
            </div>

            {twoFAError && (
              <div className="flex items-center gap-2 p-3 bg-error/10 rounded-xl text-error text-sm mb-4">
                <FiAlertTriangle size={16} />
                <span>{twoFAError}</span>
              </div>
            )}

            {/* Étape QR code */}
            {twoFAStep === 'qr' && (
              <div className="space-y-5">
                <p className="text-sm text-on-surface-variant">
                  Scannez ce QR code avec votre application d'authentification (Google Authenticator, Authy, etc.).
                </p>
                <div className="flex justify-center bg-white p-4 rounded-xl border border-outline-variant/10">
                  {qrCodeSvg ? (
                    <img
                      src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrCodeSvg)}`}
                      alt="QR Code pour l'authentification à deux facteurs"
                      width={250}
                      height={250}
                    />
                  ) : (
                    <div className="w-[250px] h-[250px] bg-surface-container-high rounded-lg flex items-center justify-center text-on-surface-variant text-sm">
                      Chargement...
                    </div>
                  )}
                </div>
                <div className="bg-surface-container-high rounded-xl p-3 flex items-center justify-between gap-2">
                  <code className="text-xs text-on-surface-variant break-all font-mono select-all">{twoFASecret}</code>
                  <button
                    onClick={() => { navigator.clipboard.writeText(twoFASecret); addToast?.('Clé copiée', 'success'); }}
                    className="flex-shrink-0 p-1.5 hover:bg-surface-container-lowest rounded-lg transition-colors"
                    title="Copier la clé"
                  >
                    <FiCopy size={14} className="text-on-surface-variant" />
                  </button>
                </div>
                <p className="text-sm text-on-surface-variant">
                  Entrez le code à 6 chiffres généré par l'application pour confirmer.
                </p>
                <input
                  type="text"
                  inputMode="numeric"
                  maxLength={6}
                  placeholder="000000"
                  className="w-full px-4 py-3 bg-surface-container-high rounded-xl text-center text-2xl font-mono tracking-widest text-primary border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
                  value={twoFACode}
                  onChange={(e) => setTwoFACode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                />
                <button
                  onClick={handleConfirm2FA}
                  disabled={twoFALoading || twoFACode.length !== 6}
                  className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all disabled:opacity-50"
                >
                  {twoFALoading ? 'Vérification...' : <><FiCheck size={18} /> Confirmer</>}
                </button>
              </div>
            )}

            {/* Étape codes de récupération */}
            {twoFAStep === 'done' && (
              <div className="space-y-5">
                <div className="flex items-center gap-2 p-3 bg-secondary/10 rounded-xl text-secondary text-sm">
                  <FiShield size={16} />
                  <span>2FA activée avec succès !</span>
                </div>
                <p className="text-sm text-on-surface-variant">
                  Ces codes de récupération sont affichés une seule fois. Conservez-les dans un endroit sûr.
                </p>
                <div className="bg-surface-container-high rounded-xl p-4">
                  <div className="grid grid-cols-2 gap-2">
                    {recoveryCodes.map((code, i) => (
                      <code key={i} className="font-mono text-sm text-on-surface bg-surface-container-lowest px-3 py-1.5 rounded-lg text-center select-all">{code}</code>
                    ))}
                  </div>
                </div>
                <div className="flex gap-3">
                  <button onClick={copyRecoveryCodes} className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors">
                    <FiCopy size={16} />
                    {copied ? 'Copié !' : 'Copier'}
                  </button>
                  <button onClick={handleCloseModal} className="flex-1 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all">
                    Terminé
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Modal désactiver 2FA */}
      {showDisableConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" onClick={() => setShowDisableConfirm(false)}>
          <div className="bg-surface-container-lowest rounded-2xl shadow-2xl max-w-md w-full p-6 relative" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center gap-3 mb-6">
              <div className="p-2 bg-error/10 rounded-xl">
                <FiAlertTriangle className="text-error" size={20} />
              </div>
              <div>
                <h3 className="text-lg font-bold text-primary font-headline">Désactiver la 2FA</h3>
                <p className="text-xs text-on-surface-variant">Cette action réduit la sécurité de votre compte.</p>
              </div>
            </div>

            {disableError && (
              <div className="flex items-center gap-2 p-3 bg-error/10 rounded-xl text-error text-sm mb-4">
                <FiAlertTriangle size={16} />
                <span>{disableError}</span>
              </div>
            )}

            <p className="text-sm text-on-surface-variant mb-4">
              Veuillez confirmer votre mot de passe pour désactiver l'authentification à deux facteurs.
            </p>
            <input
              type="password"
              placeholder="Votre mot de passe actuel"
              className="w-full px-4 py-2.5 bg-surface-container-high rounded-xl text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all mb-4"
              value={disablePassword}
              onChange={(e) => setDisablePassword(e.target.value)}
            />
            <div className="flex gap-3">
              <button
                onClick={() => { setShowDisableConfirm(false); setDisablePassword(''); setDisableError(''); }}
                className="flex-1 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors"
              >
                Annuler
              </button>
              <button
                onClick={handleDisable2FA}
                disabled={twoFALoading || !disablePassword}
                className="flex-1 px-4 py-2.5 bg-error text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50"
              >
                {twoFALoading ? 'Désactivation...' : 'Confirmer'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
