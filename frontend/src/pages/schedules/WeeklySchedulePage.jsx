import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiChevronLeft, FiChevronRight, FiMapPin, FiLoader, FiUpload, FiPlus, FiFileText, FiX, FiAlertTriangle } from 'react-icons/fi';
import api from '../../api/axios';

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const HOURS = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'];

const COLORS = [
  'bg-[#E3F2FD] border-l-4 border-[#1565C0] text-[#1565C0]',
  'bg-[#E8F5E9] border-l-4 border-[#2E7D32] text-[#2E7D32]',
  'bg-[#FFF8E1] border-l-4 border-[#F57F17] text-[#F57F17]',
  'bg-[#F3E5F5] border-l-4 border-[#7B1FA2] text-[#7B1FA2]',
  'bg-[#FFEBEE] border-l-4 border-[#C62828] text-[#C62828]',
  'bg-[#E0F7FA] border-l-4 border-[#00838F] text-[#00838F]',
];

function getWeekDateRange(offset) {
  const now = new Date();
  const day = now.getDay();
  const diff = now.getDate() - day + (day === 0 ? -6 : 1) + offset * 7;
  const monday = new Date(now.setDate(diff));
  const sunday = new Date(new Date(monday).setDate(monday.getDate() + 6));
  return {
    start: monday.toISOString().split('T')[0],
    end: sunday.toISOString().split('T')[0],
    dates: DAYS.map((_, i) => {
      const d = new Date(monday);
      d.setDate(monday.getDate() + i);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
    }),
  };
}

function getHourIndex(time) {
  const h = parseInt(time?.split(':')[0] || '8', 10);
  return h - 8;
}

export default function WeeklySchedulePage() {
  const navigate = useNavigate();
  const [weekOffset, setWeekOffset] = useState(0);
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const weekRange = getWeekDateRange(weekOffset);

  // Filtres
  const [filtreAnnee, setFiltreAnnee] = useState('');
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [filtreSemestre, setFiltreSemestre] = useState('');

  // Import EDT
  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importDragOver, setImportDragOver] = useState(false);
  const [importUploading, setImportUploading] = useState(false);
  const [importError, setImportError] = useState('');
  const importFileRef = useRef(null);

  // Charger les options de filtres
  useEffect(() => {
    (async () => {
      try {
        const [filRes, annRes] = await Promise.all([
          api.get('/admin/filieres'),
          api.get('/admin/annees-academiques'),
        ]);
        setFilieres(filRes.data?.data ?? filRes.data ?? []);
        setAnnees(annRes.data?.data ?? annRes.data ?? []);
      } catch { /* silencieux */ }
    })();
  }, []);

  useEffect(() => {
    const fetchEvents = async () => {
      setLoading(true);
      try {
        const params = { date_debut: weekRange.start, date_fin: weekRange.end };
        if (filtreAnnee) params.annee_id = filtreAnnee;
        if (filtreFiliere) params.filiere_id = filtreFiliere;
        if (filtreSemestre) params.semestre = filtreSemestre;
        const { data: res } = await api.get('/admin/evenements', { params });
        const list = res.data || res;
        setEvents(Array.isArray(list) ? list : []);
      } catch {
        setEvents([]);
      } finally {
        setLoading(false);
      }
    };
    fetchEvents();
  }, [weekOffset, filtreAnnee, filtreFiliere, filtreSemestre]);

  const handleImportDrop = (e) => {
    e.preventDefault();
    setImportDragOver(false);
    const f = e.dataTransfer.files[0];
    if (f && (f.type === 'application/pdf' || f.name.endsWith('.pdf'))) {
      setImportFile(f);
      setImportError('');
    } else {
      setImportError('Veuillez sélectionner un fichier PDF.');
    }
  };

  const handleImportUpload = async () => {
    if (!importFile) return;
    setImportUploading(true);
    setImportError('');
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const { data } = await api.post('/admin/import/schedule', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const analysisId = data?.data?.id || data?.analysis_id;
      if (analysisId) {
        sessionStorage.setItem('analysis_id', analysisId);
        sessionStorage.setItem('import_type', 'schedule');
      }
      navigate('/import/ai-analysis');
    } catch (err) {
      setImportError(err.response?.data?.message || "Erreur lors de l'import du fichier.");
      setImportUploading(false);
    }
  };

  const resetImport = () => {
    setImportFile(null);
    setImportError('');
    setImportUploading(false);
  };

  const mappedEvents = events.map((e, i) => {
    const date = new Date(e.date + 'T12:00:00');
    const dayIdx = (date.getDay() + 6) % 7;
    const startHour = getHourIndex(e.heure_debut);
    const endHour = getHourIndex(e.heure_fin) || startHour + 1;
    return {
      day: dayIdx,
      start: startHour,
      end: endHour,
      title: e.ec?.intitule || e.cours || 'Cours',
      room: e.salle || 'N/A',
      color: i % COLORS.length,
    };
  });

  return (
    <div>
      {/* Titre */}
      <div className="flex items-center justify-between flex-wrap gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Emploi du Temps</h1>
          <p className="text-sm text-on-surface-variant">Planning hebdomadaire des cours</p>
        </div>
      </div>

      {/* Filtres + actions */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <div className="space-y-1 flex-1 min-w-[180px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
            <select value={filtreAnnee} onChange={(e) => setFiltreAnnee(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes les années</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle || a.annee}{a.active ? ' (Active)' : ''}</option>)}
            </select>
          </div>
          <div className="space-y-1 flex-1 min-w-[180px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
            <select value={filtreFiliere} onChange={(e) => setFiltreFiliere(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes les filières</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
            </select>
          </div>
          <div className="space-y-1 flex-1 min-w-[140px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Semestre</label>
            <select value={filtreSemestre} onChange={(e) => setFiltreSemestre(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Tous les semestres</option>
              {[1,2,3,4,5,6].map(s => <option key={s} value={s}>Semestre {s}</option>)}
            </select>
          </div>
          <div className="flex items-center gap-2 self-end pt-1">
            <button onClick={() => navigate('/schedules/events')}
              className="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
              <FiPlus size={15} /> Ajouter un cours
            </button>
            <button onClick={() => setShowImportModal(true)}
              className="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl font-bold text-sm border border-outline-variant/20 hover:bg-surface-container-low transition-all">
              <FiUpload size={15} /> Importer un EDT
            </button>
          </div>
        </div>
      </div>

      {/* Navigation semaine */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <button onClick={() => setWeekOffset(w => w - 1)} className="p-2 hover:bg-surface-container-high rounded-xl transition-colors">
            <FiChevronLeft />
          </button>
          <span className="text-sm font-semibold text-primary min-w-[140px] text-center">
            {weekOffset === 0 ? 'Cette semaine' : weekOffset === -1 ? 'Semaine dernière' : `S+${Math.abs(weekOffset)}`}
          </span>
          <button onClick={() => setWeekOffset(w => w + 1)} className="p-2 hover:bg-surface-container-high rounded-xl transition-colors">
            <FiChevronRight />
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <FiLoader className="animate-spin text-primary w-8 h-8" />
        </div>
      ) : (
        <div className="bg-surface-container-lowest rounded-xxl shadow-sm overflow-hidden">
          <div className="grid grid-cols-[80px_repeat(6,1fr)] border-b border-outline-variant/10">
            <div className="p-3 text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider"></div>
            {DAYS.map((day, i) => (
              <div key={i} className="p-3 text-center">
                <p className="text-xs font-bold text-primary">{day}</p>
                <p className="text-[10px] text-on-surface-variant">{weekRange.dates[i]}</p>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-[80px_repeat(6,1fr)] relative">
            <div>
              {HOURS.map((hour, i) => (
                <div key={i} className="h-[60px] border-b border-outline-variant/5 flex items-start justify-end pr-3 pt-1">
                  <span className="text-[10px] text-on-surface-variant font-mono">{hour}</span>
                </div>
              ))}
            </div>

            {DAYS.map((_, dayIdx) => (
              <div key={dayIdx} className="relative border-l border-outline-variant/5">
                {HOURS.map((_, hourIdx) => (
                  <div key={hourIdx} className="h-[60px] border-b border-outline-variant/5"></div>
                ))}

                {mappedEvents.filter(e => e.day === dayIdx).map((event, i) => (
                  <div
                    key={i}
                    className={`absolute left-0.5 right-0.5 ${COLORS[event.color]} rounded-lg p-2 overflow-hidden cursor-pointer hover:shadow-md transition-shadow z-10`}
                    style={{
                      top: `${(event.start / HOURS.length) * 100}%`,
                      height: `${((event.end - event.start) / HOURS.length) * 100}%`,
                    }}
                  >
                    <p className="text-[11px] font-bold leading-tight mb-0.5">{event.title}</p>
                    <p className="text-[9px] flex items-center gap-1 opacity-80">
                      <FiMapPin size={9} /> {event.room}
                    </p>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ─── Modal Import EDT ─────────────────────────────── */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => { if (!importUploading) { setShowImportModal(false); resetImport(); } }}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-bold text-primary">Importer un emploi du temps</h2>
              <button onClick={() => { setShowImportModal(false); resetImport(); }} disabled={importUploading}
                className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-outline" />
              </button>
            </div>

            {/* Drop zone */}
            <div onDragOver={(e) => { e.preventDefault(); setImportDragOver(true); }} onDragLeave={() => setImportDragOver(false)} onDrop={handleImportDrop}
              className={`border-2 border-dashed rounded-xl p-10 text-center transition-all cursor-pointer ${importDragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${importFile ? 'bg-surface-container-low' : ''}`}
              onClick={() => importFileRef.current?.click()}>
              <input ref={importFileRef} type="file" accept=".pdf" className="hidden" onChange={(e) => {
                const f = e.target.files[0]; if (f) { setImportFile(f); setImportError(''); }
              }} />
              {!importFile ? (
                <>
                  <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <FiUpload className="text-2xl text-primary" />
                  </div>
                  <h3 className="text-sm font-semibold text-on-surface mb-1">Importez un fichier PDF</h3>
                  <p className="text-xs text-on-surface-variant mb-4">Analyse par IA Gemini — ou <span className="text-primary font-semibold cursor-pointer hover:underline">parcourez</span></p>
                  <p className="text-[10px] text-on-surface-variant/60">PDF uniquement — 10 Mo max</p>
                </>
              ) : (
                <div className="flex items-center gap-4 justify-center">
                  <FiFileText className="text-2xl text-primary" />
                  <div className="text-left">
                    <p className="text-sm font-medium text-on-surface">{importFile.name}</p>
                    <p className="text-[10px] text-on-surface-variant">{(importFile.size / 1024).toFixed(1)} Ko</p>
                  </div>
                  <button onClick={(e) => { e.stopPropagation(); resetImport(); }} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors">
                    <FiX className="text-outline" />
                  </button>
                </div>
              )}
            </div>

            {/* Erreur */}
            {importError && (
              <div className="mt-4 flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
                <FiAlertTriangle /> {importError}
              </div>
            )}

            {/* Upload */}
            {importFile && !importUploading && (
              <button onClick={handleImportUpload}
                className="mt-6 w-full flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
                <FiUpload /> Analyser avec l'IA
              </button>
            )}

            {importUploading && (
              <div className="mt-6 bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10 text-center">
                <FiLoader className="animate-spin mx-auto text-primary text-2xl mb-3" />
                <p className="font-semibold text-primary text-sm">Analyse IA en cours...</p>
                <p className="text-xs text-on-surface-variant mt-1">Redirection vers la page d'analyse</p>
              </div>
            )}

            <div className="mt-6 bg-surface-container-high rounded-xl p-4">
              <h4 className="text-xs font-bold text-primary mb-2">Informations</h4>
              <p className="text-[11px] text-on-surface-variant">Le fichier PDF sera analysé par l'IA Gemini pour extraire les créneaux. Vous pourrez valider les données avant l'import final.</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
