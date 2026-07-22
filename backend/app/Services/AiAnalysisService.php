<?php

namespace App\Services;

use App\Contracts\AiProviderInterface;
use App\Models\Analyse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service d'analyse IA générique.
 * Utilise le provider configuré via AiProviderInterface.
 */
class AiAnalysisService
{
    public function __construct(
        private AiProviderInterface $provider
    ) {}

    /**
     * Analyse un document et met à jour l'enregistrement Analyse.
     */
    public function analyze(Analyse $analyse): Analyse
    {
        $analyse->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $filePath = $analyse->file_path;

        try {
            // Télécharger le fichier depuis le stockage
            $tmpPath = tempnam(sys_get_temp_dir(), 'ai_') . '.pdf';
            file_put_contents($tmpPath, Storage::disk('supabase')->get($filePath));

            if (!file_exists($tmpPath)) {
                throw new \Exception("Fichier introuvable sur le stockage : {$filePath}");
            }

            $result = $this->provider->analyzeDocument($tmpPath, $analyse->type);

            $analyse->update([
                'status'             => $result->isCompleted() ? 'completed' : 'failed',
                'result'             => $result->data ?? [],
                'score_de_confiance' => $result->confidence,
                'statut_analyse'     => $result->requiresValidation() ? 'a_reverifier' : 'valide',
                'warning'            => $result->warning,
                'error_message'      => $result->errorMessage,
                'completed_at'       => now(),
            ]);

            if ($result->isFailed()) {
                Log::warning("Analyse IA échouée via {$this->provider->getName()}", [
                    'analyse_id'  => $analyse->id,
                    'error'       => $result->errorMessage,
                ]);
            } else {
                Log::info("Analyse IA réussie via {$this->provider->getName()}", [
                    'analyse_id' => $analyse->id,
                    'confidence' => $result->confidence,
                ]);
            }
        } catch (\Exception $e) {
            $analyse->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            Log::error("Analyse IA exception via {$this->provider->getName()}", [
                'analyse_id' => $analyse->id,
                'error'      => $e->getMessage(),
            ]);
        } finally {
            if (isset($tmpPath) && file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }

        return $analyse;
    }

    /**
     * Retourne le nom du provider actif.
     */
    public function getProviderName(): string
    {
        return $this->provider->getName();
    }
}
