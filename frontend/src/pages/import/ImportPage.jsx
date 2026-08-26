import { useState, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FiUpload, FiCheck, FiAlertTriangle, FiTrash2, FiLoader, FiInfo } from 'react-icons/fi';
import { MdPictureAsPdf, MdDescription, MdSchool } from 'react-icons/md';
import api from '../../api/axios';
import CsvTemplateDownload from '../../components/import/CsvTemplateDownload';

const STEP_UPLOAD = 0;
const STEP_RESULTAT = 2;

/**
 * Correspondance entre le segment d'URL et le type d'import.
 *
 * Les segments sont en francais et lisibles : l'URL d'un onglet est partageable,
 * et c'est elle qui porte desormais l'onglet actif — un etat local ne survivait
 * pas a un rechargement.
 */
const TYPES = {
  'cours-csv': 'csv-courses',
  'edt-csv':   'csv-schedule',
  'edt-ia':    'schedule',
  'cours-ia':  'courses',
};

/**
 * Enrobage de route : il lit le type dans l'URL et le passe en clé.
 *
 * Changer d'onglet remonte donc le composant, ce qui remet à zéro le fichier
 * sélectionné, le résultat et l'erreur — sans effet de bord à écrire. Sans ce
 * remontage, un fichier déposé sur l'import des étudiants restait sélectionné
 * en passant à l'import des cours, et le résultat de l'un s'affichait sous le
 * titre de l'autre.
 */
export default function ImportPageRoute() {
  const { type } = useParams();
  return <ImportPage key={type ?? 'cours-csv'} type={type} />;
}

function ImportPage({ type }) {
  const tab = TYPES[type] ?? 'csv-courses';
  const [file, setFile] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  // L'étape courante pilote la navigation par onglets, elle n'est pas lue ici.
  const [, setStep] = useState(STEP_UPLOAD);
  const fileRef = useRef(null);
  const navigate = useNavigate();

  const isCsvTab = tab === 'csv-courses' || tab === 'csv-schedule';
  const isIaTab = tab === 'schedule' || tab === 'courses';

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    const f = e.dataTransfer.files[0];
    if (!f) return;
    const ext = '.' + f.name.split('.').pop().toLowerCase();
    if (tab === 'csv-courses' || tab === 'csv-schedule') {
      if (ext === '.csv') {
        setFile(f); setError(''); setResult(null); setStep(STEP_UPLOAD);
      } else setError('Format non supporté. Utilisez CSV.');
    } else {
      if (ext === '.pdf') {
        setFile(f); setError(''); setResult(null); setStep(STEP_UPLOAD);
      } else setError('Format non supporté. Utilisez PDF.');
    }
  };

  const handleCsvImport = async (endpoint) => {
    if (!file) return;
    setUploading(true);
    setError('');
    setResult(null);
    const formData = new FormData();
    formData.append('file', file);
    try {
      const response = await api.post(endpoint, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const apiData = response.data;
      if (apiData.success && apiData.data) {
        setResult({
          success: apiData.data.errors?.length === 0 || apiData.data.success > 0,
          imported: apiData.data.success ?? 0,
          total: apiData.data.total ?? 0,
          errors: apiData.data.errors || [],
          message: apiData.message || '',
        });
      } else {
        setResult({
          success: false,
          imported: 0,
          total: 0,
          errors: [apiData.message || 'Erreur inconnue'],
        });
      }
      setStep(STEP_RESULTAT);
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Erreur lors de l\'import CSV.';
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
      const analysisId = data.data?.analysis_id;
      if (!analysisId) {
        throw new Error('Réponse invalide du serveur : identifiant d\'analyse manquant.');
      }
      sessionStorage.setItem('import_analysis', JSON.stringify({
        analysis_id: analysisId,
        type: 'schedule',
      }));
      navigate('/import/ai-analysis');
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Erreur lors de l\'analyse de l\'emploi du temps.';
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
      const analysisId = data.data?.analysis_id;
      if (!analysisId) {
        throw new Error('Réponse invalide du serveur : identifiant d\'analyse manquant.');
      }
      sessionStorage.setItem('import_analysis', JSON.stringify({
        analysis_id: analysisId,
        type: 'courses',
      }));
      navigate('/import/ai-analysis');
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Erreur lors de l\'analyse des cours.';
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
    if (tab === 'csv-courses') handleCsvImport('/admin/import/csv/courses');
    else if (tab === 'csv-schedule') handleCsvImport('/admin/import/csv/schedule');
    else if (tab === 'schedule') handleAnalyzeSchedule();
    else if (tab === 'courses') handleAnalyzeCourses();
  };

  const actionLabels = {
    'csv-courses': 'Importer les cours (CSV)',
    'csv-schedule': "Importer l'emploi du temps (CSV)",
    schedule: 'Analyser avec IA',
    courses: 'Analyser avec IA',
  };

  // Le titre change avec l'onglet : « Importation » seul ne disait pas ce qu'on
  // s'apprete a importer, alors que la barre d'onglets le porte deja au-dessus.
  const titres = {
    'csv-courses':  ['Import des cours (CSV)', 'Unités d\'enseignement et éléments constitutifs, une ligne par EC.'],
    'csv-schedule': ["Import de l'emploi du temps (CSV)", 'Créneaux récurrents. Les conflits de salle sont détectés automatiquement.'],
    schedule:       ["Import de l'emploi du temps (IA)", 'Document PDF analysé par le module d\'extraction. Rien n\'est enregistré avant votre validation.'],
    courses:        ['Import de la maquette pédagogique (IA)', 'Document PDF analysé par le module d\'extraction. Rien n\'est enregistré avant votre validation.'],
  };
  const [titre, sousTitre] = titres[tab] ?? titres['csv-courses'];

  return (
    <div className="max-w-2xl">
      <div className="mb-6">
        <h1 className="text-xl font-bold text-primary font-headline">{titre}</h1>
        <p className="text-sm text-on-surface-variant mt-0.5">{sousTitre}</p>
      </div>

      {/* Drop zone */}
      <div onDragOver={(e) => { e.preventDefault(); setDragOver(true); }} onDragLeave={() => setDragOver(false)} onDrop={handleDrop}
        className={`border-2 border-dashed rounded-xl p-12 text-center transition-all cursor-pointer ${dragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${file ? 'bg-surface-container-low' : ''}`}
        onClick={() => fileRef.current?.click()}>
        <input ref={fileRef} type="file" accept={isCsvTab ? '.csv' : '.pdf'} className="hidden" onChange={(e) => {
          const f = e.target.files[0];
          if (f) { setFile(f); setError(''); setResult(null); setStep(STEP_UPLOAD); }
        }} />

        {!file ? (
          <>
            <div className={`w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm ${
              isCsvTab ? 'bg-primary/10' : 'bg-primary-fixed'
            }`}>
              {isCsvTab ? <FiUpload className="text-3xl text-primary" /> : tab === 'schedule' ? <MdPictureAsPdf className="text-4xl text-primary" /> : <MdSchool className="text-4xl text-primary" />}
            </div>
            <h3 className="text-lg font-semibold text-on-surface mb-2">
              {tab === 'csv-courses' && 'Importez vos cours (UE/EC) au format CSV'}
              {tab === 'csv-schedule' && "Importez votre emploi du temps au format CSV"}
              {tab === 'schedule' && 'Glissez votre emploi du temps PDF ici'}
              {tab === 'courses' && 'Glissez votre catalogue de cours PDF ici'}
            </h3>
            <p className="text-sm text-on-surface-variant mb-6">
              ou <span className="text-primary font-bold cursor-pointer hover:underline">parcourez vos fichiers</span>
            </p>
            <div className="flex items-center justify-center gap-4 flex-wrap">
              {isCsvTab && (
                <div className="flex items-center gap-2 bg-surface-container-lowest px-3 py-1.5 rounded-lg text-xs font-medium text-outline shadow-sm">
                  <FiInfo className="text-sm" /> CSV uniquement — 5 Mo max
                </div>
              )}
              {isIaTab && (
                <>
                  <div className="flex items-center gap-2 bg-surface-container-lowest px-3 py-1.5 rounded-lg text-xs font-medium text-outline shadow-sm">
                    <MdPictureAsPdf className="text-sm" /> Format PDF uniquement
                  </div>
                  <div className="flex items-center gap-2 bg-surface-container-lowest px-3 py-1.5 rounded-lg text-xs font-medium text-outline shadow-sm">
                    <FiInfo className="text-sm" /> Max 10 Mo
                  </div>
                </>
              )}
            </div>
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

      {/* Modèle CSV vierge, pour les deux onglets CSV.
          L'ancienne version ouvrait l'URL du modèle avec le jeton en paramètre
          de requête : Sanctum ne lit que l'en-tête Authorization, le
          téléchargement repartait donc en 401 — et le jeton se retrouvait dans
          l'historique du navigateur et les journaux du serveur. */}
      {error && !result && (
        <div className="mt-4 flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle /> {error}
        </div>
      )}

      {/* Result for imports */}
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
              {result.imported}/{result.total} élément(s) importé(s)
              {result.errors?.length > 0 && ` (${result.errors.length} erreur${result.errors.length > 1 ? 's' : ''})`}
            </p>
            {result.errors?.length > 0 && Array.isArray(result.errors) && (
              <div className="mt-3 space-y-1 max-h-32 overflow-y-auto">
                {result.errors.map((e, i) => (
                  <p key={i} className="text-xs text-on-error-container/70 bg-error-container/20 p-1.5 rounded">
                    {typeof e === 'string'
                      ? e
                      : `${e.line ? 'Ligne ' + e.line : ''} : ${e.error || e.errors || 'Erreur'}`}
                  </p>
                ))}
              </div>
            )}
            {result.warnings?.length > 0 && (
              <div className="mt-2 space-y-1">
                {result.warnings.map((w, i) => (
                  <p key={i} className="text-xs text-warning bg-warning/10 p-1.5 rounded">
                    ⚠ {w.warning || w}
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
          {actionLabels[tab] || 'Continuer'}
        </button>
      )}

      {/* Pendant l'import */}
      {uploading && (
        <div className="mt-8 bg-surface-container-lowest rounded-xl p-8 shadow-sm border border-outline-variant/10 text-center">
          <FiLoader className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="font-semibold text-primary">
            {isIaTab ? 'Analyse IA en cours...' : 'Import CSV en cours...'}
          </p>
          <p className="text-sm text-on-surface-variant mt-1">Veuillez patienter</p>
        </div>
      )}

      {/* Info card */}
      <div className="mt-8 bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10">
        <h3 className="text-sm font-bold text-primary mb-2">Format attendu</h3>
        {tab === 'csv-courses' && (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Colonnes requises : <span className="font-mono font-medium text-primary">code_ue, intitule_ue, filiere_code, niveau, annee_libelle, semestre, volume_horaire_ue, code_ec, intitule_ec, volume_horaire_ec</span></p>
            <p className="mt-2">Une UE avec plusieurs ECs = plusieurs lignes (une par EC).</p>
            <div className="mt-3">
              <CsvTemplateDownload types={['ue-ec']} />
            </div>
          </div>
        )}
        {tab === 'csv-schedule' && (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Colonnes requises : <span className="font-mono font-medium text-primary">filiere_code, niveau, annee_libelle, ue_code, ec_code, jour, heure_debut, heure_fin, salle_code, type_cours</span></p>
            <p className="mt-2">La détection de conflits (même salle/même créneau) est automatique.</p>
            <div className="mt-3">
              <CsvTemplateDownload types={['edt']} />
            </div>
          </div>
        )}
        {tab === 'schedule' && (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Importez votre emploi du temps au format PDF.</p>
            <p>L'IA analysera le document et extraira automatiquement :</p>
            <ul className="list-disc list-inside mt-2 space-y-1">
              <li>Les cours et leurs horaires</li>
              <li>Les salles et créneaux</li>
              <li>Les conflits potentiels</li>
            </ul>
          </div>
        )}
        {tab === 'courses' && (
          <div className="text-xs text-on-surface-variant space-y-1">
            <p>Importez votre catalogue de cours (maquette) au format PDF.</p>
            <p>L'IA extraira automatiquement :</p>
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
