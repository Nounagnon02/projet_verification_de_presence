import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  MdArrowBack,
  MdBusiness,
  MdEmail,
  MdPhone,
  MdLocationOn,
  MdEdit,
  MdRefresh,
  MdContentCopy,
  MdCheck,
  MdWarning,
  MdVpnKey,
  MdDeleteOutline,
} from 'react-icons/md';
import {
  recupererEtablissement,
  recupererStatsEtablissement,
  modifierEtablissement,
  supprimerEtablissement,
  renvoyerIdentifiantsEtablissement,
} from '../../api/resources/etablissements';
import ProgressBar from '../../components/charts/ProgressBar';
import { monogrammeInstitution, tonaliteInstitution } from '../../utils/identiteInstitution';
import { couleurTaux } from '../../utils/taux';

/** « 2026-10-13 » → « 13 oct. 2026 », sans décalage de fuseau. */
const dateCourte = (iso) => {
  if (!iso) return null;
  const [a, m, j] = String(iso).slice(0, 10).split('-').map(Number);
  return new Date(a, m - 1, j).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
};

const CHAMPS = [
  { cle: 'code', libelle: 'Code', Icone: MdBusiness },
  { cle: 'nom', libelle: 'Nom', Icone: MdBusiness },
  { cle: 'email', libelle: 'Email', Icone: MdEmail, type: 'email' },
  { cle: 'telephone', libelle: 'Téléphone', Icone: MdPhone, type: 'tel' },
  { cle: 'adresse', libelle: 'Adresse', Icone: MdLocationOn, multiligne: true },
];

/**
 * Fiche d'une faculté (super administrateur) : identité et année de travail en
 * tête, chiffres clés, filières en colonne principale ; coordonnées, compte
 * administrateur et suppression dans un panneau latéral, chacun avec ses
 * actions nommées. Remplace une colonne étroite à icônes muettes, dont les
 * chiffres changeaient de couleur sans signification.
 */
export default function EtablissementDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });

  // Une clé de requête par établissement : changer de faculté ne peut plus
  // afficher les données de la précédente, TanStack Query annulant/ignorant
  // la requête devenue obsolète.
  const etablissementQuery = useQuery({
    queryKey: ['etablissement', id],
    queryFn: ({ signal }) => recupererEtablissement(id, signal),
  });
  const statsQuery = useQuery({
    queryKey: ['etablissement-stats', id],
    queryFn: ({ signal }) => recupererStatsEtablissement(id, signal),
  });
  const etablissement = etablissementQuery.data?.data ?? null;
  const stats = statsQuery.data?.data ?? null;
  const loading = etablissementQuery.isLoading || statsQuery.isLoading;

  const rafraichir = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['etablissement', id] });
    queryClient.invalidateQueries({ queryKey: ['etablissement-stats', id] });
  }, [queryClient, id]);

  // Préremplissage du formulaire d'édition depuis les données chargées, ajusté
  // PENDANT le rendu (mécanisme documenté de React pour dériver un état d'un
  // changement de props/état, hors effet — même motif que dans
  // EvenementManagementPage) : un effet aurait déclenché un rendu
  // supplémentaire à chaque chargement.
  const [etablissementCharge, setEtablissementCharge] = useState(null);
  if (etablissement && etablissementCharge !== etablissement) {
    setEtablissementCharge(etablissement);
    setForm({
      code: etablissement.code || '',
      nom: etablissement.nom || '',
      email: etablissement.email || '',
      telephone: etablissement.telephone || '',
      adresse: etablissement.adresse || '',
    });
  }

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage({ type: '', text: '' });
    try {
      const data = await modifierEtablissement(id, form);
      if (data.success) {
        queryClient.invalidateQueries({ queryKey: ['etablissement', id] });
        setEditing(false);
        setMessage({ type: 'success', text: 'Faculté mise à jour avec succès.' });
      }
    } catch (err) {
      const data = err.response?.data;
      const msg =
        (data?.errors ? Object.values(data.errors).flat().join(', ') : null) ||
        data?.message ||
        'Erreur lors de la mise à jour.';
      setMessage({ type: 'error', text: msg });
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette faculté ? Cette action est irréversible.')) return;
    try {
      await supprimerEtablissement(id);
      // La liste des facultés, si elle est déjà en cache, ne doit plus montrer
      // celle qu'on vient de supprimer.
      queryClient.invalidateQueries({ queryKey: ['etablissements'] });
      navigate('/super-admin/etablissements');
    } catch {
      setMessage({ type: 'error', text: 'Erreur lors de la suppression.' });
    }
  };

  // Le renvoi remplace le mot de passe et révoque les sessions ouvertes
  // (EtablissementController::resendCredentials) : il partait sans confirmation.
  const handleResendCredentials = async () => {
    if (
      !window.confirm(
        "Renvoyer les identifiants ? Un nouveau mot de passe sera envoyé par email et l'administrateur sera déconnecté de ses sessions en cours.",
      )
    )
      return;
    try {
      const data = await renvoyerIdentifiantsEtablissement(id);
      if (data.success) {
        setMessage({ type: 'success', text: 'Identifiants renvoyés par email.' });
      }
    } catch {
      setMessage({ type: 'error', text: "Erreur lors de l'envoi des identifiants." });
    }
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
      setMessage({ type: 'success', text: 'Email copié.' });
      setTimeout(() => setMessage({ type: '', text: '' }), 2000);
    });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!etablissement) {
    return (
      <div className="text-center py-16">
        <p className="text-on-surface-variant">Faculté introuvable.</p>
        <button
          type="button"
          onClick={() => navigate('/super-admin/etablissements')}
          className="text-primary text-sm mt-2 hover:underline"
        >
          Retour à la liste
        </button>
      </div>
    );
  }

  const { fond, texte } = tonaliteInstitution(etablissement.code);
  const filieres = Array.isArray(stats?.filieres) ? stats.filieres : [];
  const taux = stats?.taux_presence ?? null;
  const annee = stats?.annee_active ?? null;
  const nbAdmins = etablissement.users_count ?? 0;
  const coursDuJour = stats?.cours_aujourdhui ?? 0;

  const chiffres = [
    {
      libelle: 'Filières',
      valeur: (stats?.total_filieres ?? etablissement.filieres_count ?? 0).toLocaleString('fr-FR'),
    },
    { libelle: 'Étudiants', valeur: (stats?.total_etudiants ?? 0).toLocaleString('fr-FR') },
    { libelle: 'Présences enregistrées', valeur: (stats?.total_presences ?? 0).toLocaleString('fr-FR') },
  ];

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <Link
        to="/super-admin/etablissements"
        className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary transition-colors"
      >
        <MdArrowBack size={16} aria-hidden="true" />
        Facultés & Écoles
      </Link>

      {/* En-tête : identité, statut, année de travail, actions nommées. */}
      <header className="carte flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex min-w-0 items-start gap-4">
          <div
            className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl font-headline text-xl font-bold"
            style={{ backgroundColor: fond, color: texte }}
            aria-hidden="true"
          >
            {monogrammeInstitution(etablissement.code)}
          </div>
          <div className="min-w-0">
            <h1 className="titre-page">{etablissement.nom}</h1>
            <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
              <span className="rounded-md bg-primary/10 px-2 py-0.5 font-mono font-semibold text-primary">
                {etablissement.code}
              </span>
              {etablissement.actif ? (
                <span className="rounded-full bg-statut-succes-fond px-2 py-0.5 font-semibold text-statut-succes">
                  Active
                </span>
              ) : (
                <span className="rounded-full bg-surface-container-high px-2 py-0.5 font-semibold text-on-surface-variant">
                  Inactive
                </span>
              )}
              {annee && (
                <span className="rounded-full bg-surface-container-high px-2 py-0.5 font-semibold text-on-surface">
                  Année {annee.libelle}
                  <span className="font-normal text-on-surface-variant">
                    {annee.suit_universite ? ' · celle de l’université' : ' · choisie par la faculté'}
                  </span>
                </span>
              )}
            </div>
            <p className="mt-2 text-sm text-on-surface-variant">
              {nbAdmins} compte{nbAdmins > 1 ? 's' : ''} administrateur
              {' · '}
              {coursDuJour === 0
                ? 'aucune séance aujourd’hui'
                : `${coursDuJour} séance${coursDuJour > 1 ? 's' : ''} aujourd’hui`}
              {etablissement.created_at && ` · créée le ${dateCourte(etablissement.created_at)}`}
            </p>
          </div>
        </div>
        <div className="flex shrink-0 flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={rafraichir}
            className="btn-icone"
            aria-label="Rafraîchir les données"
            title="Rafraîchir"
          >
            <MdRefresh size={18} aria-hidden="true" />
          </button>
        </div>
      </header>

      {message.text && (
        <div
          role="status"
          className={`flex items-start gap-3 p-4 rounded-xl border text-sm ${
            message.type === 'success'
              ? 'bg-statut-succes-fond text-statut-succes border-statut-succes/20'
              : 'bg-statut-erreur-fond text-statut-erreur border-statut-erreur/20'
          }`}
        >
          {message.type === 'success' ? (
            <MdCheck className="text-lg shrink-0 mt-0.5" aria-hidden="true" />
          ) : (
            <MdWarning className="text-lg shrink-0 mt-0.5" aria-hidden="true" />
          )}
          <p>{message.text}</p>
        </div>
      )}

      {/* Chiffres clés : une seule couleur ; seul le taux est coloré, selon
          les mêmes seuils que partout ailleurs (utils/taux). */}
      <section aria-label="Chiffres clés" className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        {chiffres.map((c) => (
          <div key={c.libelle} className="carte p-5">
            <p className="font-headline text-3xl font-bold text-primary">{c.valeur}</p>
            <p className="mt-1 text-xs font-medium uppercase tracking-wider text-on-surface-variant">{c.libelle}</p>
          </div>
        ))}
        <div className="carte p-5">
          <p
            className="font-headline text-3xl font-bold"
            style={{ color: taux === null ? undefined : couleurTaux(taux) }}
          >
            {taux === null ? '—' : `${taux} %`}
          </p>
          <p className="mt-1 text-xs font-medium uppercase tracking-wider text-on-surface-variant">Taux de présence</p>
          {taux === null ? (
            <p className="mt-2 text-xs text-on-surface-variant">Aucune séance passée avec des inscrits.</p>
          ) : (
            <ProgressBar
              value={taux}
              showValue={false}
              size="sm"
              color={taux >= 80 ? 'success' : taux >= 50 ? 'warning' : 'error'}
              className="mt-3"
            />
          )}
        </div>
      </section>

      <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-3 [&>*]:min-w-0">
        {/* Filières */}
        <section aria-labelledby="titre-filieres" className="carte overflow-hidden lg:col-span-2">
          <div className="flex items-baseline justify-between gap-3 border-b border-outline-variant/15 px-5 py-4 sm:px-6">
            <h2 id="titre-filieres" className="font-headline text-base font-bold text-primary">
              Filières
            </h2>
            <span className="text-xs text-on-surface-variant">
              {filieres.length} filière{filieres.length > 1 ? 's' : ''}
            </span>
          </div>
          {filieres.length === 0 ? (
            <p className="px-6 py-10 text-center text-sm text-on-surface-variant">
              Aucune filière : la faculté les crée depuis ses paramètres.
            </p>
          ) : (
            <div className="overflow-x-auto" tabIndex={0} role="region" aria-labelledby="titre-filieres">
              <table className="tableau">
                <thead>
                  <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Intitulé</th>
                    <th scope="col" className="hidden sm:table-cell">
                      Niveau
                    </th>
                    <th scope="col" className="!text-right">
                      Étudiants
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {filieres.map((f) => (
                    <tr key={f.id}>
                      <td className="whitespace-nowrap font-mono text-xs font-semibold text-primary">{f.code}</td>
                      <td className="min-w-[9rem] sm:min-w-[12rem]">
                        {f.intitule}
                        {/* Sous 640 px, le niveau passe sous l'intitulé : sa colonne
                            repoussait « Étudiants » hors de l'écran. */}
                        {f.niveau && !String(f.intitule).includes(f.niveau) && (
                          <span className="block text-xs text-on-surface-variant sm:hidden">{f.niveau}</span>
                        )}
                      </td>
                      <td className="hidden whitespace-nowrap text-on-surface-variant sm:table-cell">
                        {f.niveau || '—'}
                      </td>
                      <td className="text-right tabular-nums">{f.etudiants}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {/* Panneau latéral */}
        <aside className="space-y-6" aria-label="Gestion de la faculté">
          <section aria-labelledby="titre-coordonnees" className="carte p-5 sm:p-6">
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 id="titre-coordonnees" className="font-headline text-base font-bold text-primary">
                {editing ? 'Modifier la faculté' : 'Coordonnées'}
              </h2>
              {!editing && (
                <button
                  type="button"
                  onClick={() => setEditing(true)}
                  className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                >
                  <MdEdit size={14} aria-hidden="true" />
                  Modifier
                </button>
              )}
            </div>

            {editing ? (
              <form onSubmit={handleSave} className="space-y-4">
                {CHAMPS.map(({ cle, libelle, Icone, type, multiligne }) => (
                  <div key={cle}>
                    <label htmlFor={`etablissement-${cle}`} className="libelle">
                      {libelle}
                    </label>
                    <div className="relative">
                      <Icone
                        className={`pointer-events-none absolute left-3.5 text-on-surface-variant ${multiligne ? 'top-3' : 'top-1/2 -translate-y-1/2'}`}
                        size={16}
                        aria-hidden="true"
                      />
                      {multiligne ? (
                        <textarea
                          id={`etablissement-${cle}`}
                          value={form[cle]}
                          onChange={(e) => setForm({ ...form, [cle]: e.target.value })}
                          rows={2}
                          className="champ resize-none pl-10"
                        />
                      ) : (
                        <input
                          id={`etablissement-${cle}`}
                          type={type || 'text'}
                          value={form[cle]}
                          onChange={(e) => setForm({ ...form, [cle]: e.target.value })}
                          className="champ pl-10"
                        />
                      )}
                    </div>
                  </div>
                ))}
                <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                  <button type="button" onClick={() => setEditing(false)} disabled={saving} className="btn-fantome">
                    Annuler
                  </button>
                  <button type="submit" disabled={saving} className="btn-primaire">
                    {saving && (
                      <span
                        className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                        aria-hidden="true"
                      />
                    )}
                    Enregistrer
                  </button>
                </div>
              </form>
            ) : (
              // Un <dl> n'admet que des groupes <div> de <dt>/<dd>.
              <dl className="space-y-4">
                <div className="min-w-0">
                  <dt className="flex items-center gap-2 text-xs text-on-surface-variant">
                    <MdEmail size={16} aria-hidden="true" />
                    Email
                  </dt>
                  <dd className="mt-0.5 flex min-w-0 items-center gap-2 pl-6">
                    <a
                      href={`mailto:${etablissement.email}`}
                      className="truncate text-sm text-primary underline-offset-2 hover:underline"
                    >
                      {etablissement.email}
                    </a>
                    <button
                      type="button"
                      onClick={() => copyToClipboard(etablissement.email)}
                      className="shrink-0 rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container-high hover:text-primary"
                      aria-label="Copier l'email"
                      title="Copier l'email"
                    >
                      <MdContentCopy size={14} aria-hidden="true" />
                    </button>
                  </dd>
                </div>
                <div>
                  <dt className="flex items-center gap-2 text-xs text-on-surface-variant">
                    <MdPhone size={16} aria-hidden="true" />
                    Téléphone
                  </dt>
                  <dd className="mt-0.5 pl-6 text-sm text-on-surface">{etablissement.telephone || 'Non renseigné'}</dd>
                </div>
                <div>
                  <dt className="flex items-center gap-2 text-xs text-on-surface-variant">
                    <MdLocationOn size={16} aria-hidden="true" />
                    Adresse
                  </dt>
                  <dd className="mt-0.5 break-words pl-6 text-sm text-on-surface">
                    {etablissement.adresse || 'Non renseignée'}
                  </dd>
                </div>
              </dl>
            )}
          </section>

          <section aria-labelledby="titre-compte" className="carte p-5 sm:p-6">
            <h2 id="titre-compte" className="font-headline text-base font-bold text-primary">
              Compte administrateur
            </h2>
            <p className="mt-2 text-sm text-on-surface-variant">
              Il se connecte avec l&apos;email de la faculté. Le renvoi génère un nouveau mot de passe, l&apos;envoie
              par email et ferme ses sessions ouvertes.
            </p>
            <button type="button" onClick={handleResendCredentials} className="btn-secondaire mt-4 w-full">
              <MdVpnKey size={16} aria-hidden="true" />
              Renvoyer les identifiants
            </button>
          </section>

          <section aria-labelledby="titre-sensible" className="carte border-statut-erreur/25 p-5 sm:p-6">
            <h2 id="titre-sensible" className="font-headline text-base font-bold text-statut-erreur">
              Zone sensible
            </h2>
            <p className="mt-2 text-sm text-on-surface-variant">
              La suppression retire la faculté de la liste. Elle est définitive.
            </p>
            <button
              type="button"
              onClick={handleDelete}
              className="btn mt-4 w-full border border-statut-erreur/40 text-statut-erreur hover:bg-statut-erreur-fond"
            >
              <MdDeleteOutline size={18} aria-hidden="true" />
              Supprimer la faculté
            </button>
          </section>
        </aside>
      </div>
    </div>
  );
}
