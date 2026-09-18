<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Analyse;
use App\Services\AiAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Une seule tentative : les erreurs transitoires (429, 5xx, timeout) sont
     * déjà relancées PAR le fournisseur, avec un délai qui double
     * (RelanceLesErreursTransitoires). Relancer le job en plus, jusqu'à 35
     * minutes plus tard, ne rattrapait que ce cas — et facturait trois appels
     * pour le même résultat sur les échecs définitifs (clé absente, JSON
     * invalide, PDF scanné, 4xx).
     */
    public $tries = 1;
    public $timeout = 300;

    protected Analyse $analyse;

    public function __construct(Analyse $analyse)
    {
        $this->analyse = $analyse;
        $this->onQueue('ai-import');
    }

    public function handle(AiAnalysisService $service): void
    {
        $service->analyze($this->analyse);

        // L'échec est déjà porté par l'Analyse (statut « failed » et message),
        // que l'interface lit par polling : l'administrateur le voit tout de
        // suite, au lieu d'attendre la fin des relances du job.
        if ($this->analyse->fresh()->status === 'failed') {
            Log::warning("Import IA échoué via {$service->getProviderName()}", [
                'analyse_id' => $this->analyse->id,
                'error'      => $this->analyse->fresh()->error_message,
            ]);

            return;
        }

        Log::info("Import IA terminé avec succès via {$service->getProviderName()}", [
            'analyse_id' => $this->analyse->id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->analyse->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at'  => now(),
        ]);

        Log::error("Import IA échoué définitivement", [
            'analyse_id' => $this->analyse->id,
            'error'      => $exception->getMessage(),
        ]);
    }
}
