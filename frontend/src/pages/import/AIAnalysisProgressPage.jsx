import { useState, useEffect } from 'react';
import { FiLoader, FiCheck, FiFileText, FiCpu, FiAlertTriangle } from 'react-icons/fi';
import { useNavigate } from 'react-router-dom';

const STAGES = [
  { key: 'extraction', label: 'Extraction du fichier', icon: FiFileText },
  { key: 'analysis', label: 'Analyse par IA', icon: FiCpu },
  { key: 'validation', label: 'Validation des résultats', icon: FiCheck },
];

export default function AIAnalysisProgressPage() {
  const [currentStage, setCurrentStage] = useState(0);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    // Vérifier que les données d'analyse existent
    const stored = sessionStorage.getItem('import_analysis');
    if (!stored) {
      setError('Aucune analyse en cours. Veuillez importer un fichier.');
      return;
    }

    const stageTimers = [
      { duration: 1500, next: 1 },
      { duration: 2500, next: 2 },
      { duration: 1500, next: 3 },
    ];

    let timer;
    let start = Date.now();

    const runStage = (stageIdx) => {
      if (stageIdx >= stageTimers.length) return;
      start = Date.now();
      const { duration, next } = stageTimers[stageIdx];
      setCurrentStage(stageIdx);

      timer = setInterval(() => {
        const elapsed = Date.now() - start;
        const pct = Math.min((elapsed / duration) * 100, 100);
        setProgress(pct);
        if (pct >= 100) {
          clearInterval(timer);
          setCurrentStage(next);
          if (next < stageTimers.length) {
            runStage(next);
          }
        }
      }, 50);
    };

    runStage(0);
    return () => clearInterval(timer);
  }, []);

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
          <button
            onClick={() => navigate('/import')}
            className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all"
          >
            Retour à l'import
          </button>
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
            <p className="text-on-surface-variant mb-8">Les données extraites sont prêtes à être validées.</p>
            <button
              onClick={() => navigate('/import/validate-courses')}
              className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all"
            >
              Voir les résultats
            </button>
          </>
        ) : (
          <>
            <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
              <FiLoader className="text-primary animate-spin" size={32} />
            </div>
            <h1 className="text-2xl font-bold font-headline text-primary mb-3">Analyse en cours</h1>
            <p className="text-on-surface-variant mb-8">Notre IA analyse votre fichier...</p>

            {/* Progress bar */}
            <div className="w-full bg-surface-container-high rounded-full h-2 mb-8">
              <div
                className="h-full bg-primary rounded-full transition-all duration-300"
                style={{ width: `${((currentStage + progress / 100) / STAGES.length) * 100}%` }}
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
                      <div className="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                    )}
                  </div>
                );
              })}
            </div>

            <button
              onClick={() => navigate('/import')}
              className="mt-8 text-sm text-on-surface-variant hover:text-primary transition-colors"
            >
              Annuler
            </button>
          </>
        )}
      </div>
    </div>
  );
}
