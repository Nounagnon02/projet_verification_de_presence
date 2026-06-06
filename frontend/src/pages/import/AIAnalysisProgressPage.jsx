import { useState, useEffect, useCallback } from 'react';
import { FiLoader, FiCheck, FiFileText, FiCpu, FiAlertTriangle, FiRefreshCw } from 'react-icons/fi';
import { useNavigate } from 'react-router-dom';
import api from '../../api/axios';

const POLL_INTERVAL = 2000; // 2 secondes entre chaque poll

const STAGES = [
  { key: 'extraction', label: 'Extraction du fichier', icon: FiFileText },
  { key: 'analysis', label: 'Analyse par IA', icon: FiCpu },
  { key: 'validation', label: 'Validation des résultats', icon: FiCheck },
];

export default function AIAnalysisProgressPage() {
  const [currentStage, setCurrentStage] = useState(0);
  const [error, setError] = useState(null);
  const [analysisId, setAnalysisId] = useState(null);
  const [analysisType, setAnalysisType] = useState(null);
  const [pollCount, setPollCount] = useState(0);
  const navigate = useNavigate();

  const getStageFromStatus = (status) => {
    const map = {
      pending: 0,
      processing: 1,
      completed: 2,
      failed: 2,
    };
    return map[status] ?? 0;
  };

  const pollAnalysisStatus = useCallback(async (id) => {
    try {
      const { data } = await api.get(`/admin/import/analysis-status/${id}`);
      if (data.success && data.data) {
        const { status } = data.data;
        setCurrentStage(getStageFromStatus(status));

        if (status === 'completed') {
          // Analyse terminée — stocker le résultat et naviguer
          sessionStorage.setItem('import_analysis_result', JSON.stringify(data.data));

          // Rediriger vers la bonne page de validation selon le type
          const type = data.data.type || analysisType;
          if (type === 'courses') {
            navigate('/import/validate-courses');
          } else {
            navigate('/import/validate-schedule');
          }
          return true; // Arrêter le polling
        }

        if (status === 'failed') {
          setError(data.data.error_message || 'L\'analyse a échoué. Veuillez réessayer.');
          return true; // Arrêter le polling
        }

        // Encore en cours — continuer le polling
        return false;
      }
      return false;
    } catch (err) {
      // Erreur réseau temporaire — on continue le polling
      console.warn('[AIAnalysis] Erreur de polling:', err);
      return false;
    }
  }, [navigate, analysisType]);

  useEffect(() => {
    // Lire les données depuis sessionStorage
    let stored;
    try {
      stored = JSON.parse(sessionStorage.getItem('import_analysis'));
    } catch (e) {
      // Ignorer
    }

    if (!stored || !stored.analysis_id) {
      setError('Aucune analyse en cours. Veuillez importer un fichier.');
      return;
    }

    setAnalysisId(stored.analysis_id);
    setAnalysisType(stored.type);
    setCurrentStage(getStageFromStatus('pending'));

    // Polling du statut
    let cancelled = false;
    let retries = 0;
    const MAX_RETRIES = 60; // 2 minutes max (60 × 2s)

    const poll = async () => {
      if (cancelled) return;
      setPollCount(prev => prev + 1);

      const done = await pollAnalysisStatus(stored.analysis_id);
      if (done || cancelled) return;

      retries++;
      if (retries >= MAX_RETRIES) {
        setError('L\'analyse a pris trop de temps. Veuillez réessayer.');
        return;
      }

      setTimeout(poll, POLL_INTERVAL);
    };

    // Premier poll immédiat, puis intervalle
    const initialTimer = setTimeout(poll, 500);

    return () => {
      cancelled = true;
      clearTimeout(initialTimer);
    };
  }, [pollAnalysisStatus]);

  const handleRetry = () => {
    setError(null);
    setCurrentStage(0);
    setPollCount(0);

    // Re-poller immédiatement
    const poll = async () => {
      const done = await pollAnalysisStatus(analysisId);
      if (!done) {
        setTimeout(poll, POLL_INTERVAL);
      }
    };
    setTimeout(poll, 500);
  };

  const isComplete = currentStage >= STAGES.length;

  if (error) {
    return (
      <div className="max-w-lg mx-auto py-12">
        <div className="bg-surface-container-lowest rounded-xl p-8 shadow-sm text-center">
          <div className="w-16 h-16 bg-error-container rounded-full flex items-center justify-center mx-auto mb-6">
            <FiAlertTriangle className="text-error" size={32} />
          </div>
          <h1 className="text-2xl font-bold font-headline text-primary mb-3">Erreur</h1>
          <p className="text-on-surface-variant mb-8">{error}</p>
          <div className="flex gap-3 justify-center">
            <button
              onClick={handleRetry}
              className="flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-semibold hover:opacity-90 transition-all"
            >
              <FiRefreshCw /> Réessayer
            </button>
            <button
              onClick={() => navigate('/import')}
              className="bg-surface-container-high text-on-surface-variant px-6 py-3 rounded-xl font-semibold hover:bg-surface-container-high/80 transition-all"
            >
              Retour à l'import
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-lg mx-auto py-12">
      <div className="bg-surface-container-lowest rounded-xl p-8 shadow-sm text-center">
        {isComplete ? (
          <>
            <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
              <FiCheck className="text-secondary" size={32} />
            </div>
            <h1 className="text-2xl font-bold font-headline text-primary mb-3">Analyse terminée !</h1>
            <p className="text-on-surface-variant mb-8">Redirection vers la validation...</p>
          </>
        ) : (
          <>
            <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
              <FiLoader className="text-primary animate-spin" size={32} />
            </div>
            <h1 className="text-2xl font-bold font-headline text-primary mb-3">Analyse en cours</h1>
            <p className="text-on-surface-variant mb-8">
              {analysisType === 'courses'
                ? 'Notre IA analyse votre catalogue de cours...'
                : "Notre IA analyse votre emploi du temps..."
              }
            </p>

            {/* Progress bar (indéterminée = simple animation) */}
            <div className="w-full bg-surface-container-high rounded-full h-2 mb-8 overflow-hidden">
              <div
                className="h-full bg-primary rounded-full transition-all duration-500"
                style={{
                  width: `${currentStage === 0 ? '15%' : currentStage === 1 ? '55%' : '90%'}`,
                }}
              />
            </div>

            {/* Stages */}
            <div className="space-y-4 text-left">
              {STAGES.map((stage, i) => {
                const StageIcon = stage.icon;
                const isActive = i === currentStage;
                const isDone = i < currentStage;
                return (
                  <div key={stage.key} className={`flex items-center gap-4 p-3 rounded-xl ${isActive ? 'bg-primary/5' : ''}`}>
                    <div className={`p-2 rounded-lg ${isDone ? 'bg-secondary/10 text-secondary' : isActive ? 'bg-primary/10 text-primary' : 'bg-surface-container-high text-on-surface-variant'}`}>
                      {isDone ? <FiCheck size={18} /> : <StageIcon size={18} />}
                    </div>
                    <div className="flex-1">
                      <p className={`text-sm font-semibold ${isDone ? 'text-secondary' : isActive ? 'text-primary' : 'text-on-surface-variant'}`}>
                        {stage.label}
                      </p>
                    </div>
                    {isActive && (
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-on-surface-variant">En cours...</span>
                        <div className="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                      </div>
                    )}
                  </div>
                );
              })}
            </div>

            <p className="mt-6 text-xs text-on-surface-variant/60">
              Requête {pollCount > 0 ? `#${pollCount}` : '...'}
            </p>

            <button
              onClick={() => navigate('/import')}
              className="mt-4 text-sm text-on-surface-variant hover:text-primary transition-colors"
            >
              Annuler
            </button>
          </>
        )}
      </div>
    </div>
  );
}
