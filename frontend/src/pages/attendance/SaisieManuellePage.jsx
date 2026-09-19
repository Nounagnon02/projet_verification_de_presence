import { useCallback, useId, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiAlertTriangle, FiCheckCircle, FiClock, FiRefreshCw, FiUsers } from 'react-icons/fi';
import { listerEtudiantsPourSaisieManuelle, saisirPresenceManuelle } from '../../api/resources/presences';
import { listerFilieres } from '../../api/resources/reference';
import { listerEvenements } from '../../api/resources/evenements';
import { useToastCtx } from '../../context/ToastContext';
import { aujourdhuiIso } from '../../utils/formatters';
import Button from '../../components/ui/Button';
import DataTable from '../../components/ui/DataTable';
import Modal from '../../components/ui/Modal';
import SearchInput from '../../components/ui/SearchInput';

/**
 * Saisie par l'administration d'un étudiant qui n'a pas pu scanner :
 * téléphone déchargé ou oublié, application en panne.
 *
 * On choisit une date, puis une séance ; la liste des étudiants attendus
 * s'affiche avec la présence de chacun, et un absent peut être marqué présent,
 * motif à l'appui. Cette page était un formulaire de scan exigeant un QR en
 * cours : ouverte depuis le menu, elle n'aboutissait jamais.
 */

const ETATS = {
  valide: { libelle: 'Présent', classe: 'bg-secondary/10 text-secondary' },
  suspect: { libelle: "Suspect — file d'attente", classe: 'bg-warning-container/30 text-warning' },
  rejete: { libelle: 'Rejeté', classe: 'bg-error/10 text-error' },
};
const ABSENT = { libelle: 'Absent', classe: 'bg-surface-container-high text-on-surface-variant' };

const MOTIF_MAX = 500;

const classeChamp = 'w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20';
const classeLibelle = 'text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider';

const nomComplet = (etudiant) => `${etudiant?.prenom || ''} ${etudiant?.nom || ''}`.trim() || 'Étudiant inconnu';
const hhmm = (heure) => String(heure || '').slice(0, 5);
const heureLocale = () => {
  const maintenant = new Date();
  return `${String(maintenant.getHours()).padStart(2, '0')}:${String(maintenant.getMinutes()).padStart(2, '0')}`;
};

/** Raison pour laquelle une séance ne peut pas recevoir de saisie, ou null. */
const indisponibilite = (seance) => {
  if (seance.statut === 'annule') return 'Annulée';
  if (seance.date === aujourdhuiIso() && hhmm(seance.heure_debut) > heureLocale()) return 'Pas encore commencée';
  return null;
};

export default function SaisieManuellePage() {
  const { addToast } = useToastCtx();
  const idMotif = useId();

  const [date, setDate] = useState(aujourdhuiIso());
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [seanceId, setSeanceId] = useState(null);
  const [recherche, setRecherche] = useState('');

  const [cible, setCible] = useState(null);
  const [motif, setMotif] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreurModale, setErreurModale] = useState('');

  const queryClient = useQueryClient();
  const rafraichir = useCallback(
    () => queryClient.invalidateQueries({ queryKey: ['etudiants-manuelle', seanceId] }),
    [queryClient, seanceId],
  );

  // Filières pour le filtre. Leur échec n'empêche pas de travailler.
  const filieresQuery = useQuery({ queryKey: ['filieres'], queryFn: () => listerFilieres() });
  const filieres = Array.isArray(filieresQuery.data?.data) ? filieresQuery.data.data : [];

  // Séances de la date choisie.
  const parametresSeances = useMemo(() => {
    const params = { date_debut: date, date_fin: date };
    if (filtreFiliere) params.filiere_id = filtreFiliere;
    return params;
  }, [date, filtreFiliere]);

  const seancesQuery = useQuery({
    queryKey: ['seances-manuelle', parametresSeances],
    queryFn: ({ signal }) => listerEvenements(parametresSeances, signal),
  });

  const listeSeances = useMemo(() => {
    const brutes = seancesQuery.data?.data ?? seancesQuery.data ?? [];
    return (Array.isArray(brutes) ? brutes : [])
      .slice()
      .sort((a, b) => hhmm(a.heure_debut).localeCompare(hhmm(b.heure_debut)));
  }, [seancesQuery.data]);

  const seances = {
    chargement: seancesQuery.isFetching,
    liste: listeSeances,
    erreur: seancesQuery.isError ? 'Impossible de charger les séances de cette date.' : '',
  };

  // Étudiants attendus à la séance choisie.
  const etudiantsQuery = useQuery({
    queryKey: ['etudiants-manuelle', seanceId],
    queryFn: ({ signal }) => listerEtudiantsPourSaisieManuelle(seanceId, signal),
    enabled: Boolean(seanceId),
  });

  const liste = {
    chargement: etudiantsQuery.isFetching,
    seance: etudiantsQuery.data?.data?.seance ?? null,
    etudiants: Array.isArray(etudiantsQuery.data?.data?.etudiants) ? etudiantsQuery.data.data.etudiants : [],
    erreur: etudiantsQuery.isError
      ? (etudiantsQuery.error?.response?.data?.message || 'Impossible de charger les étudiants de cette séance.')
      : '',
  };

  const choisirDate = (valeur) => {
    setDate(valeur);
    setSeanceId(null);
    setRecherche('');
  };

  const choisirFiliere = (valeur) => {
    setFiltreFiliere(valeur);
    setSeanceId(null);
    setRecherche('');
  };

  const choisirSeance = (id) => {
    setSeanceId(id);
    setRecherche('');
  };

  // La liste d'une séance est entière (une promotion), la recherche porte donc
  // sur toutes ses lignes.
  const etudiantsAffiches = useMemo(() => {
    const terme = recherche.trim().toLowerCase();
    if (!terme) return liste.etudiants;
    return liste.etudiants.filter((e) => [e.nom, e.prenom, e.matricule, nomComplet(e)]
      .some((valeur) => (valeur || '').toLowerCase().includes(terme)));
  }, [liste.etudiants, recherche]);

  const compteurs = useMemo(() => liste.etudiants.reduce((acc, e) => {
    const cle = e.presence?.statut || 'absent';
    acc[cle] = (acc[cle] || 0) + 1;
    return acc;
  }, {}), [liste.etudiants]);

  const ouvrir = (etudiant) => {
    setMotif('');
    setErreurModale('');
    setCible(etudiant);
  };

  const fermer = () => {
    if (envoi) return;
    setCible(null);
    setMotif('');
    setErreurModale('');
  };

  const enregistrer = async () => {
    const motifNettoye = motif.trim();
    if (!cible || !motifNettoye || !seanceId) return;

    setEnvoi(true);
    setErreurModale('');
    try {
      const data = await saisirPresenceManuelle({
        evenement_id: seanceId,
        etudiant_id: cible.id,
        motif: motifNettoye,
      });
      const presence = data?.data?.presence;
      queryClient.setQueryData(['etudiants-manuelle', seanceId], (precedent) => {
        if (!precedent?.data) return precedent;
        return {
          ...precedent,
          data: {
            ...precedent.data,
            etudiants: (precedent.data.etudiants ?? []).map((e) => (e.id === cible.id
              ? { ...e, presence: { id: presence?.id, statut: 'valide' } }
              : e)),
          },
        };
      });
      addToast?.(data?.message || 'Présence enregistrée.', 'success');
      setCible(null);
      setMotif('');
    } catch (err) {
      // Le détail par champ d'abord : le message racine d'une erreur de
      // validation n'est que « Erreur de validation. ».
      const detail = Object.values(err.response?.data?.errors ?? {}).flat()[0];
      setErreurModale(detail || err.response?.data?.message || "L'enregistrement a échoué. Réessayez.");
      // Déjà enregistrée entre-temps : la liste doit refléter l'état réel.
      if (err.response?.status === 409) rafraichir();
    } finally {
      setEnvoi(false);
    }
  };

  const colonnes = [
    {
      key: 'etudiant',
      label: 'Étudiant',
      render: (_, e) => (
        <div className="min-w-[160px]">
          <p className="font-semibold text-on-surface">{nomComplet(e)}</p>
          <p className="text-[11px] font-mono text-on-surface-variant">{e.matricule || 'Matricule inconnu'}</p>
        </div>
      ),
    },
    {
      key: 'presence',
      label: 'Présence',
      render: (_, e) => {
        const etat = e.presence ? (ETATS[e.presence.statut] || ETATS.valide) : ABSENT;
        return (
          <span className={`inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap ${etat.classe}`}>
            {etat.libelle}
          </span>
        );
      },
    },
    {
      key: 'action',
      label: 'Action',
      render: (_, e) => (e.presence ? (
        <span className="text-[11px] text-on-surface-variant/70">—</span>
      ) : (
        <Button variant="primary" size="sm" className="whitespace-nowrap" onClick={() => ouvrir(e)}>
          Marquer présent
        </Button>
      )),
    },
  ];

  const seance = liste.seance;

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-primary font-headline">Saisie manuelle</h1>
        <p className="text-sm text-on-surface-variant">
          Enregistrez un étudiant qui n'a pas pu scanner (téléphone déchargé, application en panne).
          Choisissez la séance, puis marquez l'étudiant présent en indiquant le motif.
        </p>
      </div>

      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px]">
            <label htmlFor="saisie-date" className={classeLibelle}>Date de la séance</label>
            <input
              id="saisie-date"
              type="date"
              value={date}
              max={aujourdhuiIso()}
              onChange={(e) => choisirDate(e.target.value)}
              className={classeChamp}
            />
          </div>
          <div className="space-y-1 min-w-[160px] flex-1 max-w-xs">
            <label htmlFor="saisie-filiere" className={classeLibelle}>Filière</label>
            <select id="saisie-filiere" value={filtreFiliere} onChange={(e) => choisirFiliere(e.target.value)} className={classeChamp}>
              <option value="">Toutes</option>
              {filieres.map((filiere) => (
                <option key={filiere.id} value={filiere.id}>{filiere.code}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="mt-4">
          <p className={`${classeLibelle} mb-2`}>Séances</p>
          {seances.chargement ? (
            <p className="text-sm text-on-surface-variant">Chargement des séances…</p>
          ) : seances.erreur ? (
            <p className="text-sm text-error">{seances.erreur}</p>
          ) : seances.liste.length === 0 ? (
            <p className="text-sm text-on-surface-variant">Aucune séance ce jour-là.</p>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
              {seances.liste.map((s) => {
                const raison = indisponibilite(s);
                const choisie = s.id === seanceId;
                return (
                  <button
                    key={s.id}
                    type="button"
                    disabled={Boolean(raison)}
                    onClick={() => choisirSeance(s.id)}
                    aria-pressed={choisie}
                    className={`text-left rounded-xl border px-3 py-2.5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${
                      choisie
                        ? 'border-primary bg-primary/10'
                        : 'border-outline-variant/30 hover:bg-surface-container-high'
                    }`}
                  >
                    <span className="block text-sm font-semibold text-on-surface">{s.ec?.intitule || s.ec?.code || 'Cours'}</span>
                    <span className="flex items-center gap-1.5 text-[11px] text-on-surface-variant mt-0.5">
                      <FiClock size={11} aria-hidden="true" />
                      {hhmm(s.heure_debut)} – {hhmm(s.heure_fin)}
                      {s.filiere?.code ? ` · ${s.filiere.code}` : ''}
                      {s.salle_ref?.nom || s.salle ? ` · ${s.salle_ref?.nom || s.salle}` : ''}
                    </span>
                    {raison && <span className="block text-[11px] font-semibold text-on-surface-variant mt-0.5">{raison}</span>}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {seanceId && (
        liste.erreur ? (
          <div className="bg-surface-container-lowest rounded-xxl p-8 shadow-sm border border-outline-variant/10 text-center">
            <FiAlertTriangle className="text-error mx-auto mb-3" size={24} aria-hidden="true" />
            <p className="text-sm text-on-surface-variant mb-4">{liste.erreur}</p>
            <Button variant="outline" size="sm" onClick={rafraichir} className="mx-auto">
              <FiRefreshCw size={14} aria-hidden="true" /> Réessayer
            </Button>
          </div>
        ) : (
          <div className="space-y-3">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-bold text-primary font-headline">
                  {seance ? `${seance.cours || seance.code || 'Cours'} · ${hhmm(seance.heure_debut)} – ${hhmm(seance.heure_fin)}` : 'Séance'}
                </h2>
                {!liste.chargement && (
                  <p className="flex items-center gap-1.5 text-xs text-on-surface-variant">
                    <FiUsers size={12} aria-hidden="true" />
                    {liste.etudiants.length} attendu(s) · {compteurs.valide || 0} présent(s) · {compteurs.absent || 0} absent(s)
                    {compteurs.suspect ? ` · ${compteurs.suspect} à arbitrer` : ''}
                  </p>
                )}
              </div>
              <SearchInput
                value={recherche}
                onChange={setRecherche}
                placeholder="Rechercher un étudiant (nom ou matricule)..."
                className="max-w-sm w-full"
              />
            </div>
            <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-hidden">
              <DataTable
                columns={colonnes}
                data={etudiantsAffiches}
                loading={liste.chargement}
                aria-label="Étudiants attendus à la séance"
                caption="Étudiants attendus à la séance et leur présence"
                emptyMessage={recherche.trim()
                  ? 'Aucun étudiant ne correspond à cette recherche.'
                  : "Aucun étudiant n'est inscrit au cours de cette séance."}
              />
            </div>
          </div>
        )
      )}

      {cible && (
        <Modal isOpen onClose={fermer} title="Marquer présent">
          <div className="space-y-4">
            <div className="rounded-xl bg-surface-container-high p-4 space-y-0.5">
              <p className="text-sm font-semibold text-on-surface">{nomComplet(cible)}</p>
              <p className="text-[11px] font-mono text-on-surface-variant">{cible.matricule || 'Matricule inconnu'}</p>
              {seance && (
                <p className="text-xs text-on-surface-variant">
                  {seance.cours || seance.code} · {seance.date?.split('-').reverse().join('/')} · {hhmm(seance.heure_debut)} – {hhmm(seance.heure_fin)}
                </p>
              )}
            </div>

            <p className="text-sm text-on-surface-variant">
              L'étudiant sera compté présent. Pendant la séance, l'heure retenue est celle de la saisie ;
              après, l'heure de fin de la séance. L'opération est tracée à votre nom dans le journal d'audit.
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
                placeholder="Ex. : téléphone déchargé, présent en salle — confirmé par l'enseignant."
                className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-colors resize-none"
              />
              <p className="text-[10px] text-on-surface-variant text-right">{motif.length}/{MOTIF_MAX} caractères</p>
            </div>

            {erreurModale && <p role="alert" className="text-sm text-error">{erreurModale}</p>}

            <div className="flex justify-end gap-3 pt-2">
              <Button variant="ghost" size="sm" onClick={fermer} disabled={envoi}>Annuler</Button>
              <Button variant="primary" size="sm" onClick={enregistrer} loading={envoi} disabled={!motif.trim()}>
                <FiCheckCircle size={14} aria-hidden="true" /> Marquer présent
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
