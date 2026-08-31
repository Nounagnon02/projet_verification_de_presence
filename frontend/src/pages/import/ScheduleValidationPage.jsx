import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiChevronRight, FiSave, FiAlertCircle, FiInfo, FiZoomIn, FiCheck, FiLoader } from 'react-icons/fi';
import { MdAutoAwesome } from 'react-icons/md';
import api from '../../api/axios';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';

const DAY_NAMES = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

/**
 * Repère local des chevauchements, pour un premier signalement à l'écran.
 *
 * L'AUTORITÉ reste le serveur : /import/schedule/verifier applique les règles
 * académiques et les conflits contre la base, que le navigateur ne connaît pas.
 * Cette fonction ne fait que colorer la liste avant l'appel.
 *
 * Elle comparait « a.date !== b.date ». Sur des créneaux hebdomadaires, qui
 * n'ont pas de date, les deux valeurs valaient undefined : la comparaison des
 * heures se faisait donc TOUS JOURS CONFONDUS, et un cours du lundi entrait en
 * conflit avec un cours du mardi à la même heure.
 */
function detectConflicts(events) {
  const conflicts = new Set();
  const memeJour = (a, b) => {
    if (a.date && b.date) return a.date === b.date;
    if (a.jour_semaine && b.jour_semaine) return a.jour_semaine === b.jour_semaine;
    return false;
  };

  for (let i = 0; i < events.length; i++) {
    for (let j = i + 1; j < events.length; j++) {
      const a = events[i];
      const b = events[j];
      if (!memeJour(a, b)) continue;
      if (a.heure_debut < b.heure_fin && b.heure_debut < a.heure_fin) {
        conflicts.add(i);
        conflicts.add(j);
      }
    }
  }
  return conflicts;
}

/**
 * Lit l'analyse déposée en session par l'écran d'import.
 *
 * Fonction pure au niveau module : la lecture de sessionStorage est synchrone,
 * elle peut donc servir de valeur initiale d'état. La faire dans un effet
 * imposait un premier rendu à vide, suivi d'un second — visible sous la forme
 * d'un écran vide qui se remplit après coup.
 *
 * Renvoie null si aucune analyse exploitable n'est disponible.
 */
function lireAnalyseEnSession() {
  const stored = sessionStorage.getItem('import_analysis');

  if (!stored) return null;

  try {
    const parsed = JSON.parse(stored);

    // L'API d'état d'analyse répond { analysis_id, type, status, result }, où
    // « result » porte { events, courses, diagnostic }. La lecture ne regardait
    // que « data », jamais « result » : les créneaux n'étaient donc JAMAIS
    // trouvés, et l'écran affichait une liste vide quelle que soit la qualité de
    // l'extraction. CourseValidationPage, lui, regardait bien « result ».
    const root = parsed?.result || parsed?.data || parsed;

    const eventsData = root?.events
      || root?.data?.events
      || (Array.isArray(root?.data) ? root.data : [])
      || [];

    const events = Array.isArray(eventsData) ? eventsData : [];

    return {
      analyse: {
        ...parsed,
        events,
        // Diagnostic d'extraction : distingue un document vide d'un document
        // dont le contenu a été détecté mais pas compris. Sans lui, l'écran
        // affichait « aucun créneau » dans les deux cas.
        diagnostic: root?.diagnostic ?? root?.data?.diagnostic ?? null,
        score: parsed?.score_de_confiance ?? root?.score_de_confiance ?? root?.confidence ?? 0.9,
        filename: root?.metadata?.filename || 'Emploi du temps',
      },
      // Tout est sélectionné par défaut.
      selection: Object.fromEntries(events.map((_, i) => [i, true])),
    };
  } catch {
    return null;
  }
}

export default function ScheduleValidationPage() {
  const navigate = useNavigate();

  // Lecture une seule fois, à l'initialisation.
  const [initial] = useState(lireAnalyseEnSession);
  // Jamais réécrite après l'initialisation : une simple valeur suffit.
  const analysisData = initial?.analyse ?? null;
  const [selected, setSelected] = useState(() => initial?.selection ?? {});
  const [saving, setSaving] = useState(false);

  // Destination de l'import. L'ancien chemin n'en demandait aucune, alors que le
  // serveur l'exige : c'est l'une des raisons pour lesquelles il ne pouvait pas
  // aboutir.
  const filtres = useFiltresAcademiques({ preselectionnerAnneeActive: true });

  // Rapport ligne par ligne rendu par le serveur : statut et motif de chaque
  // créneau. Il remplace la devinette côté client, qui ne connaissait ni le
  // référentiel des EC ni l'état de la base.
  const [rapport, setRapport] = useState(null);
  const [ignorerRefuses, setIgnorerRefuses] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  // Seule la redirection reste un effet : c'est une action sur l'extérieur, pas
  // une écriture d'état.
  useEffect(() => {
    if (!initial) navigate('/schedules/weekly');
  }, [initial, navigate]);

  const events = useMemo(() => analysisData?.events || [], [analysisData]);
  const conflicts = useMemo(() => detectConflicts(events), [events]);

  const toggleAll = () => {
    const allSelected = Object.values(selected).every(Boolean);
    const newSelected = {};
    events.forEach((_, i) => { newSelected[i] = !allSelected; });
    setSelected(newSelected);
  };

  const toggleOne = (idx) => {
    setSelected((prev) => ({ ...prev, [idx]: !prev[idx] }));
  };

  // Un créneau hebdomadaire porte jour_semaine (1 = lundi … 7 = dimanche) et
  // aucune date. Ne savoir lire qu'une date affichait « — » sur toute la liste.
  const getDayName = (dateStr, jourSemaine) => {
    if (!dateStr) {
      return jourSemaine ? DAY_NAMES[jourSemaine % 7] : '—';
    }
    const d = new Date(dateStr + 'T12:00:00');
    return DAY_NAMES[d.getDay()] || '—';
  };

  const getStatusInfo = (event, idx) => {
    if (conflicts.has(idx)) {
      return { label: 'Conflit', color: 'bg-error-container text-on-error-container', icon: FiAlertCircle };
    }
    const missing = !event.heure_debut || !event.heure_fin || !event.salle;
    if (missing) {
      return { label: 'Incomplet', color: 'bg-tertiary-fixed text-on-tertiary-fixed', icon: FiAlertCircle };
    }
    return { label: 'Validé', color: 'bg-secondary-container text-on-secondary-container', icon: FiCheck };
  };

  const selectedCount = Object.values(selected).filter(Boolean).length;
  const conflictCount = conflicts.size;

  /**
   * Vérifie le lot auprès du serveur, SANS rien enregistrer.
   *
   * L'ancien code postait directement vers /import/validate-events, qui exige
   * ec_id, filiere_id et annee_id par événement. La page envoyait les créneaux
   * bruts — ec, date, heure — donc l'appel repartait en 422 à tous les coups :
   * l'import d'emploi du temps par IA n'a jamais pu aboutir.
   */
  const handleVerify = async () => {
    const toSave = events.filter((_, i) => selected[i]);

    if (toSave.length === 0) {
      setError('Sélectionnez au moins un créneau à importer.');
      return;
    }

    if (!filtres.filiere || !filtres.annee) {
      setError("Choisissez la filière et l'année académique de destination.");
      return;
    }

    setSaving(true);
    setError('');

    try {
      const { data: res } = await api.post('/admin/import/schedule/verifier', {
        creneaux: toSave,
        filiere_id: Number(filtres.filiere),
        annee_id: Number(filtres.annee),
      });

      setRapport(res.data ?? null);

      if (!res.success) {
        setError(res.message || 'La vérification a échoué.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur de connexion au serveur.');
    } finally {
      setSaving(false);
    }
  };

  /**
   * Enregistre après vérification. Le serveur REVALIDE : le rapport affiché
   * n'est pas une autorisation.
   */
  const handleSave = async () => {
    const toSave = events.filter((_, i) => selected[i]);

    setSaving(true);
    setError('');

    try {
      const { data: res } = await api.post('/admin/import/schedule/confirmer', {
        creneaux: toSave,
        filiere_id: Number(filtres.filiere),
        annee_id: Number(filtres.annee),
        ignorer_les_refuses: ignorerRefuses,
      });

      if (res.success) {
        setSaved(true);
        sessionStorage.setItem('import_events_result', JSON.stringify(res));
      } else {
        setRapport(res.data ?? null);
        setError(res.message || 'Enregistrement refusé.');
      }
    } catch (err) {
      setRapport(err.response?.data?.data ?? null);
      setError(err.response?.data?.message || 'Erreur de connexion au serveur.');
    } finally {
      setSaving(false);
    }
  };

  if (!analysisData) return null;

  if (saved) {
    return (
      <div className="max-w-lg mx-auto py-12">
        <div className="bg-surface-container-lowest rounded-xl p-8 shadow-sm text-center">
          <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <FiCheck className="text-secondary" size={32} />
          </div>
          <h1 className="text-2xl font-bold font-headline text-primary mb-3">Événements importés !</h1>
          {/* « événements » désignait autre chose : ce sont des CRÉNEAUX
              hebdomadaires, que la commande events:generate-from-schedule
              matérialisera ensuite en événements datés. Et le décompte affiché
              était celui des lignes SÉLECTIONNÉES, non des lignes réellement
              enregistrées — deux nombres qui diffèrent dès qu'une ligne est
              refusée. */}
          <p className="text-on-surface-variant mb-2">
            {rapport?.valides ?? selectedCount} créneau(x) ajouté(s) à l&apos;emploi du temps.
          </p>
          {conflictCount > 0 && (
            <p className="text-xs text-warning mb-8">{conflictCount} chevauchement(s) repéré(s) à l&apos;écran.</p>
          )}
          <div className="flex gap-3 justify-center">
            <button onClick={() => navigate('/schedules/weekly')}
              className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all">
              Voir l'emploi du temps
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div>
      {/* Breadcrumb & Header */}
      <div className="mb-8">
        <nav className="flex items-center gap-2 text-xs font-medium text-on-surface-variant mb-3 uppercase tracking-wider">
          <span>Emplois du temps</span>
          <FiChevronRight className="text-[14px]" />
          <span>Import IA</span>
          <FiChevronRight className="text-[14px]" />
          <span className="text-primary font-bold">Étape 3 : Validation</span>
        </nav>
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-3xl font-extrabold text-primary tracking-tight">Valider les événements</h1>
            <p className="text-on-surface-variant mt-1">Vérifiez les cours extraits avant de finaliser.</p>
          </div>
          <div className="flex items-center gap-1.5 px-3 py-1 bg-secondary-container/30 text-on-secondary-container rounded-full text-xs font-semibold">
            <MdAutoAwesome className="text-sm" />
            {Math.round((analysisData.score || 0) * 100)}% Confiance
          </div>
        </div>
      </div>

      <div className="flex flex-col lg:flex-row gap-8">
        {/* Main table */}
        <div className="flex-1 space-y-6">
          <div className="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-[0.1em] font-bold">
                  <th className="py-4 px-6 w-12 text-center">
                    <input
                      type="checkbox"
                      className="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                      checked={Object.values(selected).every(Boolean) && Object.keys(selected).length > 0}
                      onChange={toggleAll}
                    />
                  </th>
                  <th className="py-4 px-6">Cours</th>
                  <th className="py-4 px-6">Jour</th>
                  <th className="py-4 px-6">Horaire</th>
                  <th className="py-4 px-6">Salle</th>
                  <th className="py-4 px-6">Statut</th>
                  <th className="py-4 px-6 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-surface-container-low">
                {events.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="py-12 text-center text-on-surface-variant text-sm">
                      Aucun événement extrait du document.
                    </td>
                  </tr>
                ) : (
                  events.map((event, idx) => {
                    const status = getStatusInfo(event, idx);
                    const StatusIcon = status.icon;
                    const isConflict = conflicts.has(idx);
                    return (
                      <tr key={idx} className={`hover:bg-surface-bright transition-colors group ${isConflict ? 'bg-red-50/20' : ''}`}>
                        <td className="py-5 px-6 text-center">
                          <input
                            type="checkbox"
                            className="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4"
                            checked={!!selected[idx]}
                            onChange={() => toggleOne(idx)}
                            disabled={isConflict}
                          />
                        </td>
                        <td className="py-5 px-6">
                          <div className="flex flex-col">
                            <span className="font-semibold text-on-surface">{event.ec || event.cours || 'Cours sans nom'}</span>
                            {event.code && <span className="text-xs text-on-surface-variant font-mono">{event.code}</span>}
                          </div>
                        </td>
                        <td className="py-5 px-6">
                          <span className="text-sm font-medium">{getDayName(event.date, event.jour_semaine)}</span>
                          <span className="text-xs text-on-surface-variant block">{event.date || 'chaque semaine'}</span>
                        </td>
                        <td className="py-5 px-6">
                          <div className="flex items-center gap-2 font-mono text-sm text-primary">
                            <span>{event.heure_debut || '--:--'}</span>
                            <span className="w-2 h-[1px] bg-outline-variant"></span>
                            <span>{event.heure_fin || '--:--'}</span>
                          </div>
                        </td>
                        <td className="py-5 px-6">
                          <span className="text-sm px-2 py-0.5 bg-surface-container rounded font-medium font-mono">
                            {event.salle || 'N/A'}
                          </span>
                        </td>
                        <td className="py-5 px-6">
                          <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold ${status.color}`}>
                            <StatusIcon className="text-[14px]" />
                            {status.label}
                          </span>
                        </td>
                        {/* Un crayon d'edition figurait ici, sans aucun
                            gestionnaire. Il est retire plutot que masque : une
                            affordance qui promet une capacite inexistante est
                            pire que son absence. Cette page permet d'ACCEPTER ou
                            d'ECARTER une ligne extraite, pas de la corriger —
                            la correction reste a construire. */}
                        <td className="py-5 px-6" />
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Info alert */}
          <div className="bg-primary/5 rounded-xl p-6 flex items-start gap-4">
            <FiInfo className="text-primary mt-0.5 shrink-0" />
            <div>
              <p className="text-sm font-bold text-primary">Prêt à importer</p>
              <p className="text-sm text-on-surface-variant">
                {selectedCount} événement(s) sélectionné(s) pour l'import.
                {conflictCount > 0 && ` ${conflictCount} événement(s) en conflit ont été désélectionné(s).`}
              </p>
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <aside className="w-full lg:w-80 space-y-6">
          {/* Destination. L'ancien chemin n'en demandait aucune, alors que le
              serveur exige filiere_id et annee_id : l'appel repartait en 422. */}
          <div className="bg-surface-container-lowest rounded-xl p-5 shadow-sm space-y-4">
            <h3 className="font-bold text-primary text-sm">Destination</h3>

            <div className="space-y-1">
              <label htmlFor="edt-annee" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
              <select id="edt-annee" value={filtres.annee} onChange={(e) => { filtres.setAnnee(e.target.value); setRapport(null); }}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50">
                <option value="">Choisir une année…</option>
                {filtres.annees.map((a) => (
                  <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (active)' : ''}</option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <label htmlFor="edt-filiere" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
              <select id="edt-filiere" value={filtres.filiere} onChange={(e) => { filtres.setFiliere(e.target.value); setRapport(null); }}
                disabled={!filtres.annee} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50">
                <option value="">Choisir une filière…</option>
                {filtres.filieres.map((f) => (
                  <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>
                ))}
              </select>
            </div>

            <button onClick={handleVerify} disabled={saving || !filtres.filiere || !filtres.annee}
              className="w-full py-2.5 rounded-lg bg-surface-container-high text-on-surface font-bold text-sm hover:bg-surface-container transition-colors disabled:opacity-50">
              {saving ? 'Vérification…' : 'Vérifier sans enregistrer'}
            </button>
          </div>

          {/* Rapport du serveur. Il remplace la devinette côté client, qui ne
              connaissait ni le référentiel des EC ni l'état de la base. */}
          {rapport && (
            <div className="bg-surface-container-lowest rounded-xl p-5 shadow-sm space-y-3">
              <h3 className="font-bold text-primary text-sm">Rapport de vérification</h3>

              <p className="text-xs text-on-surface-variant">
                {rapport.valides ?? 0} créneau(x) sur {rapport.total ?? 0} sont enregistrables.
              </p>

              <ul className="space-y-2 max-h-64 overflow-y-auto">
                {(rapport.lignes ?? []).filter((l) => l.statut !== 'valide').map((l) => (
                  <li key={l.rang} className="text-xs p-2 rounded-lg bg-error-container/20 text-on-error-container">
                    <span className="font-bold">Ligne {l.rang} — {l.statut}</span>
                    <span className="block mt-0.5">{l.motifs.join(' ')}</span>
                  </li>
                ))}
              </ul>

              {(rapport.valides ?? 0) < (rapport.total ?? 0) && (
                <label className="flex items-start gap-2 text-xs text-on-surface-variant cursor-pointer">
                  <input type="checkbox" checked={ignorerRefuses} onChange={(e) => setIgnorerRefuses(e.target.checked)}
                    className="mt-0.5" />
                  <span>
                    N&apos;enregistrer que les {rapport.valides ?? 0} ligne(s) valide(s) et ignorer les autres.
                    Sans cette case, rien ne sera écrit tant qu&apos;une ligne est refusée.
                  </span>
                </label>
              )}
            </div>
          )}

          <div className="bg-surface-container-low rounded-xl p-1 shadow-sm">
            <div className="bg-surface-container-lowest rounded-lg p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-bold text-primary flex items-center gap-2 text-sm">
                  Document Source
                </h3>
              </div>
              <div className="relative rounded-lg overflow-hidden border border-outline-variant/10 bg-surface-container-high h-48 flex items-center justify-center">
                <div className="text-center p-4">
                  <FiZoomIn className="text-3xl text-on-surface-variant/40 mx-auto mb-2" />
                  <p className="text-xs text-on-surface-variant font-medium">{analysisData.filename}</p>
                  <p className="text-[10px] text-on-surface-variant/60 mt-1">Analysé par IA</p>
                </div>
              </div>
              <div className="mt-6 space-y-4">
                <h4 className="text-[10px] uppercase tracking-widest font-bold text-on-surface-variant">Stats d'extraction</h4>
                <div className="grid grid-cols-2 gap-4">
                  <div className="bg-surface-container-low p-3 rounded-lg">
                    <p className="text-[10px] text-on-surface-variant font-medium">Événements</p>
                    <p className="text-xl font-bold text-primary font-mono">{events.length}</p>
                  </div>
                  <div className="bg-surface-container-low p-3 rounded-lg">
                    <p className="text-[10px] text-on-surface-variant font-medium">Confiance</p>
                    <p className="text-xl font-bold text-secondary font-mono">{Math.round((analysisData.score || 0) * 100)}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="bg-surface-container-high/50 rounded-xl p-5 border border-outline-variant/10">
            <p className="text-xs font-medium text-on-surface-variant leading-relaxed">
              <FiInfo className="text-xs inline mr-1" />
              Les événements en conflit (même jour et heure) sont automatiquement désélectionnés.
            </p>
          </div>
        </aside>
      </div>

      {error && (
        <div className="mt-6 flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertCircle /> {error}
        </div>
      )}

      {/* Bottom bar */}
      <footer className="mt-8 bg-surface-container-lowest border border-outline-variant/10 rounded-xl p-6">
        <div className="flex items-center justify-between">
          <div className="flex flex-col">
            <span className="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Emploi du temps</span>
            <span className="text-sm font-semibold text-primary">{selectedCount} événement(s) sélectionné(s)</span>
          </div>
          <div className="flex items-center gap-4">
            <button onClick={() => navigate('/schedules/weekly')}
              className="px-6 py-2.5 rounded-lg font-bold text-sm text-on-surface hover:bg-surface-container transition-all active:scale-95">
              Annuler
            </button>
            <button onClick={handleSave}
              disabled={saving || selectedCount === 0 || !filtres.filiere || !filtres.annee || rapport === null}
              className="px-8 py-2.5 rounded-lg font-bold text-sm text-white bg-gradient-to-br from-primary to-primary-container shadow-md hover:shadow-lg transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2">
              <FiSave className="text-sm" />
              {saving ? <><FiLoader className="animate-spin" /> Enregistrement...</> : 'Valider et enregistrer'}
            </button>
          </div>
        </div>
      </footer>
    </div>
  );
}
