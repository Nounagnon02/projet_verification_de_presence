import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiSave, FiRefreshCw, FiAlertTriangle, FiCheck } from 'react-icons/fi';
import { obtenirProfil, modifierProfil } from '../../api/resources/profil';
import SecuriteCompte from '../../components/profile/SecuriteCompte';
import ActiveSessionsPanel from '../../components/settings/ActiveSessionsPanel';
import { libelleRole } from '../../utils/roles';
import { initialesPersonne, tonalitePersonne } from '../../utils/identitePersonne';

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
      const msg =
        err.response?.data?.message ||
        (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null) ||
        'Erreur lors de la mise à jour.';
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    );
  }

  const { fond, texte } = tonalitePersonne(profile?.email || profile?.name || '');

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      {/* Bandeau d'identité : avatar généré, nom, rôle, ancienneté — un seul
          endroit pour ces faits, au lieu d'un titre de page suivi d'une carte
          « Compte » redondante plus bas. */}
      <div className="flex flex-col gap-5 rounded-xxl border border-outline-variant/15 bg-surface-container-lowest p-7 sm:flex-row sm:items-center">
        <div
          className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl font-headline text-2xl font-bold"
          style={{ backgroundColor: fond, color: texte }}
          aria-hidden="true"
        >
          {initialesPersonne(profile?.name)}
        </div>
        <div className="min-w-0">
          <h1 className="font-headline text-2xl font-bold text-primary">{profile?.name || 'Profil'}</h1>
          <p className="mt-1 text-sm text-on-surface-variant">
            <span className="font-medium text-primary">{libelleRole(profile?.role)}</span>
            {' · '}
            {profile?.email}
            {profile?.created_at && (
              <>
                {' · membre depuis le '}
                {new Date(profile.created_at).toLocaleDateString('fr-FR', {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric',
                })}
              </>
            )}
          </p>
        </div>
      </div>

      {/* Alertes */}
      {error && (
        <div className="flex items-center gap-2 rounded-xl border border-statut-erreur/20 bg-statut-erreur-fond p-3 text-sm text-statut-erreur">
          <FiAlertTriangle size={16} className="shrink-0" />
          <span>{error}</span>
          <button onClick={() => setError('')} className="ml-auto text-error/60 hover:text-error">
            &times;
          </button>
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 rounded-xl border border-success/20 bg-statut-succes-fond p-3 text-sm text-statut-succes">
          <FiCheck size={16} className="shrink-0" />
          <span>{success}</span>
          <button onClick={() => setSuccess('')} className="ml-auto text-success/60 hover:text-success">
            &times;
          </button>
        </div>
      )}

      {/* Informations personnelles */}
      <div className="rounded-xxl border border-outline-variant/15 bg-surface-container-lowest p-7">
        <div className="mb-6">
          <h2 className="font-headline text-base font-bold text-primary">Informations personnelles</h2>
          <p className="mt-1 text-sm text-on-surface-variant">Mettez à jour votre nom et votre adresse email.</p>
        </div>

        <form onSubmit={handleUpdateProfile} className="max-w-md space-y-5">
          <div className="space-y-1.5">
            <label
              htmlFor="name"
              className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
            >
              Nom complet
            </label>
            <input
              id="name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              className="w-full rounded-xl border-b-2 border-transparent bg-surface-container-high px-4 py-3 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
              placeholder="Votre nom"
            />
          </div>

          <div className="space-y-1.5">
            <label
              htmlFor="email"
              className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
            >
              Adresse email
            </label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full rounded-xl border-b-2 border-transparent bg-surface-container-high px-4 py-3 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
              placeholder="email@exemple.com"
            />
          </div>

          <button
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 rounded-xl bg-gradient-to-br from-marque to-marque-fonce px-6 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:scale-[0.99] active:scale-[0.98] disabled:opacity-60"
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
        onDeuxFacteursChange={(actif) =>
          queryClient.setQueryData(['profil'], (prev) => (prev ? { ...prev, two_factor_enabled: actif } : prev))
        }
      />

      {/* Sessions actives : révoquer les autres appareils connectés. */}
      <ActiveSessionsPanel />
    </div>
  );
}
