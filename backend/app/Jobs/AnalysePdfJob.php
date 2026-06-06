<?php

namespace App\Jobs;

use App\Models\Analyse;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job asynchrone pour l'analyse IA (Gemini) de PDF.
 * Conforme CDC 8.1 & 8.4 — retry automatique avec backoff.
 *
 * @property string $filePath
 * @property int $analyseId
 */
class AnalysePdfJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    /**
     * Tentatives maximales avant échec (CDC 8.4).
     */
    public int $tries = 3;

    /**
     * Backoff exponentiel : 10s, 30s, 60s.
     */
    public array $backoff = [10, 30, 60];

    /**
     * Timeout par tentative : 120s (Gemini peut être lent).
     */
    public int $timeout = 120;

    /**
     * @param  string  $filePath  Chemin absolu du fichier PDF uploadé.
     * @param  int  $analyseId   ID du modèle Analyse à mettre à jour.
     */
    public function __construct(
        public string $filePath,
        public int $analyseId,
    ) {
        $this->onQueue('high');
    }

    /**
     * Exécute l'analyse Gemini.
     */
    public function handle(GeminiService $gemini): void
    {
        /** @var Analyse|null $analyse */
        $analyse = Analyse::find($this->analyseId);

        if (! $analyse) {
            Log::warning('AnalysePdfJob : Analyse introuvable', [
                'analyse_id' => $this->analyseId,
                'file'       => $this->filePath,
            ]);

            return;
        }

        // Passage en cours de traitement
        $analyse->update(['status' => 'processing']);

        try {
            // Appel à Gemini selon le type d'analyse
            $result = match ($analyse->type) {
                'schedule' => $gemini->analyzeSchedule($this->filePath),
                'courses'  => $gemini->analyzeCourses($this->filePath),
                default    => throw new \InvalidArgumentException(
                    "Type d'analyse inconnu : {$analyse->type}"
                ),
            };

            // Mise à jour du modèle Analyse avec le résultat
            $analyse->update([
                'status'             => 'completed',
                'result'             => $result['data'] ?? $result,
                'score_de_confiance' => $result['score_de_confiance'] ?? null,
                'statut_analyse'     => $result['statut_analyse'] ?? null,
                'warning'            => $result['warning'] ?? null,
                'error_message'      => null,
            ]);

            Log::info('AnalysePdfJob terminée avec succès', [
                'analyse_id' => $this->analyseId,
                'type'       => $analyse->type,
                'score'      => $result['score_de_confiance'] ?? 'N/A',
            ]);

        } catch (\Throwable $e) {
            // Erreur temporaire — le job sera retenté (via $tries / $backoff)
            $analyse->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("AnalysePdfJob échouée (tentative {$this->attempts()})", [
                'analyse_id' => $this->analyseId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e; // Relancée pour que Laravel gère le retry
        }
    }

    /**
     * Dernière tentative échouée — statut finalisé.
     */
    public function failed(?\Throwable $e): void
    {
        $analyse = Analyse::find($this->analyseId);

        if (! $analyse) {
            return;
        }

        $analyse->update([
            'status'        => 'failed',
            'error_message' => $e?->getMessage() ?? 'Erreur inconnue après 3 tentatives.',
        ]);

        Log::error('AnalysePdfJob définitivement échouée', [
            'analyse_id' => $this->analyseId,
            'error'      => $e?->getMessage(),
        ]);
    }

    /**
     * ID d'unicité — empêche deux jobs identiques en simultané.
     */
    public function uniqueId(): string
    {
        return "analyse_{$this->analyseId}";
    }
}
