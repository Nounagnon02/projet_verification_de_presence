import { useState, useEffect } from 'react';
import { FiUser, FiSave, FiMail, FiShield, FiRefreshCw, FiAlertTriangle } from 'react-icons/fi';
import api from '../../api/axios';

export default function ProfilePage() {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Formulaire profil
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');

  useEffect(() => {
    fetchProfile();
  }, []);

  const fetchProfile = async () => {
    try {
      setLoading(true);
      const { data } = await api.get('/admin/profile');
      if (data.success && data.data) {
        setProfile(data.data);
        setName(data.data.name || '');
        setEmail(data.data.email || '');
      }
    } catch (err) {
      setError('Erreur lors du chargement du profil.');
      console.error('[Profile]', err);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const { data } = await api.put('/admin/profile', { name, email });
      if (data.success) {
        setSuccess('Profil mis à jour avec succès.');
        setProfile(prev => ({ ...prev, name, email }));
      } else {
        setError(data.message || 'Erreur lors de la mise à jour.');
      }
    } catch (err) {
      const msg = err.response?.data?.message
        || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null)
        || 'Erreur lors de la mise à jour.';
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-2xl mx-auto py-12">
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
          <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <FiRefreshCw className="text-primary animate-spin" size={28} />
          </div>
          <p className="text-on-surface-variant">Chargement du profil...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-8">
      {/* En-tête */}
      <div>
        <h1 className="text-2xl font-bold text-primary font-headline">Profil</h1>
        <p className="text-sm text-on-surface-variant">Gérez vos informations personnelles</p>
      </div>

      {/* Alertes */}
      {error && (
        <div className="flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={16} className="flex-shrink-0" />
          <span>{error}</span>
          <button onClick={() => setError('')} className="ml-auto text-on-error-container/60 hover:text-on-error-container">&times;</button>
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 p-3 bg-secondary-container/30 rounded-xl text-on-secondary-container text-sm border border-secondary/10">
          <FiSave size={16} className="flex-shrink-0" />
          <span>{success}</span>
          <button onClick={() => setSuccess('')} className="ml-auto text-on-secondary-container/60 hover:text-on-secondary-container">&times;</button>
        </div>
      )}

      {/* Carte informations personnelles */}
      <div className="bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10">
        <div className="flex items-center gap-3 mb-6">
          <div className="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
            <FiUser className="text-primary" size={22} />
          </div>
          <div>
            <h2 className="text-lg font-bold text-on-surface">Informations personnelles</h2>
            <p className="text-xs text-on-surface-variant">Mettez à jour votre nom et votre adresse email</p>
          </div>
        </div>

        <form onSubmit={handleUpdateProfile} className="space-y-5">
          <div>
            <label htmlFor="name" className="block text-sm font-semibold text-on-surface mb-1.5">Nom complet</label>
            <input
              id="name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              className="w-full px-4 py-2.5 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
              placeholder="Votre nom"
            />
          </div>

          <div>
            <label htmlFor="email" className="block text-sm font-semibold text-on-surface mb-1.5">Adresse email</label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full px-4 py-2.5 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
              placeholder="email@exemple.com"
            />
          </div>

          {profile?.member && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-on-surface-variant mb-1">Matricule membre</label>
                <div className="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high rounded-xl text-sm text-on-surface">
                  <FiShield size={14} className="text-outline" />
                  {profile.member.matricule || 'Non renseigné'}
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface-variant mb-1">Téléphone</label>
                <div className="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high rounded-xl text-sm text-on-surface">
                  {profile.member.telephone || 'Non renseigné'}
                </div>
              </div>
            </div>
          )}

          <button
            type="submit"
            disabled={saving}
            className="flex items-center justify-center gap-2 px-6 py-2.5 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saving ? <FiRefreshCw className="animate-spin" size={16} /> : <FiSave size={16} />}
            {saving ? 'Enregistrement...' : 'Enregistrer'}
          </button>
        </form>
      </div>

      {/* Métadonnées compte */}
      {profile?.created_at && (
        <div className="bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
              <FiMail className="text-primary" size={22} />
            </div>
            <div>
              <h2 className="text-lg font-bold text-on-surface">Compte</h2>
              <p className="text-xs text-on-surface-variant mt-0.5">
                Rôle : <span className="font-semibold text-primary">{profile?.role || 'Administrateur'}</span>
                {' · '}Membre depuis le {new Date(profile.created_at).toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' })}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
