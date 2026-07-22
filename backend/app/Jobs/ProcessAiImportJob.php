<?php

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

    public $tries = 3;
    public $backoff = [60, 300, 1800];
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

        if ($this->analyse->fresh()->status === 'failed') {
            throw new \Exception($this->analyse->error_message ?? 'Erreur inconnue lors de l\'analyse IA');
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
