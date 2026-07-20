<?php

namespace App\Jobs;

use App\Models\Analyse;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessGeminiImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Le nombre de tentatives max (avec backoff exponentiel)
     */
    public $tries = 3;

    /**
     * Le délai entre les tentatives (en secondes)
     * Utilise un backoff exponentiel : 60s, 300s, 1800s
     */
    public $backoff = [60, 300, 1800];

    /**
     * Timeout du job (en secondes)
     */
    public $timeout = 300; // 5 minutes

    protected Analyse $analyse;
    protected string $type; // 'schedule' ou 'courses'

    public function __construct(Analyse $analyse, string $type)
    {
        $this->analyse = $analyse;
        $this->type = $type;

        // Configurer la queue dédiée
        $this->onQueue('gemini-import');
    }

    public function handle(GeminiService $geminiService): void
    {
        $this->analyse->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $filePath = $this->analyse->file_path;

        // Télécharger le fichier depuis Supabase Storage vers un fichier temporaire
        $tmpPath = tempnam(sys_get_temp_dir(), 'gemini_') . '.pdf';
        file_put_contents($tmpPath, Storage::disk('supabase')->get($filePath));
        $fullPath = $tmpPath;

        if (!file_exists($fullPath)) {
            $this->fail(new \Exception("Fichier introuvable sur Supabase : {$filePath}"));
            return;
        }

        try {
            $result = match ($this->type) {
                'schedule' => $geminiService->analyzeSchedule($fullPath),
                'courses' => $geminiService->analyzeCourses($fullPath),
                default => throw new \InvalidArgumentException("Type d'import inconnu : {$this->type}"),
            };

            $this->analyse->update([
                'status' => $result['status'] === 'success' ? 'completed' : 'failed',
                'result' => $result['data'] ?? [],
                'score_de_confiance' => $result['score_de_confiance'] ?? null,
                'statut_analyse' => $result['statut_analyse'] ?? null,
                'warning' => $result['warning'] ?? null,
                'error_message' => $result['message'] ?? null,
                'completed_at' => now(),
            ]);

            // Si échec, lever une exception pour déclencher le retry
            if ($result['status'] !== 'success') {
                throw new \Exception($result['message'] ?? 'Erreur inconnue lors de l\'analyse Gemini');
            }

            Log::info("Import Gemini {$this->type} terminé avec succès", [
                'analyse_id' => $this->analyse->id,
                'score' => $result['score_de_confiance'] ?? null,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini : timeout ou erreur réseau', [
                'analyse_id' => $this->analyse->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            $this->analyse->update([
                'error_message' => 'Timeout de l\'API Gemini. Tentative ' . $this->attempts() . '/3.',
            ]);
            throw $e; // Déclencher le retry
        } catch (\Exception $e) {
            Log::error('Gemini : erreur inattendue', [
                'analyse_id' => $this->analyse->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            $this->analyse->update([
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Nettoyer le fichier temporaire
            if (isset($tmpPath) && file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    /**
     * Gestion de l'échec définitif après toutes les tentatives
     */
    public function failed(\Throwable $exception): void
    {
        $this->analyse->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);

        Log::error("Import Gemini {$this->type} échoué définitivement", [
            'analyse_id' => $this->analyse->id,
            'error' => $exception->getMessage(),
        ]);
    }
}