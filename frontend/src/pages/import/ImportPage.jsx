import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiUpload, FiCheck, FiAlertTriangle, FiTrash2, FiLoader, FiInfo, FiFileText } from 'react-icons/fi';
import { MdPictureAsPdf, MdDescription, MdSchool } from 'react-icons/md';
import api from '../../api/axios';

const STEP_UPLOAD = 0;
const STEP_ANALYSE = 1;
const STEP_RESULTAT = 2;

export default function ImportPage() {
  const [tab, setTab] = useState('students');
  const [file, setFile] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [step, setStep] = useState(STEP_UPLOAD);
  const fileRef = useRef(null);
  const navigate = useNavigate();

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    const f = e.dataTransfer.files[0];
    if (!f) return;
    const ext = '.' + f.name.split('.').pop().toLowerCase();
    if (tab === 'students') {
      if (ext === '.csv' || ext === '.xlsx') {
        setFile(f); setError(''); setResult(null); setStep(STEP_UPLOAD);
      } else setError('Format non supporté. Utilisez CSV ou XLSX.');
    } else {
      if (ext === '.pdf') {
        setFile(f); setError(''); setResult(null); setStep(STEP_UPLOAD);
      } else setError('Format non supporté. Utilisez PDF.');
    }
  };

  const handleImportStudents = async () => {
    if (!file) return;
    setUploading(true);
    setError('');
    setResult(null);
    setStep(STEP_ANALYSE);
    const formData = new FormData();
    formData.append('file', file);
    try {
      const response = await api.post('/admin/import/students', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      // Réponse API: { success, message, data: { success: N, errors: [...], total: N } }
      const apiData = response.data;
      console.log('[Import] API response:', apiData);

      if (apiData.success && apiData.data) {
        setResult({
          success: true,
          imported: apiData.data.success ?? 0,
          total: apiData.data.total ?? 0,
          errors: apiData.data.errors || [],
        });
      } else if (apiData.data && typeof apiData.data.success === 'number') {
        // L'API a répondu mais les données sont dans apiData.data directement
        setResult({
          success: apiData.data.success > 0,
          imported: apiData.data.success ?? 0,
          total: apiData.data.total ?? 0,
          errors: apiData.data.errors || [],
        });
      } else {
        // Format de réponse inattendu
        const errMsg = apiData.message || JSON.stringify(apiData);
        setResult({
          success: false,
          imported: 0,
          total: 0,
          errors: [errMsg],
        });
      }
      setStep(STEP_RESULTAT);
    } catch (err) {
      console.error('[Import] API error:', err.response || err);
      const message = err.response?.data?.message
        || (err.response?.data?.errors ? JSON.stringify(err.response.data.errors) : null)
        || err.message
        || 'Erreur lors de l\'import';
      setError(message);
      setResult(null);
      setStep(STEP_UPLOAD);
    } finally {
      setUploading(false);
    }
  };

  const handleAnalyzeSchedule = async () => {
    if (!file) return;
    setUploading(true);
    setError('');
    const formData = new FormData();
    formData.append('file', file);
    try {
      const { data } = await api.post('/admin/import/schedule', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      sessionStorage.setItem('import_analysis', JSON.stringify(data));
      navigate('/import/ai-analysis');
    } catch (err) {
      const message = err.response?.data?.message || 'Erreur lors de l\'analyse de l\'emploi du temps.';
      setError(message);
    } finally {
      setUploading(false);
    }
  };

  const handleAnalyzeCourses = async () => {
    if (!file) return;
    setUploading(true);
    setError('');
    const formData = new FormData();
    formData.append('file', file);
    try {
      const { data } = await api.post('/admin/import/courses', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      sessionStorage.setItem('import_courses_analysis', JSON.stringify(data));
      navigate('/import/validate-courses');
    } catch (err) {
      const message = err.response?.data?.message || 'Erreur lors de l\'analyse des cours.';
      setError(message);
    } finally {
      setUploading(false);
    }
  };

  const resetAll = () => { setFile(null); setResult(null); setError(''); setStep(STEP_UPLOAD); if (fileRef.current) fileRef.current.value = ''; };
  const clearFile = () => { resetAll(); };
  const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
  };

  const handleAction = () => {
    if (tab === 'students') handleImportStudents();
    else if (tab === 'schedule') handleAnalyzeSchedule();
    else handleAnalyzeCourses();
  };

  // --- Stepper pour étudiants ---
  const studentSteps = [
    { num: 1, label: 'Upload', key: 'upload' },
    { num: 2, label: 'Analyse & Validation', key: 'analyse' },
    { num: 3, label: 'Résultat', key: 'resultat' },
  ];

  const getCurrentStepIdx = () => {
    if (tab !== 'students') return -1;
    if (step === STEP_RESULTAT && result) return 2;
    if (step === STEP_ANALYSE || uploading) return 1;
    return 0;
  };

  const renderStepper = (steps, activeIdx) => (
    <div className="flex items-center justify-between w-full mb-8 relative">
      <div className="absolute top-1/2 left-0 w-full h-[2px] bg-surface-container-high -z-10 -translate-y-1/2"></div>
      {steps.map((s, i) => {
        const isActive = i === activeIdx;
        const isDone = i < activeIdx;
        return (
          <div key={s.key} className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className={`w-10 h-10 rounded-full flex items-center justify-center font-bold transition-all ${
              isDone
                ? 'bg-secondary text-white ring-4 ring-secondary/20'
                : isActive
                  ? 'bg-primary text-white ring-4 ring-primary-fixed animate-pulse'
                  : 'bg-surface-container-high text-outline'
            }`}>
              {isDone ? <FiCheck size={18} /> : s.num}
            </div>
            <span className={`text-xs font-medium whitespace-nowrap ${
              isDone ? 'text-secondary font-bold' : isActive ? 'text-primary font-bold' : 'text-outline'
            }`}>
              {s.label}
            </span>
          </div>
        );
      })}
    </div>
  );

  return (
    <div className="max-w-2xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Importation</h1>
        <p className="text-sm text-on-surface-variant">Importez des données via CSV ou analyse IA</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-8 bg-surface-container-high rounded-xl p-1 w-fit">
        <button onClick={() => { setTab('students'); resetAll(); }}
          className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all ${tab === 'students' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
          Import Étudiants
        </button>
        <button onClick={() => { setTab('schedule'); resetAll(); }}
          className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all ${tab === 'schedule' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
          Emploi du temps (IA)
        </button>
        <button onClick={() => { setTab('courses'); resetAll(); }}
          className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all ${tab === 'courses' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
          Catalogue Cours (IA)
        </button>
      </div>

      {/* Stepper for students */}
      {tab === 'students' && renderStepper(studentSteps, getCurrentStepIdx())}

      {/* Stepper for schedule */}
      {tab === 'schedule' && (
        <div className="flex items-center justify-between w-full mb-8 relative">
          <div className="absolute top-1/2 left-0 w-full h-[2px] bg-surface-container-high -z-10 -translate-y-1/2"></div>
          <div className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold ring-4 ring-primary-fixed">1</div>
            <span className="text-xs font-bold text-primary">Upload</span>
          </div>
          <div className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-outline font-bold">2</div>
            <span className="text-xs font-medium text-outline">Analyse IA</span>
          </div>
          <div className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-outline font-bold">3</div>
            <span className="text-xs font-medium text-outline">Validation</span>
          </div>
        </div>
      )}

      {/* Stepper for courses */}
      {tab === 'courses' && (
        <div className="flex items-center justify-between w-full mb-8 relative">
          <div className="absolute top-1/2 left-0 w-full h-[2px] bg-surface-container-high -z-10 -translate-y-1/2"></div>
          <div className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold ring-4 ring-primary-fixed">1</div>
            <span className="text-xs font-bold text-primary">Upload</span>
          </div>
          <div className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-outline font-bold">2</div>
            <span className="text-xs font-medium text-outline">Analyse IA</span>
          </div>
          <div className="flex flex-col items-center gap-2 bg-surface px-2">
            <div className="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-outline font-bold">3</div>
            <span className="text-xs font-medium text-outline">Validation</span>
          </div>
        </div>
      )}

      {/* Drop zone */}
      <div onDragOver={(e) => { e.preventDefault(); setDragOver(true); }} onDragLeave={() => setDragOver(false)} onDrop={handleDrop}
        className={`border-2 border-dashed rounded-xl p-12 text-center transition-all cursor-pointer ${dragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${file ? 'bg-surface-container-low' : ''}`}
        onClick={() => fileRef.current?.click()}>
        <input ref={fileRef} type="file" accept={tab === 'students' ? '.csv,.xlsx' : '.pdf'} className="hidden" onChange={(e) => {
          const f = e.target.files[0];
          if (f) { setFile(f); setError(''); setResult(null); setStep(STEP_UPLOAD); }
        }} />

        {!file ? (
          <>
            <div className={`w-20 h-20 ${tab === 'students' ? 'bg-primary/10' : 'bg-primary-fixed'} rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm`}>
              {tab === 'students' ? <FiUpload className="text-3xl text-primary" /> : tab === 'schedule' ? <MdPictureAsPdf className="text-4xl text-primary" /> : <MdSchool className="text-4xl text-primary" />}
            </div>
            <h3 className="text-lg font-semibold text-on-surface mb-2">
              {tab === 'students' ? 'Importez votre fichier étudiants' : tab === 'schedule' ? 'Glissez votre emploi du temps PDF ici' : 'Glissez votre catalogue de cours PDF ici'}
            </h3>
            <p className="text-sm text-on-surface-variant mb-6">
              ou <span className="text-primary font-bold cursor-pointer hover:underline">parcourez vos fichiers</span>
            </p>
            {(tab === 'schedule' || tab === 'courses') && (
              <div className="flex items-center justify-center gap-4">
                <div className="flex items-center gap-2 bg-surface-container-lowest px-3 py-1.5 rounded-lg text-xs font-medium text-outline shadow-sm">
                  <MdPictureAsPdf className="text-sm" /> Format PDF uniquement
                </div>
                <div className="flex items-center gap-2 bg-surface-container-lowest px-3 py-1.5 rounded-lg text-xs font-medium text-outline shadow-sm">
                  <FiInfo className="text-sm" /> Max 10 Mo
                </div>
              </div>
            )}
            {tab === 'students' && <p className="text-[10px] text-on-surface-variant/60 mt-2">CSV ou XLSX - 5 Mo max</p>}
          </>
        ) : (
          <div className="flex items-center gap-4 justify-center">
            <MdDescription className="text-2xl text-primary" />
            <div className="text-left">
              <p className="text-sm font-medium text-on-surface">{file.name}</p>
              <p className="text-[10px] text-on-surface-variant">{formatSize(file.size)}</p>
            </div>
            <button onClick={(e) => { e.stopPropagation(); clearFile(); }} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors">
              <FiTrash2 className="text-outline" />
            </button>
          </div>
        )}
      </div>

      {error && !result && (
        <div className="mt-4 flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle /> {error}
        </div>
      )}

      {/* Result for students import */}
      {result && (
        <div className={`mt-4 p-4 rounded-xl flex items-start gap-3 ${
          result.success ? 'bg-secondary-container/30 border border-secondary/10' : 'bg-error-container/30 border border-error/10'
        }`}>
          {result.success
            ? <FiCheck className="text-secondary text-lg mt-0.5 flex-shrink-0" />
            : <FiAlertTriangle className="text-error text-lg mt-0.5 flex-shrink-0" />
          }
          <div className="text-sm flex-1">
            <p className="font-semibold">
              {result.success ? 'Import terminé avec succès' : 'Erreurs lors de l\'import'}
            </p>
            <p className="text-on-surface-variant text-xs mt-1">
              {result.imported}/{result.total} étudiants importés
              {result.errors?.length > 0 && ` (${result.errors.length} erreur${result.errors.length > 1 ? 's' : ''})`}
            </p>
            {result.errors?.length > 0 && Array.isArray(result.errors) && (
              <div className="mt-3 space-y-1 max-h-32 overflow-y-auto">
                {result.errors.map((e, i) => (
                  <p key={i} className="text-xs text-on-error-container/70 bg-error-container/20 p-1.5 rounded">
                    {typeof e === 'string' ? e : `${e.row || 'Ligne ' + (i+1)} : ${Array.isArray(e.errors) ? e.errors.join(', ') : e.errors || 'Erreur'}`}
                  </p>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Action button */}
      {file && !result && !uploading && (
        <button
          onClick={handleAction}
          className="mt-6 w-full flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
          {tab === 'students' ? 'Importer les étudiants' : 'Continuer'}
        </button>
      )}

      {/* Pendant l'import */}
      {uploading && (
        <div className="mt-8 bg-surface-container-lowest rounded-xl p-8 shadow-sm border border-outline-variant/10 text-center">
          <FiLoader className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="font-semibold text-primary">
            {tab === 'students' ? 'Analyse du fichier en cours...' : 'Analyse IA en cours...'}
          </p>
          <p className="text-sm text-on-surface-variant mt-1">Veuillez patienter</p>
        </div>
      )}

      {/* Info card */}
      <div className="mt-8 bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10">
        <h3 className="text-sm font-bold text-primary mb-2">Format attendu</h3>
        {tab === 'students' ? (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Colonnes requises : <span className="font-mono font-medium text-primary">nom, prenom, email, matricule, filiere_code, annee_libelle</span></p>
            <p className="mt-2">Fichier CSV avec séparateur virgule. Encodage UTF-8.</p>
            <div className="mt-3 flex items-center gap-2 text-primary">
              <FiFileText size={14} />
              <span className="font-semibold">Étapes de l'import :</span>
            </div>
            <ol className="list-decimal list-inside space-y-1 ml-1 mt-1">
              <li><span className="font-medium">Upload</span> — Sélection et envoi du fichier CSV</li>
              <li><span className="font-medium">Analyse &amp; Validation</span> — Vérification des données ligne par ligne</li>
              <li><span className="font-medium">Résultat</span> — Récapitulatif des importations réussies et des erreurs</li>
            </ol>
          </div>
        ) : tab === 'schedule' ? (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Importez votre emploi du temps au format PDF.</p>
            <p>L'IA Gemini analysera le document et extraira automatiquement :</p>
            <ul className="list-disc list-inside mt-2 space-y-1">
              <li>Les cours et leurs horaires</li>
              <li>Les salles et créneaux</li>
              <li>Les conflits potentiels</li>
            </ul>
          </div>
        ) : (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Importez votre catalogue de cours (maquette) au format PDF.</p>
            <p>L'IA Gemini extraira automatiquement :</p>
            <ul className="list-disc list-inside mt-2 space-y-1">
              <li>Les Unités d'Enseignement (UE) avec codes et crédits</li>
              <li>Les Éléments Constitutifs (EC) avec volumes horaires</li>
              <li>La répartition par semestre</li>
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
