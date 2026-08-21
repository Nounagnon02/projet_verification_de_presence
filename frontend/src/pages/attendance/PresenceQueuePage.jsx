import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import {
  FiAlertTriangle, FiCheckCircle, FiClock, FiMapPin,
  FiRefreshCw, FiSmartphone, FiXCircle,
} from 'react-icons/fi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';
import Badge from '../../components/ui/Badge';
import Button from '../../components/ui/Button';
import DataTable from '../../components/ui/DataTable';
import Modal from '../../components/ui/Modal';
import SearchInput from '../../components/ui/SearchInput';

// Statuts réellement retournés par GET /admin/presence/pending : le contrôleur
// restreint la file à ces trois valeurs (whereIn statut).
const STATUTS = {
  suspect: { libelle: 'Suspect', variante: 'warning' },
  en_attente: { libelle: 'En attente', variante: 'info' },
  invalide: { libelle: 'Invalide', variante: 'error' },
};

const ONGLETS_STATUT = [
  ['', 'Tous'],
  ['suspect', 'Suspects'],
  ['en_attente', 'En attente'],
  ['invalide', 'Invalides'],
];

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

/**
 * Indices d'anomalie affichés pour une présence.
 *
 * Le backend ne renvoie aucun score d'anomalie sur cet endpoint : la table
 * « anomalies » n'est pas rattachée aux présences et pendingValidations ne la
 * charge pas. Les indices sont donc déduits des champs effectivement présents
 * dans la réponse (statut, GPS, empreinte d'appareil, horodatage du scan).
 */
const indicesAnomalie = (presence) => {
  const indices = [];

  if (presence.statut === 'suspect') {
    indices.push({ cle: 'suspect', libelle: 'Scan marqué suspect', Icone: FiAlertTriangle });
  }
  if (presence.latitude === null || presence.latitude === undefined
    || presence.longitude === null || presence.longitude === undefined) {
    indices.push({ cle: 'gps', libelle: 'Position GPS absente', Icone: FiMapPin });
  }
  if (!presence.device_fingerprint) {
    indices.push({ cle: 'appareil', libelle: 'Appareil non identifié', Icone: FiSmartphone });
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

  const [presences, setPresences] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState('');

  // Filtres envoyés au serveur (les seuls que le contrôleur accepte).
  const [page, setPage] = useState(1);
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');
  const [rechargement, setRechargement] = useState(0);

  // Affinage local : l'endpoint n'accepte ni « statut » ni « search », ces deux
  // filtres ne portent donc que sur les lignes de la page affichée.
  const [filtreStatut, setFiltreStatut] = useState('');
  const [recherche, setRecherche] = useState('');

  const [filieres, setFilieres] = useState([]);
  const [cible, setCible] = useState(null);
  const [motif, setMotif] = useState('');
  const [envoi, setEnvoi] = useState(false);

  const controleursActions = useRef(new Set());
  const monte = useRef(true);

  const rafraichir = useCallback(() => setRechargement((n) => n + 1), []);

  // Les requêtes d'action (valider / rejeter) sont abandonnées au démontage,
  // comme le chargement de la liste.
  useEffect(() => {
    const controleurs = controleursActions.current;
    monte.current = true;
    return () => {
      monte.current = false;
      controleurs.forEach((controleur) => controleur.abort());
      controleurs.clear();
    };
  }, []);

  // Liste des filières pour le filtre. Son échec n'empêche pas de travailler.
  useEffect(() => {
    const controleur = new AbortController();

    (async () => {
      try {
        const { data } = await api.get('/admin/filieres', { signal: controleur.signal });
        setFilieres(Array.isArray(data?.data) ? data.data : []);
      } catch {
        // Filtre secondaire : on reste silencieux plutôt que d'alarmer.
      }
    })();

    return () => controleur.abort();
  }, []);

  // Chargement de la file, intégré à l'effet et annulable : quatre entrées le
  // pilotent, et deux changements rapprochés feraient partir deux requêtes dont
  // l'ordre de retour n'est pas garanti.
  useEffect(() => {
    const controleur = new AbortController();
    let annule = false;

    (async () => {
      setChargement(true);
      setErreur('');

      try {
        const params = { page, per_page: PAR_PAGE };
        if (filtreFiliere) params.filiere_id = filtreFiliere;
        if (dateDebut) params.date_from = dateDebut;
        if (dateFin) params.date_to = dateFin;

        const { data } = await api.get('/admin/presence/pending', {
          params,
          signal: controleur.signal,
        });

        if (annule) return;

        // pendingValidations renvoie le paginateur Laravel brut dans « data » :
        // { current_page, data: [...], from, to, last_page, per_page, total }.
        const paginateur = data?.data ?? {};
        setPresences(Array.isArray(paginateur.data) ? paginateur.data : []);
        setPagination(paginateur.current_page ? paginateur : null);
      } catch (err) {
        if (annule || err.name === 'CanceledError' || err.name === 'AbortError') return;
        setPresences([]);
        setPagination(null);
        setErreur(
          err.response?.data?.message
          || 'Impossible de charger les présences à valider. Vérifiez votre connexion puis réessayez.'
        );
      } finally {
        if (!annule) setChargement(false);
      }
    })();

    return () => {
      annule = true;
      controleur.abort();
    };
  }, [page, filtreFiliere, dateDebut, dateFin, rechargement]);

  const lignesAffichees = useMemo(() => {
    const terme = recherche.trim().toLowerCase();

    return presences.filter((presence) => {
      if (filtreStatut && presence.statut !== filtreStatut) return false;
      if (!terme) return true;

      const etudiant = presence.etudiant || {};
      return [etudiant.nom, etudiant.prenom, etudiant.matricule]
        .some((valeur) => (valeur || '').toLowerCase().includes(terme));
    });
  }, [presences, filtreStatut, recherche]);

  const filtresServeurActifs = Boolean(filtreFiliere || dateDebut || dateFin);
  const affinageActif = Boolean(filtreStatut || recherche.trim());

  const reinitialiserFiltres = () => {
    setFiltreFiliere('');
    setDateDebut('');
    setDateFin('');
    setPage(1);
  };

  const reinitialiserAffinage = () => {
    setFiltreStatut('');
    setRecherche('');
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

    const controleur = new AbortController();
    controleursActions.current.add(controleur);
    setEnvoi(true);

    // Retrait optimiste : la ligne quitte la file immédiatement et n'y revient
    // qu'en cas d'échec réel de l'appel.
    setPresences((precedent) => precedent.filter((ligne) => ligne.id !== presence.id));
    ajusterCompteurs(-1);

    let fermer = true;

    try {
      const corps = { action: type };
      if (motifNettoye) corps.motif = motifNettoye;

      const { data } = await api.patch(`/admin/presence/${presence.id}/validate`, corps, {
        signal: controleur.signal,
      });

      if (!monte.current) return;

      addToast?.(
        data?.message || (type === 'valider' ? 'Présence validée.' : 'Présence rejetée.'),
        'success',
      );

      // La page vient de se vider : on recharge pour reprendre la suite de la file.
      if (derniereLigne) rafraichir();
    } catch (err) {
      if (err.name === 'CanceledError' || err.name === 'AbortError') return;
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
      controleursActions.current.delete(controleur);
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
          ? `Séance ${ligne.evenement.heure_debut} – ${ligne.evenement.heure_fin}`
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
          <div className="flex flex-col gap-1 min-w-[160px]">
            {indices.map(({ cle, libelle, Icone }) => (
              <span key={cle} className="inline-flex items-center gap-1.5 text-[11px] font-medium text-on-surface-variant">
                <Icone size={12} className="text-warning shrink-0" aria-hidden="true" />
                {libelle}
              </span>
            ))}
          </div>
        );
      },
    },
    {
      key: 'statut',
      label: 'Statut',
      render: (valeur) => {
        const config = STATUTS[valeur];
        return (
          <Badge variant={config?.variante || 'neutral'}>
            {config?.libelle || valeur || 'Inconnu'}
          </Badge>
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
            Arbitrez les présences suspectes, en attente ou invalides signalées par le système.
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

      {/* Affinage local */}
      <div className="flex flex-col md:flex-row gap-4 md:items-center">
        <SearchInput
          value={recherche}
          onChange={setRecherche}
          placeholder="Filtrer par nom ou matricule..."
          className="flex-1 max-w-md"
        />
        <div className="flex flex-wrap gap-2">
          {ONGLETS_STATUT.map(([cle, libelle]) => (
            <button
              key={cle || 'tous'}
              type="button"
              onClick={() => setFiltreStatut(cle)}
              aria-pressed={filtreStatut === cle}
              className={`px-4 py-2 rounded-xl text-xs font-semibold transition-colors ${
                filtreStatut === cle
                  ? 'bg-primary text-on-primary shadow-sm'
                  : 'bg-surface-container-high text-on-surface-variant hover:text-primary'
              }`}
            >
              {libelle}
            </button>
          ))}
        </div>
      </div>
      <p className="text-[11px] text-on-surface-variant">
        La recherche et le filtre de statut s'appliquent aux lignes de la page affichée :
        l'API de la file ne prend pas ces deux critères en charge.
      </p>

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
          {affinageActif && !chargement && (
            <p className="px-4 pt-4 text-[11px] text-on-surface-variant">
              {lignesAffichees.length} ligne(s) affichée(s) sur les {presences.length} de cette page.
            </p>
          )}
          <DataTable
            columns={colonnes}
            data={lignesAffichees}
            loading={chargement}
            aria-label="Présences en attente de validation manuelle"
            caption="File des présences suspectes, en attente ou invalides à valider ou rejeter"
            emptyMessage={affinageActif
              ? 'Aucune ligne de cette page ne correspond à ces critères.'
              : "Aucune présence n'attend de validation. Les scans suspects, en attente ou invalides apparaîtront ici dès qu'ils seront enregistrés."}
            emptyAction={affinageActif ? (
              <Button variant="outline" size="sm" onClick={reinitialiserAffinage}>
                Effacer les critères
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
