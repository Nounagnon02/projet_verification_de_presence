import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiChevronRight, FiEdit, FiSave, FiAlertCircle, FiInfo, FiZoomIn, FiCheck, FiLoader } from 'react-icons/fi';
import { MdAutoAwesome } from 'react-icons/md';
import api from '../../api/axios';

const DAY_NAMES = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

function detectConflicts(events) {
  const conflicts = new Set();
  for (let i = 0; i < events.length; i++) {
    for (let j = i + 1; j < events.length; j++) {
      const a = events[i];
      const b = events[j];
      if (a.date !== b.date) continue;
      if (a.heure_debut < b.heure_fin && b.heure_debut < a.heure_fin) {
        conflicts.add(i);
        conflicts.add(j);
      }
    }
  }
  return conflicts;
}

export default function ScheduleValidationPage() {
  const navigate = useNavigate();
  const [analysisData, setAnalysisData] = useState(null);
  const [selected, setSelected] = useState({});
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const stored = sessionStorage.getItem('import_analysis');
    if (!stored) {
      navigate('/import');
      return;
    }
    try {
      const parsed = JSON.parse(stored);
      const root = parsed?.data || parsed;
      // Nouveau format: data.data.events; ancien: data.data (array)
      const eventsData = root?.data?.events || (Array.isArray(root?.data) ? root?.data : []);
      setAnalysisData({
        ...parsed,
        events: Array.isArray(eventsData) ? eventsData : [],
        score: root?.score_de_confiance ?? 0.9,
        filename: root?.metadata?.filename || 'Emploi du temps',
      });
      // Select all by default
      const initSelected = {};
      (Array.isArray(eventsData) ? eventsData : []).forEach((_, i) => { initSelected[i] = true; });
      setSelected(initSelected);
    } catch {
      navigate('/import');
    }
  }, [navigate]);

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

  const getDayName = (dateStr) => {
    if (!dateStr) return '—';
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

  const handleSave = async () => {
    const toSave = events.filter((_, i) => selected[i]);
    if (toSave.length === 0) {
      setError('Sélectionnez au moins un événement à importer.');
      return;
    }

    setSaving(true);
    setError('');

    try {
      // Envoyer les événements bruts au backend pour création
      const { data: res } = await api.post('/admin/import/validate-events', { events: toSave });
      if (res.success) {
        setSaved(true);
        // Stocker le résultat pour la page suivante
        sessionStorage.setItem('import_events_result', JSON.stringify(res));
      } else {
        setError(res.message || 'Erreur lors de la sauvegarde.');
      }
    } catch (err) {
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
          <p className="text-on-surface-variant mb-2">{selectedCount} événement(s) créé(s) avec succès.</p>
          {conflictCount > 0 && (
            <p className="text-xs text-warning mb-8">{conflictCount} conflit(s) ont été exclus.</p>
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
                          <span className="text-sm font-medium">{getDayName(event.date)}</span>
                          <span className="text-xs text-on-surface-variant block">{event.date || '—'}</span>
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
                        <td className="py-5 px-6 text-right">
                          <button className="p-2 text-on-surface-variant hover:text-primary transition-colors opacity-0 group-hover:opacity-100">
                            <FiEdit className="text-sm" />
                          </button>
                        </td>
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
            <button onClick={() => navigate('/import')}
              className="px-6 py-2.5 rounded-lg font-bold text-sm text-on-surface hover:bg-surface-container transition-all active:scale-95">
              Annuler
            </button>
            <button onClick={handleSave} disabled={saving || selectedCount === 0}
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
