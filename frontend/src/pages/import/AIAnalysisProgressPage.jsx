import { useState, useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { FiLoader, FiCheck, FiFileText, FiCpu, FiAlertTriangle, FiRefreshCw } from 'react-icons/fi';
import { useNavigate } from 'react-router-dom';
import { recupererStatutAnalyseIa } from '../../api/resources/imports';

const POLL_INTERVAL = 2000; // 2 secondes entre chaque poll
const MAX_RETRIES = 60; // 2 minutes max (60 × 2s)

const STAGES = [
  { key: 'extraction', label: 'Extraction du fichier', icon: FiFileText },
  { key: 'analysis', label: 'Analyse par IA', icon: FiCpu },
  { key: 'validation', label: 'Validation des résultats', icon: FiCheck },
];

/**
 * Lit l'analyse en cours déposée en session par l'écran d'import.
 *
 * Fonction pure au niveau module : la lecture de sessionStorage est synchrone et
 * peut donc servir de valeur initiale d'état. Effectuée dans un effet, elle
 * imposait un rendu supplémentaire et affichait brièvement une progression à
 * zéro avant de basculer sur l'erreur quand aucune analyse n'existait.
 *
 * Renvoie soit l'identifiant et le type, soit le message d'erreur à afficher.
 */
function lireAnalyseEnSession() {
  let stored;

  try {
    // « import_en_cours » et non « import_analysis » : cette derniere porte le
    // RESULTAT de l'analyse d'un emploi du temps, ecrit par cette page elle-meme
    // un peu plus bas. Lire et ecrire la meme cle faisait qu'un second import
    // relisait le resultat du premier, lequel ne contient aucun analysis_id.
    stored = JSON.parse(sessionStorage.getItem('import_en_cours'));
  } catch {
    stored = null;
  }

  if (!stored || !stored.analysis_id) {
    return { erreur: 'Aucune analyse en cours. Veuillez importer un fichier.' };
  }

  const id = Number(stored.analysis_id);

  if (!id || Number.isNaN(id)) {
    return { erreur: "Identifiant d'analyse invalide. Veuillez relancer l'import." };
  }

  // Consommee : sans cela, revenir sur cette page apres coup relancerait le
  // suivi d'une analyse deja validee, et renverrait vers un ecran de validation
  // dont les donnees ont ete traitees.
  sessionStorage.removeItem('import_en_cours');

  return { id, type: stored.type };
}

function getStageFromStatus(status) {
  const map = {
    pending: 0,
    processing: 1,
    completed: 2,
    failed: 2,
  };
  return map[status] ?? 0;
}

export default function AIAnalysisProgressPage() {
  // Lecture une seule fois, à l'initialisation.
  const [initial] = useState(lireAnalyseEnSession);
  const analysisId = initial.id ?? null;
  const analysisType = initial.type ?? null;

  // La section « Imports » a ete supprimee : chaque import vit desormais dans
  // l'ecran qui gere les donnees qu'il alimente. Le retour mene donc la, selon
  // le type de document analyse.
  const retourVersLOrigine = analysisType === 'courses' ? '/courses' : '/schedules/weekly';
  const navigate = useNavigate();

  // Compteur de tentatives : une ref, pour que la decision de refetchInterval
  // (ci-dessous) lise toujours la valeur la plus recente sans dependre de la
  // fraicheur d'une fermeture React. pollCount, lui, n'existe que pour
  // l'affichage « Requête #n ».
  const tentatives = useRef(0);
  const [pollCount, setPollCount] = useState(0);
  // Message du bouton Réessayer quand l'analyse n'est plus disponible : ecrit
  // uniquement depuis ce gestionnaire de clic, jamais depuis un effet.
  const [erreurRelance, setErreurRelance] = useState(null);

  const statutQuery = useQuery({
    queryKey: ['analyse-ia-statut', analysisId],
    enabled: Boolean(analysisId) && !initial.erreur,
    queryFn: async ({ signal }) => {
      tentatives.current += 1;
      setPollCount(tentatives.current);
      try {
        return await recupererStatutAnalyseIa(analysisId, signal);
      } catch (err) {
        // Erreur réseau temporaire — on continue le polling, comme avant.
        console.warn('[AIAnalysis] Erreur de polling:', err);
        return { success: false, data: null };
      }
    },
    // Remplacement idiomatique du setInterval/setTimeout répété : on repolle
    // toutes les POLL_INTERVAL ms tant que le statut n'est ni terminé ni en
    // échec, et jusqu'à MAX_RETRIES tentatives (2 minutes).
    refetchInterval: (query) => {
      const status = query.state.data?.data?.status;
      if (status === 'completed' || status === 'failed') return false;
      return tentatives.current >= MAX_RETRIES ? false : POLL_INTERVAL;
    },
  });

  const status = statutQuery.data?.data?.status;
  const currentStage = getStageFromStatus(status);
  const isFailed = status === 'failed';
  const delaiDepasse = !isFailed && status !== 'completed' && pollCount >= MAX_RETRIES;

  // Redirection vers l'écran de validation : une action sur l'extérieur (la
  // navigation, l'écriture en session), jamais un setState — le seul type
  // d'effet qui reste légitime ici.
  useEffect(() => {
    if (status !== 'completed' || !statutQuery.data) return;
    const data = statutQuery.data.data;
    const type = data.type || analysisType;
    if (type === 'courses') {
      sessionStorage.setItem('import_courses_analysis', JSON.stringify(data));
      navigate('/import/validate-courses');
    } else {
      sessionStorage.setItem('import_analysis', JSON.stringify(data));
      navigate('/import/validate-schedule');
    }
  }, [status, statutQuery.data, analysisType, navigate]);

  const error = erreurRelance
    ?? initial.erreur
    ?? (isFailed ? (statutQuery.data?.data?.error_message || 'L\'analyse a échoué. Veuillez réessayer.') : null)
    ?? (delaiDepasse ? 'L\'analyse a pris trop de temps. Veuillez réessayer.' : null);

  const handleRetry = () => {
    if (!analysisId) {
      setErreurRelance("Impossible de réessayer : l'analyse n'est plus disponible. Veuillez réimporter le fichier.");
      return;
    }
    tentatives.current = 0;
    setPollCount(0);
    setErreurRelance(null);
    statutQuery.refetch();
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
              onClick={() => navigate(retourVersLOrigine)}
              className="bg-surface-container-high text-on-surface-variant px-6 py-3 rounded-xl font-semibold hover:bg-surface-container-high/80 transition-all"
            >
              Retour
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
              onClick={() => navigate(retourVersLOrigine)}
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
