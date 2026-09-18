<?php

namespace Tests\Unit\Jobs;

use App\Contracts\AiProviderInterface;
use App\Jobs\ProcessAiImportJob;
use App\Models\Analyse;
use App\Services\AiAnalysisService;
use App\ValueObjects\AnalysisResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Régression : le job relançait TROIS fois (60 s, 5 min, 30 min) toute analyse
 * échouée, y compris les échecs définitifs — clé absente, JSON invalide, PDF
 * scanné — soit trois appels facturés pour le même résultat, et un
 * administrateur qui ne voyait l'erreur qu'après 35 minutes.
 */
class ProcessAiImportJobTest extends TestCase
{
    public function test_un_echec_danalyse_ne_relance_pas_le_job_et_reste_visible(): void
    {
        Storage::fake('local');
        $chemin = UploadedFile::fake()->create('cours.pdf', 10, 'application/pdf')->store('imports/courses');

        $analyse = Analyse::create(['type' => 'courses', 'status' => 'pending', 'file_path' => $chemin]);

        $provider = new class implements AiProviderInterface {
            public int $appels = 0;

            public function analyzeDocument(string $filePath, string $type): AnalysisResult
            {
                $this->appels++;

                return AnalysisResult::failed('Clé API manquante.');
            }

            public function getName(): string
            {
                return 'fake';
            }
        };

        // Ne lève pas : un job qui lève est relancé par la file.
        (new ProcessAiImportJob($analyse))->handle(new AiAnalysisService($provider));

        $this->assertSame(1, $provider->appels);
        $this->assertSame('failed', $analyse->fresh()->status);
        $this->assertSame('Clé API manquante.', $analyse->fresh()->error_message);
        $this->assertSame(1, (new ProcessAiImportJob($analyse))->tries);
    }
}
