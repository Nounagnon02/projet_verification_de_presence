import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiUser, FiSave, FiMail, FiRefreshCw, FiAlertTriangle } from 'react-icons/fi';
import { obtenirProfil, modifierProfil } from '../../api/resources/profil';
import SecuriteCompte from '../../components/profile/SecuriteCompte';
import ActiveSessionsPanel from '../../components/settings/ActiveSessionsPanel';
import { libelleRole } from '../../utils/roles';

export default function ProfilePage() {
  const [searchParams] = useSearchParams();
  const [saving, setSaving] = useState(false);
  // Le super admin sans 2FA est redirigé ici (?securite=requise, voir
  // src/api/axios.js) : le groupe /super-admin l'exige désormais, et cette
  // page est là où il l'active — voir SecuriteCompte plus bas.
  const [error, setError] = useState(
    searchParams.get('securite') === 'requise'
      ? "L'authentification à deux facteurs est obligatoire pour votre rôle. Activez-la ci-dessous avant de continuer."
      : '',
  );
  const [success, setSuccess] = useState('');

  // Formulaire profil
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');

  const queryClient = useQueryClient();

  const profilQuery = useQuery({
    queryKey: ['profil'],
    queryFn: async ({ signal }) => {
      const result = await obtenirProfil(signal);
      if (!result.success) throw new Error(result.message || 'Erreur lors du chargement du profil.');
      return result.data;
    },
  });

  const profile = profilQuery.data ?? null;
  const loading = profilQuery.isLoading;

  // Reflète l'échec de chargement dans la bannière d'erreur, sans écraser un
  // message déjà affiché (ex. après un enregistrement) et sans passer par un
  // effet — ajusté PENDANT LE RENDU, motif du projet (voir FilieresPage).
  const [erreurChargementVue, setErreurChargementVue] = useState(profilQuery.error);
  if (profilQuery.error !== erreurChargementVue) {
    setErreurChargementVue(profilQuery.error);
    if (profilQuery.error) setError('Erreur lors du chargement du profil.');
  }

  // Initialise le formulaire une fois le profil arrivé, sans écraser une
  // saisie en cours — même motif : ajusté pendant le rendu plutôt que dans un
  // effet.
  const [profilVu, setProfilVu] = useState(null);
  if (profilQuery.data && profilQuery.data !== profilVu) {
    setProfilVu(profilQuery.data);
    setName(profilQuery.data.name || '');
    setEmail(profilQuery.data.email || '');
  }

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const data = await modifierProfil({ name, email });
      if (data.success) {
        setSuccess('Profil mis à jour avec succès.');
        queryClient.setQueryData(['profil'], (prev) => (prev ? { ...prev, name, email } : prev));
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
        <p className="text-sm text-on-surface-variant">Vos informations personnelles et la sécurité de votre compte</p>
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

      {/* Sécurité du compte : elle vivait dans Paramètres, qui configure
          l'établissement ; elle concerne la personne connectée. */}
      <SecuriteCompte
        deuxFacteursActive={Boolean(profile?.two_factor_enabled)}
        onDeuxFacteursChange={(actif) => queryClient.setQueryData(['profil'], (prev) => (prev ? { ...prev, two_factor_enabled: actif } : prev))}
      />

      {/* Sessions actives : révoquer les autres appareils connectés. */}
      <ActiveSessionsPanel />

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
                Rôle : <span className="font-semibold text-primary">{libelleRole(profile?.role)}</span>
                {' · '}Membre depuis le {new Date(profile.created_at).toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' })}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
