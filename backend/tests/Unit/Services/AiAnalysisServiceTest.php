<?php

namespace Tests\Unit\Services;

use App\Contracts\AiProviderInterface;
use App\Models\Analyse;
use App\Services\AiAnalysisService;
use App\ValueObjects\AnalysisResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Régression : AiAnalysisService lisait le fichier sur le disque « supabase »
 * codé en dur, alors qu'ImportController l'écrit désormais sur le disque par
 * défaut (FILESYSTEM_DISK) — les deux ont divergé, et l'import IA échouait
 * silencieusement (« Fichier introuvable sur le stockage ») dès que le disque
 * par défaut n'était pas nommé « supabase », ce qui est le cas en local et en
 * test.
 */
class AiAnalysisServiceTest extends TestCase
{
    public function test_analyze_lit_le_fichier_sur_le_disque_par_defaut(): void
    {
        Storage::fake('local');

        // Même chemin d'écriture que ImportController::courses()/schedule().
        $chemin = UploadedFile::fake()->create('cours.pdf', 10, 'application/pdf')
            ->store('imports/courses');

        $analyse = Analyse::create([
            'type'      => 'courses',
            'status'    => 'pending',
            'file_path' => $chemin,
        ]);

        $resultatAttendu = AnalysisResult::completed(['events' => []], confidence: 0.9);

        $provider = new class($resultatAttendu) implements AiProviderInterface {
            public function __construct(private AnalysisResult $resultat) {}

            public function analyzeDocument(string $filePath, string $type): AnalysisResult
            {
                return $this->resultat;
            }

            public function getName(): string
            {
                return 'fake';
            }
        };

        (new AiAnalysisService($provider))->analyze($analyse);

        $analyse->refresh();

        $this->assertSame('completed', $analyse->status);
        $this->assertNull($analyse->error_message);
    }

    public function test_analyze_echoue_proprement_si_le_fichier_est_introuvable(): void
    {
        Storage::fake('local');

        $analyse = Analyse::create([
            'type'      => 'courses',
            'status'    => 'pending',
            'file_path' => 'imports/courses/absent.pdf',
        ]);

        $provider = new class implements AiProviderInterface {
            public function analyzeDocument(string $filePath, string $type): AnalysisResult
            {
                throw new \RuntimeException('Ne doit pas être appelé : le fichier est introuvable.');
            }

            public function getName(): string
            {
                return 'fake';
            }
        };

        (new AiAnalysisService($provider))->analyze($analyse);

        $analyse->refresh();

        $this->assertSame('failed', $analyse->status);
        $this->assertStringContainsString('introuvable', $analyse->error_message);
    }
}
