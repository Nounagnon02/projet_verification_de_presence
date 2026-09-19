import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  FiAlertTriangle, FiCheckCircle, FiClock, FiMapPin,
  FiRefreshCw, FiSmartphone, FiXCircle,
} from 'react-icons/fi';
import { listerFilePresences, validerPresence } from '../../api/resources/presences';
import { listerFilieres } from '../../api/resources/reference';
import useDebounce from '../../hooks/useDebounce';
import { useToastCtx } from '../../context/ToastContext';
import Button from '../../components/ui/Button';
import DataTable from '../../components/ui/DataTable';
import Modal from '../../components/ui/Modal';
import SearchInput from '../../components/ui/SearchInput';

// La file ne contient que des scans « suspect » : c'est le seul statut à
// arbitrer que le scan produise. Les onglets « En attente » et « Invalides »
// filtraient des statuts qui n'existent nulle part.

const PAR_PAGE = 20;
const MOTIF_MAX = 500;

/** Nom affichable d'un étudiant, sans laisser passer « undefined ». */
const nomComplet = (etudiant) => {
  if (!etudiant) return 'Étudiant inconnu';
  return `${etudiant.prenom || ''} ${etudiant.nom || ''}`.trim() || 'Étudiant inconnu';
};

/** Intitulé de l'EC rattaché à l'événement de la présence. */
const intituleCours = (presence) => {
  const ec = presence?.evenement?.ec;
  return ec?.intitule || ec?.code || 'Cours inconnu';
};

/** Date et heure lisibles à partir d'un horodatage ISO ; null si inexploitable. */
const formaterDateHeure = (valeur) => {
  if (!valeur) return null;
  const date = new Date(valeur);
  if (Number.isNaN(date.getTime())) return null;
  return date.toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

/** Heure « 14:46 » d'un horodatage ISO ; chaîne vide si inexploitable. */
const heureCourte = (valeur) => {
  const date = valeur ? new Date(valeur) : null;
  return date && !Number.isNaN(date.getTime())
    ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    : '';
};

const ETATS_VOISIN = { valide: 'validé', rejete: 'rejeté' };

/** « Valérie AHOUANDJINOU (14:46, rejeté) » pour un autre scan du même téléphone. */
const decrireVoisin = (voisin) => {
  const precisions = [heureCourte(voisin.heure_scan), ETATS_VOISIN[voisin.statut]].filter(Boolean);
  return `${nomComplet(voisin.etudiant)}${precisions.length ? ` (${precisions.join(', ')})` : ''}`;
};

/**
 * Raison de la suspicion et indices complémentaires.
 *
 * Un scan n'est suspect que pour une raison : son téléphone a servi à d'autres
 * étudiants pendant la même séance. L'API les renvoie dans « meme_appareil » :
 * c'est ce qu'il faut voir pour trancher, et le seul « scan marqué suspect »
 * affiché jusqu'ici ne le permettait pas.
 */
const indicesAnomalie = (presence) => {
  const indices = [];
  const voisins = Array.isArray(presence.meme_appareil) ? presence.meme_appareil : [];

  if (voisins.length > 0) {
    indices.push({
      cle: 'appareil-partage',
      libelle: 'Même téléphone que :',
      details: voisins.map(decrireVoisin),
      Icone: FiSmartphone,
    });
  } else if (presence.statut === 'suspect') {
    indices.push({ cle: 'suspect', libelle: 'Suspect, sans autre scan de ce téléphone', Icone: FiAlertTriangle });
  }
  if (presence.latitude === null || presence.latitude === undefined
    || presence.longitude === null || presence.longitude === undefined) {
    indices.push({ cle: 'gps', libelle: 'Position GPS absente', Icone: FiMapPin });
  }
  if (!presence.heure_scan) {
    indices.push({ cle: 'scan', libelle: 'Aucun scan enregistré', Icone: FiClock });
  }

  return indices;
};

const classeChamp = 'w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20';
const classeLibelle = 'text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider';

export default function PresenceQueuePage() {
  const { addToast } = useToastCtx();
  const idMotif = useId();

  // Filtres envoyés au serveur (les seuls que le contrôleur accepte).
  const [page, setPage] = useState(1);
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');

  // Recherche envoyée au serveur après une courte pause de frappe : elle porte
  // sur toute la file, et non plus sur la seule page affichée.
  const [recherche, setRecherche] = useState('');
  const rechercheServeur = useDebounce(recherche.trim(), 300);

  const [cible, setCible] = useState(null);
  const [motif, setMotif] = useState('');
  const [envoi, setEnvoi] = useState(false);

  const monte = useRef(true);
  useEffect(() => {
    monte.current = true;
    return () => { monte.current = false; };
  }, []);

  const queryClient = useQueryClient();

  const rafraichir = useCallback(
    () => queryClient.invalidateQueries({ queryKey: ['presences-file'] }),
    [queryClient],
  );

  // Liste des filières pour le filtre. Son échec n'empêche pas de travailler.
  const filieresQuery = useQuery({ queryKey: ['filieres'], queryFn: () => listerFilieres() });
  const filieres = Array.isArray(filieresQuery.data?.data) ? filieresQuery.data.data : [];

  const parametresListe = useMemo(() => {
    const params = { page, per_page: PAR_PAGE };
    if (filtreFiliere) params.filiere_id = filtreFiliere;
    if (dateDebut) params.date_from = dateDebut;
    if (dateFin) params.date_to = dateFin;
    if (rechercheServeur) params.search = rechercheServeur;
    return params;
  }, [page, filtreFiliere, dateDebut, dateFin, rechercheServeur]);

  // Chargement de la file : une clé de requête par combinaison filtres+page.
  // pendingValidations renvoie le paginateur Laravel brut dans « data » :
  // { current_page, data: [...], from, to, last_page, per_page, total }.
  const fileQuery = useQuery({
    queryKey: ['presences-file', parametresListe],
    queryFn: ({ signal }) => listerFilePresences(parametresListe, signal),
  });

  const chargement = fileQuery.isFetching;
  const erreur = fileQuery.isError
    ? (fileQuery.error?.response?.data?.message
      || 'Impossible de charger les présences à valider. Vérifiez votre connexion puis réessayez.')
    : '';

  // Copie locale de la page affichée : les actions de validation/rejet la
  // modifient de façon optimiste (retrait immédiat, restauration si l'appel
  // échoue) avant que la prochaine réponse du serveur ne la remplace.
  const [presences, setPresences] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [derniereReponse, setDerniereReponse] = useState(undefined);
  if (fileQuery.data !== undefined && fileQuery.data !== derniereReponse) {
    setDerniereReponse(fileQuery.data);
    const paginateur = fileQuery.data?.data ?? {};
    setPresences(Array.isArray(paginateur.data) ? paginateur.data : []);
    setPagination(paginateur.current_page ? paginateur : null);
  }

  const filtresServeurActifs = Boolean(filtreFiliere || dateDebut || dateFin);
  const rechercheActive = Boolean(rechercheServeur);

  const reinitialiserFiltres = () => {
    setFiltreFiliere('');
    setDateDebut('');
    setDateFin('');
    setPage(1);
  };

  const effacerRecherche = () => {
    setRecherche('');
    setPage(1);
  };

  const fermerModale = () => {
    if (envoi) return;
    setCible(null);
    setMotif('');
  };

  // Les compteurs du paginateur sont ajustés localement pour rester cohérents
  // avec le retrait optimiste, en attendant le prochain chargement.
  const ajusterCompteurs = useCallback((delta) => {
    setPagination((precedent) => {
      if (!precedent) return precedent;
      return {
        ...precedent,
        total: Math.max(0, (precedent.total ?? 0) + delta),
        to: Math.max(0, (precedent.to ?? 0) + delta),
      };
    });
  }, []);

  const confirmerAction = async () => {
    if (!cible) return;

    const { type, presence } = cible;
    const motifNettoye = motif.trim();
    if (type === 'rejeter' && !motifNettoye) return;

    const position = presences.findIndex((ligne) => ligne.id === presence.id);
    const derniereLigne = presences.length <= 1;

    setEnvoi(true);

    // Retrait optimiste : la ligne quitte la file immédiatement et n'y revient
    // qu'en cas d'échec réel de l'appel.
    setPresences((precedent) => precedent.filter((ligne) => ligne.id !== presence.id));
    ajusterCompteurs(-1);

    let fermer = true;

    try {
      const corps = { action: type };
      if (motifNettoye) corps.motif = motifNettoye;

      const data = await validerPresence(presence.id, corps);

      if (!monte.current) return;

      addToast?.(
        data?.message || (type === 'valider' ? 'Présence validée.' : 'Présence rejetée.'),
        'success',
      );

      // La page vient de se vider : on recharge pour reprendre la suite de la file.
      if (derniereLigne) rafraichir();
    } catch (err) {
      if (!monte.current) return;

      const statut = err.response?.status;

      if (statut === 404 || statut === 409) {
        // Déjà arbitrée entre-temps (ou supprimée) : sa place n'est plus dans la
        // file, on la laisse retirée.
        addToast?.(
          err.response?.data?.message || 'Cette présence a déjà été traitée.',
          'warning',
        );
      } else {
        setPresences((precedent) => {
          if (precedent.some((ligne) => ligne.id === presence.id)) return precedent;
          const index = position >= 0 ? Math.min(position, precedent.length) : precedent.length;
          return [...precedent.slice(0, index), presence, ...precedent.slice(index)];
        });
        ajusterCompteurs(1);
        addToast?.(
          err.response?.data?.message
          || "L'opération a échoué. La ligne a été remise dans la file, réessayez.",
          'error',
        );
        fermer = false;
      }
    } finally {
      if (monte.current) {
        setEnvoi(false);
        if (fermer) {
          setCible(null);
          setMotif('');
        }
      }
    }
  };

  const colonnes = [
    {
      key: 'etudiant',
      label: 'Étudiant',
      render: (_, ligne) => (
        <div className="min-w-[150px]">
          <p className="font-semibold text-on-surface">{nomComplet(ligne.etudiant)}</p>
          <p className="text-[11px] font-mono text-on-surface-variant">
            {ligne.etudiant?.matricule || 'Matricule inconnu'}
            {ligne.etudiant?.filiere?.code ? ` · ${ligne.etudiant.filiere.code}` : ''}
          </p>
        </div>
      ),
    },
    {
      key: 'cours',
      label: 'Cours (EC)',
      render: (_, ligne) => (
        <div className="min-w-[150px]">
          <p className="text-on-surface">{intituleCours(ligne)}</p>
          <p className="text-[11px] text-on-surface-variant">
            {ligne.evenement?.ec?.code ? `${ligne.evenement.ec.code} · ` : ''}
            {ligne.evenement?.salleRef?.nom || ligne.evenement?.salle || 'Salle non précisée'}
          </p>
        </div>
      ),
    },
    {
      key: 'scan',
      label: 'Scan',
      render: (_, ligne) => {
        const horodatage = formaterDateHeure(ligne.heure_scan);
        const creneau = ligne.evenement?.heure_debut && ligne.evenement?.heure_fin
          ? `Séance ${String(ligne.evenement.heure_debut).slice(0, 5)} – ${String(ligne.evenement.heure_fin).slice(0, 5)}`
          : null;

        return (
          <div className="min-w-[130px]">
            <p className="text-on-surface">{horodatage || 'Non enregistré'}</p>
            {creneau && <p className="text-[11px] text-on-surface-variant">{creneau}</p>}
          </div>
        );
      },
    },
    {
      key: 'indices',
      label: "Indices d'anomalie",
      render: (_, ligne) => {
        const indices = indicesAnomalie(ligne);
        if (indices.length === 0) {
          return <span className="text-on-surface-variant/60">Aucun indice</span>;
        }

        return (
          <div className="flex flex-col gap-1 min-w-[180px]">
            {indices.map(({ cle, libelle, details, Icone }) => (
              <div key={cle} className="text-[11px] font-medium text-on-surface-variant">
                <span className="inline-flex items-center gap-1.5">
                  <Icone size={12} className="text-warning shrink-0" aria-hidden="true" />
                  {libelle}
                </span>
                {details && (
                  <ul className="mt-0.5 pl-[18px] space-y-0.5 text-on-surface">
                    {details.map((detail, i) => <li key={`${cle}-${i}`}>{detail}</li>)}
                  </ul>
                )}
              </div>
            ))}
          </div>
        );
      },
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (_, ligne) => (
        <div className="flex items-center gap-1.5">
          <button
            type="button"
            onClick={() => { setMotif(''); setCible({ type: 'valider', presence: ligne }); }}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-lg text-[11px] font-bold hover:bg-primary/20 transition-colors"
            aria-label={`Valider la présence de ${nomComplet(ligne.etudiant)}`}
          >
            <FiCheckCircle size={12} aria-hidden="true" /> Valider
          </button>
          <button
            type="button"
            onClick={() => { setMotif(''); setCible({ type: 'rejeter', presence: ligne }); }}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-error/10 text-error rounded-lg text-[11px] font-bold hover:bg-error/20 transition-colors"
            aria-label={`Rejeter la présence de ${nomComplet(ligne.etudiant)}`}
          >
            <FiXCircle size={12} aria-hidden="true" /> Rejeter
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      {/* En-tête */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Validation manuelle des présences</h1>
          <p className="text-sm text-on-surface-variant">
            Arbitrez les scans suspects : un même téléphone a servi à plusieurs étudiants pendant une séance.
          </p>
        </div>
        <div className="flex items-center gap-3">
          {pagination?.total > 0 && (
            <span className="px-3 py-1.5 rounded-xl bg-warning-container/30 text-warning text-xs font-semibold whitespace-nowrap">
              {pagination.total} à traiter
            </span>
          )}
          <button
            type="button"
            onClick={rafraichir}
            className="flex items-center gap-1.5 px-4 py-2 bg-surface-container-high rounded-xl text-xs font-semibold text-on-surface-variant hover:bg-surface-container-high/80 transition-colors"
          >
            <FiRefreshCw size={14} className={chargement ? 'animate-spin' : ''} aria-hidden="true" />
            Actualiser
          </button>
        </div>
      </div>

      {/* Filtres serveur */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px] flex-1">
            <label htmlFor="file-filiere" className={classeLibelle}>Filière</label>
            <select
              id="file-filiere"
              value={filtreFiliere}
              onChange={(e) => { setFiltreFiliere(e.target.value); setPage(1); }}
              className={classeChamp}
            >
              <option value="">Toutes</option>
              {filieres.map((filiere) => (
                <option key={filiere.id} value={filiere.id}>{filiere.code}</option>
              ))}
            </select>
          </div>
          <div className="space-y-1 min-w-[150px] flex-1">
            <label htmlFor="file-date-debut" className={classeLibelle}>Séances à partir du</label>
            <input
              id="file-date-debut"
              type="date"
              value={dateDebut}
              onChange={(e) => { setDateDebut(e.target.value); setPage(1); }}
              className={classeChamp}
            />
          </div>
          <div className="space-y-1 min-w-[150px] flex-1">
            <label htmlFor="file-date-fin" className={classeLibelle}>Séances jusqu'au</label>
            <input
              id="file-date-fin"
              type="date"
              value={dateFin}
              onChange={(e) => { setDateFin(e.target.value); setPage(1); }}
              className={classeChamp}
            />
          </div>
          {filtresServeurActifs && (
            <button
              type="button"
              onClick={reinitialiserFiltres}
              className="flex items-center justify-center gap-1.5 px-3 py-2 bg-surface-container-high text-on-surface-variant rounded-lg text-sm font-semibold hover:bg-surface-container-high/80 transition-colors"
            >
              <FiRefreshCw size={14} aria-hidden="true" /> Réinitialiser
            </button>
          )}
        </div>
      </div>

      {/* Recherche (côté serveur) */}
      <SearchInput
        value={recherche}
        onChange={(valeur) => { setRecherche(valeur); setPage(1); }}
        placeholder="Rechercher un étudiant (nom ou matricule)..."
        className="max-w-md"
      />

      {/* Contenu : erreur réseau, ou tableau (chargement et vide gérés par DataTable) */}
      {erreur ? (
        <div className="bg-surface-container-lowest rounded-xxl p-10 shadow-sm border border-outline-variant/10 text-center">
          <div className="w-14 h-14 mx-auto mb-4 rounded-2xl bg-error-container/30 flex items-center justify-center">
            <FiAlertTriangle className="text-error" size={26} aria-hidden="true" />
          </div>
          <h2 className="text-base font-semibold text-on-surface mb-1">Chargement impossible</h2>
          <p className="text-sm text-on-surface-variant mb-5">{erreur}</p>
          <Button variant="outline" size="sm" onClick={rafraichir} className="mx-auto">
            <FiRefreshCw size={14} aria-hidden="true" /> Réessayer
          </Button>
        </div>
      ) : (
        <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-hidden">
          <DataTable
            columns={colonnes}
            data={presences}
            loading={chargement}
            aria-label="Présences en attente de validation manuelle"
            caption="File des scans suspects à valider ou rejeter"
            emptyMessage={rechercheActive
              ? 'Aucun scan suspect ne correspond à cette recherche.'
              : "Aucune présence n'attend de validation. Les scans suspects apparaîtront ici dès qu'ils seront enregistrés."}
            emptyAction={rechercheActive ? (
              <Button variant="outline" size="sm" onClick={effacerRecherche}>
                Effacer la recherche
              </Button>
            ) : (
              <Button variant="outline" size="sm" onClick={rafraichir}>
                <FiRefreshCw size={14} aria-hidden="true" /> Actualiser
              </Button>
            )}
            pagination={pagination}
            onPageChange={setPage}
          />
        </div>
      )}

      {/* Confirmation de validation / saisie du motif de rejet */}
      {cible && (
        <Modal
          isOpen
          onClose={fermerModale}
          title={cible.type === 'valider' ? 'Valider la présence' : 'Rejeter la présence'}
        >
          <div className="space-y-4">
            <div className="rounded-xl bg-surface-container-high p-4 space-y-0.5">
              <p className="text-sm font-semibold text-on-surface">{nomComplet(cible.presence.etudiant)}</p>
              <p className="text-[11px] font-mono text-on-surface-variant">
                {cible.presence.etudiant?.matricule || 'Matricule inconnu'}
              </p>
              <p className="text-xs text-on-surface-variant">{intituleCours(cible.presence)}</p>
              <p className="text-xs text-on-surface-variant">
                Scan : {formaterDateHeure(cible.presence.heure_scan) || 'non enregistré'}
              </p>
            </div>

            {cible.type === 'valider' ? (
              <p className="text-sm text-on-surface-variant">
                La présence passera au statut « valide ». L'opération est tracée à votre nom
                dans le journal d'audit. Confirmez-vous cette validation ?
              </p>
            ) : (
              <div className="space-y-1.5">
                <label htmlFor={idMotif} className="text-xs font-semibold text-on-surface-variant">
                  Motif du rejet <span className="text-error">*</span>
                </label>
                <textarea
                  id={idMotif}
                  rows="4"
                  maxLength={MOTIF_MAX}
                  value={motif}
                  onChange={(e) => setMotif(e.target.value)}
                  placeholder="Expliquez pourquoi cette présence est rejetée (obligatoire)."
                  className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-colors resize-none"
                />
                <p className="text-[10px] text-on-surface-variant text-right">
                  {motif.length}/{MOTIF_MAX} caractères
                </p>
              </div>
            )}

            <div className="flex justify-end gap-3 pt-2">
              <Button variant="ghost" size="sm" onClick={fermerModale} disabled={envoi}>
                Annuler
              </Button>
              <Button
                variant={cible.type === 'valider' ? 'primary' : 'destructive'}
                size="sm"
                onClick={confirmerAction}
                loading={envoi}
                disabled={cible.type === 'rejeter' && !motif.trim()}
              >
                {cible.type === 'valider' ? 'Confirmer la validation' : 'Confirmer le rejet'}
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
