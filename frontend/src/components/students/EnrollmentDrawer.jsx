import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  FiAlertTriangle,
  FiBookOpen,
  FiLoader,
  FiPlus,
  FiRefreshCw,
  FiTrash2,
} from 'react-icons/fi';
import {
  listerEcsInscrits, listerEcsDisponibles, inscrireEc, desinscrireEc, reinitialiserInscriptions,
} from '../../api/resources/inscriptions';
import Badge from '../ui/Badge';
import Button from '../ui/Button';
import EmptyState from '../ui/EmptyState';
import LoadingSkeleton from '../ui/LoadingSkeleton';
import Modal from '../ui/Modal';
import SearchInput from '../ui/SearchInput';
import { useToastCtx } from '../../context/ToastContext';

// Libellés des statuts d'EC tels que les enregistre le serveur.
const STATUTS = {
  non_demarre: { libelle: 'Non démarré', variant: 'neutral' },
  en_cours: { libelle: 'En cours', variant: 'info' },
  termine: { libelle: 'Terminé', variant: 'success' },
};

/**
 * Message d'erreur le plus précis dont on dispose : les erreurs de validation
 * du serveur d'abord, puis son message, et seulement en dernier recours le
 * repli générique.
 */
const messageErreur = (err, repli) => {
  const data = err.response?.data;
  const validation = data?.errors ? Object.values(data.errors).flat().join(', ') : null;
  return validation || data?.message || err.message || repli;
};

/**
 * Une annulation au démontage n'est pas une panne : elle ne doit ni afficher
 * d'erreur ni écrire dans l'état.
 */
const estAnnulation = (err) => err.name === 'CanceledError' || err.name === 'AbortError';

/** Texte sur lequel porte la recherche locale d'un EC. */
const texteRecherche = (ec) =>
  [ec.code, ec.intitule, ec.ue?.code, ec.ue?.intitule]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();

/**
 * Une ligne d'EC, avec son action unique (inscrire ou désinscrire).
 *
 * Composant hissé au niveau du module : le recréer à chaque rendu du tiroir
 * remonterait toute la liste et ferait perdre le focus du bouton cliqué.
 */
function LigneEc({ ec, action }) {
  const statut = STATUTS[ec.statut];

  return (
    <li className="flex items-start gap-3 px-4 py-3 border-b border-outline-variant/5 last:border-b-0">
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-2 text-sm font-semibold text-on-surface">
          <span className="font-mono text-xs text-primary">{ec.code}</span>
          <span className="truncate">{ec.intitule}</span>
        </p>
        <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
          {/* /ecs-available ne charge pas la relation ue : on n'affiche
              l'unité d'enseignement que lorsque le serveur la fournit. */}
          {ec.ue && <span className="truncate">UE {ec.ue.code} — {ec.ue.intitule}</span>}
          {ec.volume_horaire > 0 && <span>{ec.volume_horaire} h</span>}
          {statut && <Badge variant={statut.variant}>{statut.libelle}</Badge>}
        </p>
      </div>
      {action}
    </li>
  );
}

/**
 * Colonne de liste (« Inscrit à » / « Disponibles »), avec son état vide.
 */
function ColonneEcs({ titre, ecs, vide, children }) {
  return (
    <section className="rounded-xl border border-outline-variant/10 bg-surface-container-lowest">
      <header className="flex items-center justify-between border-b border-outline-variant/10 px-4 py-3">
        <h3 className="text-xs font-bold uppercase tracking-wider text-on-surface-variant">
          {titre}
        </h3>
        <Badge>{ecs.length}</Badge>
      </header>
      {ecs.length === 0 ? (
        <EmptyState icon={FiBookOpen} message={vide} />
      ) : (
        <ul className="max-h-72 overflow-y-auto">{children}</ul>
      )}
    </section>
  );
}

/**
 * Gestion des inscriptions d'un étudiant à ses ECs (CDC 7.2.3).
 *
 * Composant contrôlé : le parent décide de l'ouverture et fournit l'étudiant.
 * Aucun appel réseau n'est émis sans étudiant, et les requêtes en vol sont
 * annulées au démontage comme à chaque rechargement.
 */
export default function EnrollmentDrawer({ student, open, onClose, onChange }) {
  const { addToast } = useToastCtx();
  const queryClient = useQueryClient();

  const [recherche, setRecherche] = useState('');
  // Identifiant de l'EC en cours de traitement, ou 'reset' : une seule écriture
  // à la fois, ce qui évite deux appels concurrents sur les mêmes inscriptions.
  const [actionEnCours, setActionEnCours] = useState(null);
  const [confirmationReset, setConfirmationReset] = useState(false);

  const studentId = student?.id ?? null;
  const ouvert = Boolean(open && studentId);

  const abortEcritureRef = useRef(null);
  const monteRef = useRef(true);

  useEffect(() => {
    monteRef.current = true;
    return () => {
      monteRef.current = false;
      abortEcritureRef.current?.abort();
    };
  }, []);

  // Les deux listes sont chargées ensemble : elles décrivent le même état et
  // les afficher désynchronisées laisserait un EC dans les deux colonnes.
  const inscritsQuery = useQuery({
    queryKey: ['ecs-inscrits', studentId],
    queryFn: ({ signal }) => listerEcsInscrits(studentId, signal),
    enabled: ouvert,
  });
  const disponiblesQuery = useQuery({
    queryKey: ['ecs-disponibles', studentId],
    queryFn: ({ signal }) => listerEcsDisponibles(studentId, signal),
    enabled: ouvert,
  });

  const inscrits = useMemo(
    () => (Array.isArray(inscritsQuery.data?.data) ? inscritsQuery.data.data : []),
    [inscritsQuery.data],
  );
  const disponibles = useMemo(
    () => (Array.isArray(disponiblesQuery.data?.data) ? disponiblesQuery.data.data : []),
    [disponiblesQuery.data],
  );
  const chargement = inscritsQuery.isFetching || disponiblesQuery.isFetching;
  const erreur = inscritsQuery.isError || disponiblesQuery.isError
    ? messageErreur(inscritsQuery.error ?? disponiblesQuery.error, 'Erreur lors du chargement des inscriptions.')
    : null;

  const rafraichir = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['ecs-inscrits', studentId] });
    queryClient.invalidateQueries({ queryKey: ['ecs-disponibles', studentId] });
  }, [queryClient, studentId]);

  /**
   * Exécute une écriture puis recharge les deux listes : le serveur reste la
   * seule source de vérité, aucune liste n'est corrigée à la main côté client.
   */
  const ecrire = useCallback(async (cle, envoyer, repliErreur) => {
    if (!studentId) return;

    const controleur = new AbortController();
    abortEcritureRef.current = controleur;
    setActionEnCours(cle);
    try {
      const data = await envoyer(controleur.signal);
      if (!monteRef.current) return;
      onChange?.(data?.data ?? null);
      rafraichir();
      return data;
    } catch (err) {
      if (estAnnulation(err) || !monteRef.current) return;
      addToast?.(messageErreur(err, repliErreur), 'error');
    } finally {
      if (monteRef.current && !controleur.signal.aborted) setActionEnCours(null);
    }
  }, [studentId, onChange, rafraichir, addToast]);

  const inscrire = useCallback(async (ec) => {
    const data = await ecrire(
      ec.id,
      (signal) => inscrireEc(studentId, [ec.id], signal),
      `Erreur lors de l'inscription à ${ec.code}.`,
    );
    if (!data) return;
    // Le serveur ignore silencieusement un EC hors filière/année de l'étudiant :
    // la réponse est un succès avec attached à 0. Sans ce contrôle, l'interface
    // annoncerait une inscription qui n'a pas eu lieu.
    if ((data.data?.attached ?? 0) === 0) {
      addToast?.(
        `${ec.code} n'appartient pas à la filière et à l'année de l'étudiant : inscription refusée.`,
        'error',
      );
      return;
    }
    addToast?.(`Inscription à ${ec.code} enregistrée.`, 'success');
  }, [ecrire, studentId, addToast]);

  const desinscrire = useCallback(async (ec) => {
    const data = await ecrire(
      ec.id,
      (signal) => desinscrireEc(studentId, ec.id, signal),
      `Erreur lors de la désinscription de ${ec.code}.`,
    );
    if (!data) return;
    addToast?.(`Étudiant désinscrit de ${ec.code}.`, 'success');
  }, [ecrire, studentId, addToast]);

  const reinitialiser = useCallback(async () => {
    const data = await ecrire(
      'reset',
      (signal) => reinitialiserInscriptions(studentId, signal),
      'Erreur lors de la réinitialisation des inscriptions.',
    );
    if (!data) return;
    setConfirmationReset(false);
    addToast?.(data.message || 'Inscriptions réinitialisées.', 'success');
  }, [ecrire, studentId, addToast]);

  // Fermeture : on repart d'une interface neutre à la prochaine ouverture.
  const fermer = useCallback(() => {
    setRecherche('');
    setConfirmationReset(false);
    onClose?.();
  }, [onClose]);

  const terme = recherche.trim().toLowerCase();

  const inscritsFiltres = useMemo(
    () => (terme ? inscrits.filter((ec) => texteRecherche(ec).includes(terme)) : inscrits),
    [inscrits, terme],
  );
  const disponiblesFiltres = useMemo(
    () => (terme ? disponibles.filter((ec) => texteRecherche(ec).includes(terme)) : disponibles),
    [disponibles, terme],
  );

  if (!student) return null;

  const enEcriture = actionEnCours !== null;
  // Le squelette n'est montré qu'au tout premier chargement. Après une
  // écriture, les listes restent affichées pendant le rechargement : les
  // remplacer par un squelette ferait clignoter tout le tiroir et effacerait
  // le filtre de recherche à chaque clic.
  const premierChargement = chargement && inscrits.length === 0 && disponibles.length === 0;
  const actionsBloquees = enEcriture || chargement;
  const nomComplet = `${student.prenom || ''} ${student.nom || ''}`.trim();

  return (
    <Modal
      isOpen={ouvert}
      onClose={fermer}
      title="Inscriptions aux enseignements"
      size="xl"
    >
      <div className="space-y-5">
        <div className="rounded-xl bg-surface-container-low p-4">
          <p className="text-sm font-semibold text-on-surface">
            {nomComplet || 'Étudiant'}
            {student.matricule && (
              <span className="ml-2 font-mono text-xs font-medium text-on-surface-variant">
                {student.matricule}
              </span>
            )}
          </p>
          <p className="mt-1 text-xs text-on-surface-variant">
            {student.filiere?.code || student.filiere || 'Filière non renseignée'}
            {' · '}
            {student.annee?.annee || student.annee || 'Année non renseignée'}
          </p>
        </div>

        {erreur && (
          <div className="flex items-start gap-3 rounded-xl border border-error/10 bg-error-container/30 p-4 text-sm text-on-error-container">
            <FiAlertTriangle className="mt-0.5 flex-shrink-0" aria-hidden="true" />
            <div className="flex-1">
              <p className="font-semibold">Erreur de chargement</p>
              <p className="mt-1 text-xs opacity-80">{erreur}</p>
            </div>
            <Button variant="outline" size="sm" onClick={rafraichir}>
              Réessayer
            </Button>
          </div>
        )}

        {premierChargement ? (
          <LoadingSkeleton rows={6} cols={2} />
        ) : (
          <>
            <SearchInput
              value={recherche}
              onChange={setRecherche}
              placeholder="Filtrer par code ou intitulé d'EC..."
              className="max-w-md"
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <ColonneEcs
                titre="Inscrit à"
                ecs={inscritsFiltres}
                vide={
                  terme
                    ? 'Aucun EC inscrit ne correspond à ce filtre.'
                    : "Cet étudiant n'est inscrit à aucun EC."
                }
              >
                {inscritsFiltres.map((ec) => (
                  <LigneEc
                    key={ec.id}
                    ec={ec}
                    action={
                      <button
                        type="button"
                        onClick={() => desinscrire(ec)}
                        disabled={actionsBloquees}
                        aria-label={`Désinscrire l'étudiant de ${ec.code}`}
                        className="flex-shrink-0 rounded-lg p-2 transition-colors hover:bg-error/10 disabled:opacity-40 disabled:pointer-events-none"
                      >
                        {actionEnCours === ec.id ? (
                          <FiLoader className="animate-spin text-error" aria-hidden="true" />
                        ) : (
                          <FiTrash2 className="text-error" aria-hidden="true" />
                        )}
                      </button>
                    }
                  />
                ))}
              </ColonneEcs>

              <ColonneEcs
                titre="Disponibles"
                ecs={disponiblesFiltres}
                vide={
                  terme
                    ? 'Aucun EC disponible ne correspond à ce filtre.'
                    : 'Aucun EC disponible : toutes les inscriptions possibles sont faites.'
                }
              >
                {disponiblesFiltres.map((ec) => (
                  <LigneEc
                    key={ec.id}
                    ec={ec}
                    action={
                      <button
                        type="button"
                        onClick={() => inscrire(ec)}
                        disabled={actionsBloquees}
                        aria-label={`Inscrire l'étudiant à ${ec.code}`}
                        className="flex-shrink-0 rounded-lg p-2 transition-colors hover:bg-primary/10 disabled:opacity-40 disabled:pointer-events-none"
                      >
                        {actionEnCours === ec.id ? (
                          <FiLoader className="animate-spin text-primary" aria-hidden="true" />
                        ) : (
                          <FiPlus className="text-primary" aria-hidden="true" />
                        )}
                      </button>
                    }
                  />
                ))}
              </ColonneEcs>
            </div>
          </>
        )}

        <div className="border-t border-outline-variant/10 pt-4">
          {confirmationReset ? (
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-error/10 bg-error/10 p-4 text-error">
              <FiAlertTriangle aria-hidden="true" />
              <p className="flex-1 text-sm">
                Toutes les inscriptions actuelles seront supprimées, puis l'étudiant sera
                réinscrit à l'ensemble des ECs de sa filière et de son année.
              </p>
              <Button
                variant="destructive"
                size="sm"
                onClick={reinitialiser}
                loading={actionEnCours === 'reset'}
              >
                Confirmer
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setConfirmationReset(false)}
                disabled={enEcriture}
              >
                Annuler
              </Button>
            </div>
          ) : (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setConfirmationReset(true)}
              disabled={actionsBloquees}
            >
              <FiRefreshCw aria-hidden="true" />
              Réinitialiser les inscriptions
            </Button>
          )}
        </div>
      </div>
    </Modal>
  );
}
