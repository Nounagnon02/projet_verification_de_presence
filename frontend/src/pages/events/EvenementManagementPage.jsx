import { useState, useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { aujourdhuiIso } from '../../utils/formatters';
import { FiPlus, FiEdit2, FiTrash2, FiSave, FiRefreshCw, FiCalendar, FiClock, FiMapPin, FiAlertTriangle, FiCheckCircle, FiGrid, FiCopy, FiSmartphone } from 'react-icons/fi';
import {
  listerEvenements, creerEvenement, modifierEvenement, supprimerEvenement,
  genererQrCode, creneauxEmploiDuTemps,
} from '../../api/resources/evenements';
import { listerEcs, listerFilieres, listerAnnees, listerSallesDisponibles } from '../../api/resources/reference';
import Modal from '../../components/ui/Modal';
import Pagination from '../../components/ui/Pagination';
import SelecteurHeure from '../../components/ui/SelecteurHeure';
import SelecteurSalle from '../../components/ui/SelecteurSalle';
import { FIN_JOURNEE, enHeure, enMinutes, finApresNouveauDebut } from '../../utils/heures';
import { TYPES_SEANCE, restantesPour } from '../../utils/typesSeance';
import SelecteurGroupe from '../../components/ui/SelecteurGroupe';

/** Même forme partout : { success, data, meta? } ou, plus rarement, le tableau nu. */
const donnees = (reponse) => reponse?.data ?? reponse ?? [];

const INITIAL_EVENT = {
  ec_id: '', filiere_id: '', annee_id: '',
  date: '', heure_debut: '', heure_fin: '', salle: '', salle_id: '', statut: 'planifie', type_cours: 'cm', groupe_id: '',
};

const STATUTS = [
  { value: 'planifie', label: 'Planifié', color: 'bg-primary/10 text-primary' },
  { value: 'en_cours', label: 'En cours', color: 'bg-secondary/10 text-secondary' },
  { value: 'termine', label: 'Terminé', color: 'bg-surface-container-high text-outline' },
  { value: 'annule', label: 'Annulé', color: 'bg-error/10 text-error' },
];

export default function EvenementManagementPage() {
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [qrGenerating, setQrGenerating] = useState(null);

  // /admin/evenements est paginé (sans filtre de date, la liste peut compter
  // des milliers de séances accumulées sur plusieurs semestres).
  const [page, setPage] = useState(1);

  // Filtres
  const [filters, setFilters] = useState({ date_debut: '', date_fin: '', filiere_id: '', statut: '' });
  // Filtres toujours visibles

  // Modal événement
  const [modal, setModal] = useState({ open: false, editing: false, data: INITIAL_EVENT, saving: false });

  // Modal QR Code
  const [qrModal, setQrModal] = useState({ open: false, event: null, qrUrl: '', token: '', expireAt: '', svg: '' });

  // Créneaux de l'emploi du temps proposés pour le cours et la date choisis.
  const [creneaux, setCreneaux] = useState({ loading: false, options: [] });

  const queryClient = useQueryClient();

  // Revient à la page 1 quand un filtre change : la page 3 des résultats
  // précédents n'a aucun sens pour un nouveau filtre. Ajustée PENDANT le rendu
  // (le mécanisme documenté de React pour dériver un état d'un changement de
  // props/état, hors effet) : un effet séparé aurait déclenché une requête
  // intermédiaire avec l'ancienne page ET les nouveaux filtres.
  const [filtresPrecedents, setFiltresPrecedents] = useState(filters);
  if (filtresPrecedents !== filters) {
    setFiltresPrecedents(filters);
    if (page !== 1) setPage(1);
  }

  const parametresListe = useMemo(() => {
    const params = { page };
    if (filters.date_debut) params.date_debut = filters.date_debut;
    if (params.date_debut && !filters.date_fin) params.date_fin = filters.date_debut;
    if (filters.filiere_id) params.filiere_id = filters.filiere_id;
    if (filters.statut) params.statut = filters.statut;
    return params;
  }, [filters, page]);

  // Une clé de requête par combinaison filtres+page : changer de page ne
  // refait plus les 4 requêtes de données de référence, qui ne dépendent
  // d'aucun des deux (elles sont désormais mises en cache après leur premier
  // chargement, plutôt que rechargées à chaque changement de page ou de filtre).
  const evenementsQuery = useQuery({
    queryKey: ['evenements', parametresListe],
    queryFn: ({ signal }) => listerEvenements(parametresListe, signal),
  });
  const ecsQuery = useQuery({ queryKey: ['ecs'], queryFn: () => listerEcs() });
  const filieresQuery = useQuery({ queryKey: ['filieres'], queryFn: () => listerFilieres() });
  const anneesQuery = useQuery({ queryKey: ['annees-academiques'], queryFn: () => listerAnnees() });
  const sallesQuery = useQuery({ queryKey: ['salles-disponibles'], queryFn: () => listerSallesDisponibles() });

  const events = donnees(evenementsQuery.data);
  const pagination = evenementsQuery.data?.meta ?? null;
  const ecs = donnees(ecsQuery.data);
  const filieres = donnees(filieresQuery.data);
  const annees = donnees(anneesQuery.data);
  const salles = donnees(sallesQuery.data);
  const loading = evenementsQuery.isLoading || ecsQuery.isLoading || filieresQuery.isLoading || anneesQuery.isLoading || sallesQuery.isLoading;

  // Après une création, une modification, une suppression ou une génération de
  // QR : seule la liste des événements est à rejouer, les quatre autres
  // requêtes ne dépendent pas de ce qu'on vient de changer.
  const rafraichir = useCallback(
    () => queryClient.invalidateQueries({ queryKey: ['evenements'] }),
    [queryClient],
  );

  const getStatutBadge = (statut) => {
    const s = STATUTS.find(s => s.value === statut);
    return s ? `${s.color} px-2.5 py-0.5 rounded-md text-[10px] font-bold` : 'px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-surface-container-high text-outline';
  };

  const getStatutLabel = (statut) => STATUTS.find(s => s.value === statut)?.label || statut;

  // ─── QR Code ─────────────────────────────────────────────

  const generateQrCode = async (eventId) => {
    setQrGenerating(eventId);
    setError('');
    setSuccess('');
    try {
      const res = await genererQrCode(eventId);
      const d = res.data || res;
      setQrModal({
        open: true,
        event: events.find(e => e.id === eventId),
        // L'URL encodée dans le QR est celle calculée par le serveur, pour que
        // l'image affichée et le lien copié désignent exactement la même cible.
        qrUrl: d.url || `${window.location.origin}/attendance/validate?token=${d.token}`,
        token: d.token,
        expireAt: d.expire_at,
        svg: d.svg || '',
      });
      setSuccess('QR Code généré avec succès !');
      rafraichir();
    } catch {
      setError('Erreur lors de la génération du QR Code.');
    } finally {
      setQrGenerating(null);
    }
  };

  // La liste ne transporte pas l'image : on régénère pour obtenir un token
  // frais et son SVG, ce qui évite aussi d'afficher un code déjà périmé.
  const viewQrCode = (ev) => {
    if (!ev.qr_code?.token) return;
    generateQrCode(ev.id);
  };

  const copyToClipboard = (text) => {
    navigator.clipboard?.writeText(text).then(() => {
      setSuccess('Lien copié !');
    }).catch(() => {});
  };

  // ─── CRUD ──────────────────────────────────────────────

  const openCreate = () => {
    setCreneaux({ loading: false, options: [] });
    setModal({
      open: true, editing: false,
      data: { ...INITIAL_EVENT, filiere_id: filieres[0]?.id || '', annee_id: annees.find(a => a.active)?.id || annees[0]?.id || '' },
      saving: false,
    });
  };

  // ─── Préremplissage depuis l'emploi du temps ───────────
  //
  // Dès qu'un cours et une date sont choisis, l'emploi du temps connaît déjà la
  // salle et les horaires de la séance : les ressaisir est une source d'erreurs
  // et de conflits de salle. Uniquement en création — en modification, les
  // valeurs enregistrées font foi.

  const appliquerCreneau = (creneau) => setModal(prev => ({
    ...prev,
    data: {
      ...prev.data,
      heure_debut: creneau.heure_debut,
      heure_fin: creneau.heure_fin,
      salle_id: creneau.salle_id ?? '',
      salle: creneau.salle_id ? prev.data.salle : (creneau.salle || prev.data.salle),
    },
  }));

  const reinitialiserHoraires = () => {
    setModal(prev => ({
      ...prev,
      data: { ...prev.data, heure_debut: '', heure_fin: '', salle_id: '', salle: '' },
    }));
  };

  // Appelé depuis les champs « cours » et « date », et non depuis un effet : la
  // recherche est la conséquence d'un choix de l'utilisateur, pas une
  // synchronisation d'état.
  const chargerCreneaux = async (ecId, date) => {
    if (!ecId || !date) {
      setCreneaux({ loading: false, options: [] });
      return;
    }

    setCreneaux({ loading: true, options: [] });

    try {
      const data = await creneauxEmploiDuTemps(ecId, date);
      const options = data?.data || [];
      setCreneaux({ loading: false, options });

      // Un seul créneau : on remplit directement. Plusieurs : on laisse
      // choisir, sans rien imposer.
      if (options.length === 1) {
        appliquerCreneau(options[0]);
      }
    } catch {
      // Le préremplissage est un confort : son échec ne doit pas empêcher la
      // saisie manuelle.
      setCreneaux({ loading: false, options: [] });
    }
  };

  const openEdit = (ev) => {
    setCreneaux({ loading: false, options: [] });
    setModal({
      open: true, editing: true,
      // Date d'origine : une date inchangée reste valable même si elle est
      // passée ; seule une date déplacée doit tomber aujourd'hui ou après.
      dateOrigine: ev.date,
      // Durée déjà réservée par cette séance (sauf si elle est annulée) : elle
      // est comptée dans le volume consommé, il faut la rendre disponible pour
      // qu'on puisse la rallonger.
      dureeOrigine: ev.statut === 'annule'
        ? 0
        : Math.max(0, (enMinutes(ev.heure_fin) ?? 0) - (enMinutes(ev.heure_debut) ?? 0)),
      data: {
        id: ev.id,
        ec_id: ev.ec?.id || '', filiere_id: ev.filiere?.id || '', annee_id: ev.annee_id || '',
        // L'API renvoie les heures à la seconde (« 08:00:00 », colonnes time de
        // PostgreSQL) mais n'accepte en écriture que « 08:00 ». Les renvoyer
        // telles quelles faisait échouer TOUTE modification d'événement.
        date: ev.date,
        heure_debut: String(ev.heure_debut ?? '').slice(0, 5),
        heure_fin: String(ev.heure_fin ?? '').slice(0, 5),
        salle: ev.salle || '', salle_id: ev.salle_id || '', statut: ev.statut, type_cours: ev.type_cours || 'cm', groupe_id: ev.groupe_id || '',
      },
      saving: false,
    });
  };

  // Limite de durée pour la fin : plafond d'une séance et volume restant de
  // l'EC, la plus contraignante des deux. En modification, la durée déjà
  // réservée par la séance elle-même est rendue au volume.
  const ecModal = ecs.find((e) => String(e.id) === String(modal.data.ec_id));
  const plafondMinutes = Number(ecModal?.duree_max_seance ?? 0) * 60 || null;
  // Le reste du TYPE de séance choisi : un TD ne consomme pas les heures de CM.
  const restantesType = ecModal ? restantesPour(ecModal, modal.data.type_cours) : null;
  const restantMinutes = restantesType !== null
    ? Math.round(restantesType * 60) + (modal.editing ? (modal.dureeOrigine ?? 0) : 0)
    : null;
  const limiteMinutes = [plafondMinutes, restantMinutes].filter((v) => v !== null).reduce(
    (a, b) => (a === null ? b : Math.min(a, b)), null,
  );
  const debutMinutes = enMinutes(modal.data.heure_debut);
  const finAuPlusTard = limiteMinutes !== null && debutMinutes !== null
    ? enHeure(Math.min(debutMinutes + limiteMinutes, FIN_JOURNEE))
    : null;

  const choisirGroupe = useCallback((groupe_id) => setModal((prev) => ({ ...prev, data: { ...prev.data, groupe_id } })), []);

  const handleSave = async (e) => {
    e.preventDefault();
    setModal(prev => ({ ...prev, saving: true }));
    setError('');
    setSuccess('');
    try {
      if (modal.editing) {
        await modifierEvenement(modal.data.id, modal.data);
        setSuccess('Événement mis à jour.');
      } else {
        await creerEvenement(modal.data);
        setSuccess('Événement créé.');
      }
      setModal({ open: false, editing: false, data: INITIAL_EVENT, saving: false });
      rafraichir();
    } catch (err) {
      // Le détail par champ d'abord : le message racine d'une erreur de
      // validation n'est que « Erreur de validation. », qui ne dit pas quoi
      // corriger. Les clés restées non traduites (« validation.date_format »)
      // sont écartées : le backend n'a pas de traduction française des règles
      // intégrées, seuls les messages personnalisés sont lisibles.
      const lisibles = Object.values(err.response?.data?.errors ?? {})
        .flat()
        .filter((m) => typeof m === 'string' && !/^validation\.[\w.]+$/.test(m));
      const msg = (lisibles.length ? lisibles.join(' ') : null)
        || err.response?.data?.message
        || 'Erreur lors de la sauvegarde.';
      setError(msg);
      setModal(prev => ({ ...prev, saving: false }));
    }
  };

  const handleDelete = async (ev) => {
    if (!window.confirm(`Supprimer l'événement du ${ev.date} (${ev.heure_debut}-${ev.heure_fin}) ?`)) return;
    try {
      await supprimerEvenement(ev.id);
      setSuccess('Événement supprimé.');
      rafraichir();
    } catch { setError('Erreur lors de la suppression.'); }
  };

  const getEcsForFiliere = () => {
    const filiereId = filters.filiere_id || modal.data.filiere_id;
    // Les cours que suit la filière, cours communs compris.
    let filtered = filiereId ? ecs.filter(ec => ec.ue?.filiere_id == filiereId || ec.ue?.filiere?.id == filiereId || (ec.ue?.filieres || []).some((f) => f.id == filiereId)) : ecs;
    // Un EC terminé reste proposé : une évaluation, qui ne consomme pas de
    // volume, se programme après le cours. Le serveur refuse le reste.
    return filtered;
  };

  // Année close pour l'établissement : consultation seulement (le serveur
  // refuse en 409). Chaque ligne suit l'année de sa propre ressource.
  const anneeFermee = (id) => Boolean(annees.find((a) => String(a.id) === String(id))?.close);

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Séances</h1>
          <p className="text-sm text-on-surface-variant">Gestion des séances de cours et codes QR</p>
        </div>
        <button onClick={openCreate}
          className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
          <FiPlus size={16} /> Nouvel événement
        </button>
      </div>

      {/* Alertes */}
      {(error || evenementsQuery.isError) && (
        <div className="flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={16} className="flex-shrink-0" />
          <span className="flex-1">{error || 'Erreur lors du chargement des événements.'}</span>
          <button onClick={() => setError('')} className="text-on-error-container/60">&times;</button>
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 p-3 bg-secondary-container/30 rounded-xl text-on-secondary-container text-sm border border-secondary/10">
          <FiSave size={16} className="flex-shrink-0" />
          <span className="flex-1">{success}</span>
          <button onClick={() => setSuccess('')} className="text-on-secondary-container/60">&times;</button>
        </div>
      )}

      {/* Filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px] flex-1">
              <label htmlFor="filtre-date-debut" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Du</label>
              <input id="filtre-date-debut" type="date" value={filters.date_debut}
                onChange={(e) => setFilters(prev => ({ ...prev, date_debut: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
            </div>
            <div className="space-y-1 min-w-[160px] flex-1">
              <label htmlFor="filtre-date-fin" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Au</label>
              <input id="filtre-date-fin" type="date" value={filters.date_fin}
                onChange={(e) => setFilters(prev => ({ ...prev, date_fin: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
            </div>
            <div className="space-y-1 min-w-[180px] flex-1">
              <label htmlFor="filtre-filiere" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
              <select id="filtre-filiere" value={filters.filiere_id}
                onChange={(e) => setFilters(prev => ({ ...prev, filiere_id: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">Toutes</option>
                {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
              </select>
            </div>
            <div className="space-y-1 min-w-[180px] flex-1">
              <label htmlFor="filtre-statut" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Statut</label>
              <select id="filtre-statut" value={filters.statut}
                onChange={(e) => setFilters(prev => ({ ...prev, statut: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">Tous</option>
                {STATUTS.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
            <div className="min-w-[120px]">
              <button onClick={rafraichir}
                className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:opacity-90 transition-all">
                <FiRefreshCw size={14} /> Appliquer
              </button>
            </div>
          </div>
          {(filters.date_debut || filters.date_fin || filters.filiere_id || filters.statut) && (
            <div className="mt-3 text-right">
              <button onClick={() => setFilters({ date_debut: '', date_fin: '', filiere_id: '', statut: '' })}
                className="text-xs text-primary hover:underline">Réinitialiser les filtres</button>
            </div>
          )}
        </div>

      {/* Loading */}
      {loading ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
          <FiRefreshCw className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="text-on-surface-variant">Chargement des événements...</p>
        </div>
      ) : events.length === 0 ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center border border-dashed border-outline-variant/30">
          <div className="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mx-auto mb-6">
            <FiCalendar className="text-outline" size={28} />
          </div>
          <h3 className="text-lg font-semibold text-on-surface mb-2">
            {Object.values(filters).some(v => v) ? 'Aucun événement ne correspond aux filtres' : 'Aucun événement'}
          </h3>
          <p className="text-sm text-on-surface-variant">
            {Object.values(filters).some(v => v) ? 'Essayez d\'autres filtres.' : 'Créez votre premier événement.'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {events.map((ev) => (
            <div key={ev.id} className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 flex items-start gap-4">
              {/* Date block */}
              <div className="text-center flex-shrink-0 w-14">
                <div className="text-2xl font-bold text-primary leading-none">
                  {new Date(ev.date + 'T00:00:00').getDate()}
                </div>
                <div className="text-[10px] text-on-surface-variant uppercase mt-0.5">
                  {new Date(ev.date + 'T00:00:00').toLocaleDateString('fr-FR', { month: 'short' })}
                </div>
              </div>

              {/* Content */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-bold text-sm text-on-surface truncate">{ev.ec?.intitule || '—'}</span>
                  <span className={getStatutBadge(ev.statut)}>{getStatutLabel(ev.statut)}</span>
                  {ev.has_qr_code && (
                    <span className="bg-secondary/10 text-secondary px-2 py-0.5 rounded-md text-[10px] font-bold flex items-center gap-1">
                      <FiGrid size={10} /> QR
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-4 mt-1 text-[11px] text-on-surface-variant flex-wrap">
                  <span className="flex items-center gap-1"><FiClock size={12} />{ev.heure_debut} - {ev.heure_fin}</span>
                  {ev.salle_ref ? (
                    <span className="flex items-center gap-1 text-secondary"><FiMapPin size={12} />{ev.salle_ref.nom}{' '}
                      {/* Le niveau réel de la salle, pas une promesse : la plupart des
                          salles n'ont encore ni GPS ni Wi-Fi configuré. */}
                      <span className="text-[9px] text-secondary/70">
                        ({[ev.salle_ref.verifie_gps && 'GPS', ev.salle_ref.verifie_wifi && 'Wi-Fi'].filter(Boolean).join(' + ') || 'QR seul'})
                      </span>
                    </span>
                  ) : ev.salle ? (
                    <span className="flex items-center gap-1"><FiMapPin size={12} />{ev.salle}</span>
                  ) : null}
                  <span className="flex items-center gap-1">{ev.ue?.code || ev.ec?.code}</span>
                  <span>{ev.filiere?.code}</span>
                </div>
                <p className="text-[10px] text-outline mt-1">
                  {ev.presences_count ?? 0} présence{(ev.presences_count ?? 0) > 1 ? 's' : ''}
                </p>

                {/* QR Code Actions */}
                <div className="flex items-center gap-2 mt-2 flex-wrap">
                  {ev.has_qr_code && ev.qr_code && !ev.qr_code.is_expired ? (
                    <button onClick={() => viewQrCode(ev)}
                      className="flex items-center gap-1 px-2.5 py-1 bg-secondary/10 text-secondary rounded-lg text-[10px] font-bold hover:bg-secondary/20 transition-all">
                      <FiGrid size={11} /> Voir QR Code
                    </button>
                  ) : (
                    <>
                      <button disabled
                        className="flex items-center gap-1 px-2.5 py-1 bg-surface-container-high text-outline/50 rounded-lg text-[10px] font-bold cursor-not-allowed">
                        <FiGrid size={11} /> Voir QR Code
                      </button>
                      <button onClick={() => generateQrCode(ev.id)} disabled={qrGenerating === ev.id}
                        className="flex items-center gap-1 px-2.5 py-1 bg-primary/10 text-primary rounded-lg text-[10px] font-bold hover:bg-primary/20 transition-all disabled:opacity-50">
                        {qrGenerating === ev.id ? <FiRefreshCw className="animate-spin" size={11} /> : <FiRefreshCw size={11} />}
                        Générer QR Code
                      </button>
                    </>
                  )}
                </div>
              </div>

              {/* Actions */}
              <div className="flex items-center gap-1 flex-shrink-0">
                <button onClick={() => openEdit(ev)} disabled={anneeFermee(ev.annee_id)}
                  className="p-2 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Modifier">
                  <FiEdit2 size={14} />
                </button>
                <button onClick={() => handleDelete(ev)} disabled={anneeFermee(ev.annee_id)}
                  className="p-2 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all" title="Supprimer">
                  <FiTrash2 size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Pagination pagination={pagination} onPageChange={setPage} />

      {/* ─── Modal QR Code ─────────────────────────────── */}
      <Modal isOpen={qrModal.open} onClose={() => setQrModal(prev => ({ ...prev, open: false }))} title="Code QR" size="sm">
        {/* Event info */}
        {qrModal.event && (
          <div className="bg-surface-container-high rounded-xl p-3 mb-4 text-center">
            <p className="font-bold text-sm text-primary">{qrModal.event.ec?.intitule || 'Cours'}</p>
            <p className="text-[11px] text-on-surface-variant mt-0.5">
              {qrModal.event.date} · {qrModal.event.heure_debut} - {qrModal.event.heure_fin}
            </p>
            {qrModal.event.salle && (
              <p className="text-[11px] text-on-surface-variant">{qrModal.event.salle}</p>
            )}
          </div>
        )}

        {/* QR Code — image SVG fournie par l'API. Elle était auparavant
            demandée à un service tiers, ce qui faisait sortir le token du
            système pour un simple encodage graphique. */}
        <div className="flex justify-center mb-4">
          {qrModal.svg ? (
            <div
              className="w-56 h-56 rounded-xl bg-white p-2 shadow-sm [&>svg]:w-full [&>svg]:h-full"
              role="img"
              aria-label="QR Code de présence"
              dangerouslySetInnerHTML={{ __html: qrModal.svg }}
            />
          ) : (
            <div className="w-56 h-56 rounded-xl bg-surface-container-high flex items-center justify-center text-xs text-on-surface-variant text-center px-4">
              Image indisponible. Régénérez le QR Code.
            </div>
          )}
        </div>

        {/* Lien de validation */}
        <div className="bg-surface-container-high rounded-xl p-3 mb-4">
          <p className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant mb-1">Lien de validation</p>
          <div className="flex items-center gap-2">
            <code className="flex-1 text-[10px] text-primary font-mono truncate bg-surface-container-lowest rounded-lg px-2 py-1.5">
              {qrModal.qrUrl}
            </code>
            <button onClick={() => copyToClipboard(qrModal.qrUrl)}
              className="p-1.5 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Copier">
              <FiCopy size={14} />
            </button>
          </div>
        </div>

        {/* Instructions */}
        <div className="flex items-start gap-2 text-[11px] text-on-surface-variant p-3 bg-surface-container-high rounded-xl">
          <FiSmartphone size={14} className="shrink-0 mt-0.5 text-secondary" />
          <p>Les étudiants scannent ce QR code avec leur téléphone pour valider leur présence. Le code expire dans 60 secondes.</p>
        </div>

        <div className="mt-4">
          <button onClick={() => setQrModal(prev => ({ ...prev, open: false }))}
            className="w-full py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all">
            Fermer
          </button>
        </div>
      </Modal>

      {/* ─── Modal événement ────────────────────────────── */}
      <Modal isOpen={modal.open} onClose={() => setModal(prev => ({ ...prev, open: false }))}
        title={modal.editing ? "Modifier l'événement" : 'Nouvel événement'} size="md">
        <form onSubmit={handleSave} className="space-y-4">
          {/* La filière ne sert qu'à réduire la liste des cours : elle
              n'est pas envoyée. Le serveur déduit filière ET année de
              l'EC choisi, ce qui rend toute incohérence impossible. */}
          <div>
            <label htmlFor="filiere-evenement" className="block text-xs font-semibold text-on-surface mb-1">Filière <span className="font-normal text-on-surface-variant">(pour filtrer les cours)</span></label>
            <select id="filiere-evenement" value={modal.data.filiere_id} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, filiere_id: e.target.value, ec_id: '' } }))}
              className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="">Toutes les filières</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
            </select>
          </div>
          <div>
            <label htmlFor="ec-evenement" className="block text-xs font-semibold text-on-surface mb-1">EC (Cours) *</label>
            <select id="ec-evenement" value={modal.data.ec_id} onChange={(e) => {
                const ecId = e.target.value;
                setModal(prev => ({ ...prev, data: { ...prev.data, ec_id: ecId } }));
                if (!modal.editing) chargerCreneaux(ecId, modal.data.date);
              }}
              required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="">Sélectionner...</option>
              {getEcsForFiliere().map(ec => {
                const filiereCode = ec.ue?.filiere?.code || ec.ue?.filiere_code;
                return (
                  <option key={ec.id} value={ec.id}>
                    {ec.code} — {ec.intitule}{filiereCode ? ` (${filiereCode})` : ''}{ec.statut === 'termine' ? ' — terminé, évaluation seulement' : ''}
                  </option>
                );
              })}
            </select>
          </div>
          <div>
            <label htmlFor="type-evenement" className="block text-xs font-semibold text-on-surface mb-1">Type de séance</label>
            <select id="type-evenement" value={modal.data.type_cours} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, type_cours: e.target.value } }))}
              className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              {TYPES_SEANCE.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
            </select>
          </div>
          <SelecteurGroupe id="groupe-evenement" ecId={modal.data.ec_id} type={modal.data.type_cours} value={modal.data.groupe_id}
            onChange={choisirGroupe}
            labelClassName="block text-xs font-semibold text-on-surface mb-1"
            className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
          <div>
            <label htmlFor="date-evenement" className="block text-xs font-semibold text-on-surface mb-1">Date *</label>
            <input id="date-evenement" type="date" value={modal.data.date} onChange={(e) => {
              const date = e.target.value;
              setModal(prev => ({ ...prev, data: { ...prev.data, date } }));
              if (!modal.editing) chargerCreneaux(modal.data.ec_id, date);
            }}
              // Aujourd'hui au plus tôt : le sélecteur grise les jours passés.
              // En modification, la date d'origine laissée telle quelle reste
              // acceptée, pour pouvoir clore ou annuler un cours d'hier.
              min={modal.editing && modal.data.date === modal.dateOrigine ? undefined : aujourdhuiIso()}
              required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            {modal.editing && modal.dateOrigine && modal.dateOrigine < aujourdhuiIso() && (
              <p className="text-xs pt-1 text-on-surface-variant">
                Cet événement est passé : sa date peut être conservée, ou reportée à aujourd'hui ou plus tard.
              </p>
            )}
          </div>
          {/* Suggestions issues de l'emploi du temps — création seulement */}
          {!modal.editing && modal.data.ec_id && modal.data.date && (
            <div className="rounded-xl border border-outline-variant/30 bg-surface-container-high/60 px-3 py-2.5 text-xs">
              {creneaux.loading ? (
                <span className="text-on-surface-variant">Recherche du créneau à l'emploi du temps…</span>
              ) : creneaux.options.length === 0 ? (
                <span className="text-on-surface-variant">
                  Aucun créneau à l'emploi du temps ce jour-là pour ce cours. Saisissez les horaires ci-dessous.
                </span>
              ) : creneaux.options.length === 1 ? (
                <div className="flex items-center justify-between gap-2">
                  <span className="text-on-surface">
                    <FiCheckCircle size={12} className="inline mb-0.5 mr-1 text-secondary" />
                    Prérempli depuis l'emploi du temps
                    {creneaux.options[0].type_cours ? ` (${creneaux.options[0].type_cours})` : ''}
                  </span>
                  <button type="button" onClick={reinitialiserHoraires}
                    className="shrink-0 font-semibold text-primary hover:underline">
                    Saisir à la main
                  </button>
                </div>
              ) : (
                <div className="space-y-2">
                  <p className="text-on-surface">
                    Ce cours a {creneaux.options.length} créneaux ce jour-là. Lequel programmez-vous&nbsp;?
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {creneaux.options.map(c => {
                      const actif = modal.data.heure_debut === c.heure_debut && modal.data.heure_fin === c.heure_fin;
                      return (
                        <button key={c.id} type="button" onClick={() => appliquerCreneau(c)}
                          className={`px-2.5 py-1 rounded-lg border text-xs font-semibold transition-colors ${
                            actif
                              ? 'border-primary bg-primary/10 text-primary'
                              : 'border-outline-variant/40 text-on-surface hover:bg-surface-container-highest'
                          }`}>
                          {c.heure_debut}–{c.heure_fin}
                          {c.type_cours ? ` · ${c.type_cours}` : ''}
                          {c.salle ? ` · ${c.salle}` : ''}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          )}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="heure-debut-evenement" className="block text-xs font-semibold text-on-surface mb-1">Début *</label>
              <SelecteurHeure id="heure-debut-evenement" required value={modal.data.heure_debut}
                // La fin suit le début en gardant la durée choisie, dans la limite permise.
                onChange={(v) => setModal(prev => ({
                  ...prev,
                  data: {
                    ...prev.data,
                    heure_debut: v,
                    heure_fin: finApresNouveauDebut({
                      ancienDebut: prev.data.heure_debut,
                      ancienneFin: prev.data.heure_fin,
                      nouveauDebut: v,
                      limiteMinutes,
                    }),
                  },
                }))}
                className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            </div>
            <div>
              <label htmlFor="heure-fin-evenement" className="block text-xs font-semibold text-on-surface mb-1">Fin *</label>
              <SelecteurHeure id="heure-fin-evenement" required value={modal.data.heure_fin}
                apres={modal.data.heure_debut || null}
                jusqua={finAuPlusTard}
                onChange={(v) => setModal(prev => ({ ...prev, data: { ...prev.data, heure_fin: v } }))}
                className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
              {finAuPlusTard && limiteMinutes !== null && (
                <p className="text-xs pt-1 text-on-surface-variant">
                  Au plus tard {finAuPlusTard}
                  {plafondMinutes !== null && limiteMinutes === plafondMinutes
                    ? ` — une séance dure ${plafondMinutes / 60} h au maximum.`
                    : ' — limite du volume horaire restant.'}
                </p>
              )}
            </div>
          </div>
          <div>
            <label htmlFor="salle-evenement" className="block text-xs font-semibold text-on-surface mb-1">Salle</label>
            {/* Salles configurées uniquement : un nom saisi à la main ne permet
                ni le contrôle GPS/Wi-Fi ni la détection de double réservation. */}
            <SelecteurSalle id="salle-evenement" salles={salles} value={modal.data.salle_id}
              nomActuel={modal.data.salle_id ? '' : modal.data.salle}
              onChange={(id) => setModal(prev => ({ ...prev, data: { ...prev.data, salle_id: id } }))}
              className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          {/* Le statut ne se choisit qu'en modification : un événement naît
              planifié, puis la séance le fait passer « En cours » et
              « Terminé ». Seule une annulation reste une décision. */}
          {modal.editing && (
            <div>
              <label htmlFor="statut-evenement" className="block text-xs font-semibold text-on-surface mb-1">Statut</label>
              <select id="statut-evenement" value={modal.data.statut} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, statut: e.target.value } }))}
                className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                {STATUTS.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
          )}
          <div className="flex gap-3 pt-2">
            <button type="submit" disabled={modal.saving}
              className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all disabled:opacity-50">
              {modal.saving ? <FiRefreshCw className="animate-spin" size={16} /> : <FiSave size={16} />}
              {modal.editing ? 'Mettre à jour' : "Créer l'événement"}
            </button>
            <button type="button" onClick={() => setModal(prev => ({ ...prev, open: false }))}
              className="px-6 py-2.5 bg-surface-container-high text-on-surface-variant rounded-xl font-semibold text-sm hover:bg-surface-container-high/80 transition-all">
              Annuler
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
