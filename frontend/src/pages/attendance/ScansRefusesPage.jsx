import { useCallback, useEffect, useId, useState } from 'react';
import { FiAlertTriangle, FiCheckCircle, FiMapPin, FiRefreshCw, FiShield, FiSmartphone } from 'react-icons/fi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';
import useDebounce from '../../hooks/useDebounce';
import Button from '../../components/ui/Button';
import DataTable from '../../components/ui/DataTable';
import Modal from '../../components/ui/Modal';
import SearchInput from '../../components/ui/SearchInput';

/**
 * Scans refusés par le serveur.
 *
 * Aucune présence n'a été enregistrée pour ces tentatives : il n'y a rien à
 * valider ni à rejeter. La page sert à comprendre un refus — la réclamation
 * d'un étudiant, des tentatives répétées — et, quand l'étudiant était bien en
 * salle (GPS imprécis, Wi-Fi mal capté), à enregistrer sa présence avec un
 * motif. Les scans suspects, eux, se tranchent dans la file d'attente.
 *
 * Elle remplace « Alertes de fraude », qui mêlait refus et scans suspects,
 * affichait « Inconnu » pour tous les types, et proposait « Valide » et
 * « Ignorer » : deux boutons sans objet sur un refus, et une porte dérobée sur
 * un double scan.
 */

const RAISONS = {
  verification_echouee: { libelle: 'Hors de la salle (GPS ou Wi-Fi)', Icone: FiMapPin },
  invalid_scan_challenge: { libelle: 'Défi de sécurité invalide', Icone: FiShield },
  double_scan_device_mismatch: { libelle: 'Second scan depuis un autre téléphone', Icone: FiSmartphone },
};
const RAISON_INCONNUE = { libelle: 'Scan refusé', Icone: FiAlertTriangle };

// Présence de l'étudiant à la séance visée, quand il en a une.
const ETATS_PRESENCE = {
  valide: { libelle: 'Présent', classe: 'bg-secondary/10 text-secondary' },
  suspect: { libelle: "Présence suspecte — file d'attente", classe: 'bg-warning-container/30 text-warning' },
  rejete: { libelle: 'Présence rejetée', classe: 'bg-error/10 text-error' },
};

const PAR_PAGE = 20;
const MOTIF_MAX = 500;

const classeChamp = 'w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20';
const classeLibelle = 'text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider';

/** Nom affichable d'un étudiant, sans laisser passer « undefined ». */
const nomComplet = (etudiant) => {
  if (!etudiant) return 'Étudiant inconnu';
  return `${etudiant.prenom || ''} ${etudiant.nom || ''}`.trim() || 'Étudiant inconnu';
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

/** « 2026-08-25 » -> « 25/08/2026 », sans passer par un fuseau. */
const formaterDate = (iso) => (iso ? iso.split('-').reverse().join('/') : '');

/** « Programmation en C · 25/08/2026 · 08:00 – 10:00 » */
const decrireSeance = (seance) => [
  seance?.cours || seance?.code || 'Cours inconnu',
  formaterDate(seance?.date),
  seance?.heure_debut && seance?.heure_fin ? `${seance.heure_debut} – ${seance.heure_fin}` : '',
].filter(Boolean).join(' · ');

export default function ScansRefusesPage() {
  const { addToast } = useToastCtx();
  const idMotif = useId();

  const [refus, setRefus] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState('');

  const [page, setPage] = useState(1);
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');
  const [recherche, setRecherche] = useState('');
  const rechercheServeur = useDebounce(recherche.trim(), 300);
  const [rechargement, setRechargement] = useState(0);

  const [filieres, setFilieres] = useState([]);

  // Enregistrement d'une présence depuis un refus.
  const [cible, setCible] = useState(null);
  const [motif, setMotif] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreurModale, setErreurModale] = useState('');

  const rafraichir = useCallback(() => setRechargement((n) => n + 1), []);

  // Liste des filières pour le filtre. Son échec n'empêche pas de consulter.
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
        if (rechercheServeur) params.search = rechercheServeur;

        const { data } = await api.get('/admin/alerts', { params, signal: controleur.signal });
        if (annule) return;

        setRefus(Array.isArray(data?.data) ? data.data : []);
        setPagination(data?.meta ?? null);
      } catch (err) {
        if (annule || err.name === 'CanceledError' || err.name === 'AbortError') return;
        setRefus([]);
        setPagination(null);
        setErreur(err.response?.data?.message || 'Impossible de charger les scans refusés. Vérifiez votre connexion puis réessayez.');
      } finally {
        if (!annule) setChargement(false);
      }
    })();

    return () => {
      annule = true;
      controleur.abort();
    };
  }, [page, filtreFiliere, dateDebut, dateFin, rechercheServeur, rechargement]);

  const filtresActifs = Boolean(filtreFiliere || dateDebut || dateFin || rechercheServeur);

  const reinitialiser = () => {
    setFiltreFiliere('');
    setDateDebut('');
    setDateFin('');
    setRecherche('');
    setPage(1);
  };

  const ouvrir = (ligne) => {
    setMotif('');
    setErreurModale('');
    setCible(ligne);
  };

  const fermer = () => {
    if (envoi) return;
    setCible(null);
    setMotif('');
    setErreurModale('');
  };

  const enregistrer = async () => {
    const motifNettoye = motif.trim();
    if (!cible || !motifNettoye) return;

    setEnvoi(true);
    setErreurModale('');

    try {
      const { data } = await api.post(`/admin/alerts/${cible.id}/presence`, { motif: motifNettoye });
      const presence = data?.data?.presence;

      setRefus((precedent) => precedent.map((ligne) => (ligne.id === cible.id
        ? { ...ligne, presence: { id: presence?.id, statut: 'valide', depuis_ce_refus: true } }
        : ligne)));
      addToast?.(data?.message || 'Présence enregistrée.', 'success');
      setCible(null);
      setMotif('');
    } catch (err) {
      // Le détail par champ d'abord : le message racine d'une erreur de
      // validation n'est que « Erreur de validation. ».
      const detail = Object.values(err.response?.data?.errors ?? {}).flat()[0];
      setErreurModale(detail || err.response?.data?.message || "L'enregistrement a échoué. Réessayez.");
      // Déjà traité entre-temps : la liste doit refléter l'état réel.
      if (err.response?.status === 409) rafraichir();
    } finally {
      setEnvoi(false);
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
            {ligne.etudiant?.filiere ? ` · ${ligne.etudiant.filiere}` : ''}
          </p>
        </div>
      ),
    },
    {
      key: 'raison',
      label: 'Raison du refus',
      render: (_, ligne) => {
        const { libelle, Icone } = RAISONS[ligne.type] || RAISON_INCONNUE;
        return (
          <div className="min-w-[220px] max-w-md">
            <p className="inline-flex items-center gap-1.5 text-xs font-semibold text-on-surface">
              <Icone size={13} className="text-warning shrink-0" aria-hidden="true" />
              {libelle}
            </p>
            {ligne.description && (
              <p className="text-[11px] text-on-surface-variant mt-0.5">{ligne.description}</p>
            )}
          </div>
        );
      },
    },
    {
      key: 'seance',
      label: 'Séance visée',
      render: (_, ligne) => {
        const seance = ligne.evenement;
        if (!seance) {
          // Refus enregistré avant que la séance ne soit notée.
          return <span className="text-[11px] text-on-surface-variant/70">Séance non enregistrée</span>;
        }
        return (
          <div className="min-w-[150px]">
            <p className="text-on-surface">{seance.cours || seance.code || 'Cours inconnu'}</p>
            <p className="text-[11px] text-on-surface-variant">
              {formaterDate(seance.date)} · {seance.heure_debut} – {seance.heure_fin}
            </p>
          </div>
        );
      },
    },
    {
      key: 'creee_le',
      label: 'Tentative',
      render: (valeur) => (
        <span className="text-xs text-on-surface whitespace-nowrap">{formaterDateHeure(valeur) || '—'}</span>
      ),
    },
    {
      key: 'suite',
      label: 'Suite donnée',
      render: (_, ligne) => {
        if (ligne.presence) {
          const etat = ETATS_PRESENCE[ligne.presence.statut] || ETATS_PRESENCE.valide;
          return (
            <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap ${etat.classe}`}>
              <FiCheckCircle size={12} aria-hidden="true" />
              {ligne.presence.depuis_ce_refus ? 'Présence enregistrée depuis ce refus' : etat.libelle}
            </span>
          );
        }
        if (!ligne.evenement) {
          return <span className="text-[11px] text-on-surface-variant/70">Séance inconnue : rien à rattacher</span>;
        }
        return (
          <Button variant="primary" size="sm" className="whitespace-nowrap" onClick={() => ouvrir(ligne)}>
            Enregistrer la présence
          </Button>
        );
      },
    },
  ];

  const raisonCible = cible ? (RAISONS[cible.type] || RAISON_INCONNUE).libelle : '';

  return (
    <div className="space-y-4">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Scans refusés</h1>
          <p className="text-sm text-on-surface-variant">
            Tentatives rejetées par le serveur : aucune présence n'a été enregistrée. Si l'étudiant était
            bien en salle, enregistrez sa présence avec un motif. Les scans suspects se tranchent dans la
            file d'attente.
          </p>
        </div>
        <button
          type="button"
          onClick={rafraichir}
          className="self-start md:self-auto flex items-center gap-1.5 px-4 py-2 bg-surface-container-high rounded-xl text-xs font-semibold text-on-surface-variant hover:bg-surface-container-high/80 transition-colors"
        >
          <FiRefreshCw size={14} className={chargement ? 'animate-spin' : ''} aria-hidden="true" />
          Actualiser
        </button>
      </div>

      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px] flex-1">
            <label htmlFor="refus-filiere" className={classeLibelle}>Filière</label>
            <select
              id="refus-filiere"
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
            <label htmlFor="refus-date-debut" className={classeLibelle}>Du</label>
            <input
              id="refus-date-debut"
              type="date"
              value={dateDebut}
              onChange={(e) => { setDateDebut(e.target.value); setPage(1); }}
              className={classeChamp}
            />
          </div>
          <div className="space-y-1 min-w-[150px] flex-1">
            <label htmlFor="refus-date-fin" className={classeLibelle}>Au</label>
            <input
              id="refus-date-fin"
              type="date"
              value={dateFin}
              onChange={(e) => { setDateFin(e.target.value); setPage(1); }}
              className={classeChamp}
            />
          </div>
        </div>
      </div>

      <SearchInput
        value={recherche}
        onChange={(valeur) => { setRecherche(valeur); setPage(1); }}
        placeholder="Rechercher un étudiant (nom ou matricule)..."
        className="max-w-md"
      />

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
            data={refus}
            loading={chargement}
            aria-label="Scans refusés"
            caption="Tentatives de scan rejetées par le serveur"
            emptyMessage={filtresActifs
              ? 'Aucun scan refusé ne correspond à ces critères.'
              : 'Aucun scan refusé. Les tentatives rejetées apparaîtront ici.'}
            emptyAction={filtresActifs ? (
              <Button variant="outline" size="sm" onClick={reinitialiser}>
                Effacer les critères
              </Button>
            ) : null}
            pagination={pagination}
            onPageChange={setPage}
          />
        </div>
      )}

      {/* Enregistrement d'une présence à la place du scan refusé */}
      {cible && (
        <Modal isOpen onClose={fermer} title="Enregistrer la présence">
          <div className="space-y-4">
            <div className="rounded-xl bg-surface-container-high p-4 space-y-0.5">
              <p className="text-sm font-semibold text-on-surface">{nomComplet(cible.etudiant)}</p>
              <p className="text-[11px] font-mono text-on-surface-variant">{cible.etudiant?.matricule || 'Matricule inconnu'}</p>
              <p className="text-xs text-on-surface-variant">{decrireSeance(cible.evenement)}</p>
              <p className="text-xs text-on-surface-variant">
                Refusé : {raisonCible}, le {formaterDateHeure(cible.creee_le) || '—'}
              </p>
            </div>

            <p className="text-sm text-on-surface-variant">
              L'étudiant sera compté présent, à l'heure de sa tentative. Ne le faites que s'il était bien
              en cours — par exemple, confirmé par l'enseignant. L'opération est tracée à votre nom dans le
              journal d'audit.
            </p>

            <div className="space-y-1.5">
              <label htmlFor={idMotif} className="text-xs font-semibold text-on-surface-variant">
                Motif <span className="text-error">*</span>
              </label>
              <textarea
                id={idMotif}
                rows="3"
                maxLength={MOTIF_MAX}
                value={motif}
                onChange={(e) => setMotif(e.target.value)}
                placeholder="Ex. : présent en salle, GPS du téléphone imprécis — confirmé par l'enseignant."
                className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-colors resize-none"
              />
              <p className="text-[10px] text-on-surface-variant text-right">{motif.length}/{MOTIF_MAX} caractères</p>
            </div>

            {erreurModale && <p role="alert" className="text-sm text-error">{erreurModale}</p>}

            <div className="flex justify-end gap-3 pt-2">
              <Button variant="ghost" size="sm" onClick={fermer} disabled={envoi}>
                Annuler
              </Button>
              <Button variant="primary" size="sm" onClick={enregistrer} loading={envoi} disabled={!motif.trim()}>
                Enregistrer la présence
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
