import { useState, useEffect } from 'react';
import { FiShield, FiLock, FiSmartphone } from 'react-icons/fi';
import Toggle from '../../components/ui/Toggle';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';

export default function SecurityPage() {
  const [profile, setProfile] = useState(null);
  const [sessions, setSessions] = useState([]);
  const { addToast } = useToastCtx();

  const [passwordForm, setPasswordForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [saved, setSaved] = useState(false);
  const [pwError, setPwError] = useState('');

  useEffect(() => {
    const fetchData = async () => {
      try {
        const { data: p } = await api.get('/admin/profile');
        setProfile(p.data || p);
      } catch { /* ignore */ }
      try {
        const { data: s } = await api.get('/admin/sessions');
        setSessions(Array.isArray(s.data) ? s.data : Array.isArray(s) ? s : []);
      } catch { /* ignore */ }
    };
    fetchData();
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

  const handleLogoutOthers = async () => {
    try {
      await api.delete('/admin/sessions/others');
      addToast?.('Autres sessions déconnectées', 'success');
    } catch {
      addToast?.('Erreur lors de la déconnexion', 'error');
    }
  };

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold font-headline text-primary mb-2">Sécurité</h1>
        <p className="text-sm text-on-surface-variant">Gérez les paramètres de sécurité de votre compte</p>
      </div>

      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2 bg-primary/5 rounded-xl">
            <FiShield className="text-primary" size={20} />
          </div>
          <div>
            <h2 className="text-base font-bold font-headline text-primary">Profil</h2>
            <p className="text-xs text-on-surface-variant">Informations du compte</p>
          </div>
        </div>
        <div className="space-y-3">
          <p className="text-sm"><span className="text-on-surface-variant">Nom :</span> <strong className="text-primary">{profile?.name || '—'}</strong></p>
          <p className="text-sm"><span className="text-on-surface-variant">Email :</span> <strong className="text-primary">{profile?.email || '—'}</strong></p>
          <p className="text-sm"><span className="text-on-surface-variant">2FA :</span> <strong className="text-primary">{profile?.two_factor_enabled ? 'Activé' : 'Désactivé'}</strong></p>
          <p className="text-sm"><span className="text-on-surface-variant">Membre depuis :</span> <strong className="text-primary">{profile?.created_at || '—'}</strong></p>
        </div>
      </div>

      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2 bg-primary/5 rounded-xl">
            <FiLock className="text-primary" size={20} />
          </div>
          <div>
            <h2 className="text-base font-bold font-headline text-primary">Mot de passe</h2>
            <p className="text-xs text-on-surface-variant">Modifiez votre mot de passe</p>
          </div>
        </div>
        {pwError && (
          <div className="mb-4 p-3 bg-error/10 rounded-xl text-error text-sm">{pwError}</div>
        )}
        <form onSubmit={handlePasswordChange} className="space-y-4 max-w-md">
          <input type="password" placeholder="Mot de passe actuel" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
            value={passwordForm.current_password} onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })} required />
          <input type="password" placeholder="Nouveau mot de passe" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
            value={passwordForm.password} onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })} required minLength={8} />
          <input type="password" placeholder="Confirmer le mot de passe" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
            value={passwordForm.password_confirmation} onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })} required />
          <div className="flex items-center gap-4">
            <button type="submit" className="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 transition-all">
              Mettre à jour
            </button>
            {saved && <span className="text-sm text-secondary font-semibold">✓ Mot de passe modifié</span>}
          </div>
        </form>
      </div>

      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2 bg-primary/5 rounded-xl">
            <FiSmartphone className="text-primary" size={20} />
          </div>
          <div>
            <h2 className="text-base font-bold font-headline text-primary">Session</h2>
            <p className="text-xs text-on-surface-variant">Gestion des sessions actives</p>
          </div>
        </div>
        <div className="space-y-3">
          <p className="text-sm text-on-surface-variant">
            Sessions actives : <strong className="text-primary">{sessions.length}</strong>
          </p>
          <button onClick={handleLogoutOthers} className="px-6 py-2.5 bg-error/10 text-error rounded-xl text-sm font-semibold hover:bg-error/20 transition-colors">
            Déconnecter toutes les autres sessions
          </button>
        </div>
      </div>
    </div>
  );
}
