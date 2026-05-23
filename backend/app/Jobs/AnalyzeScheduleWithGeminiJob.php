<?php

namespace App\Jobs;

use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AnalyzeScheduleWithGeminiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 60;

    protected $filePath;
    protected $jobId;
    protected $userId;

    public function __construct(string $filePath, string $jobId, int $userId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
        $this->userId = $userId;
    }

    public function handle(GeminiService $gemini): void
    {
        try {
            Cache::put("gemini_job_{$this->jobId}_status", 'processing', 600);

            $result = $gemini->analyzeSchedule(storage_path('app/' . $this->filePath));

            Cache::put("gemini_job_{$this->jobId}_status", 'completed', 600);
            Cache::put("gemini_job_{$this->jobId}_result", $result, 600);

            Log::info("Analyse Gemini terminée pour job {$this->jobId}");
        } catch (\Exception $e) {
            Cache::put("gemini_job_{$this->jobId}_status", 'failed', 600);
            Cache::put("gemini_job_{$this->jobId}_error", $e->getMessage(), 600);

            Log::error("Erreur analyse Gemini job {$this->jobId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put("gemini_job_{$this->jobId}_status", 'failed', 600);
        Cache::put("gemini_job_{$this->jobId}_error", $exception->getMessage(), 600);
    }
}



OK, tu vas travailler sur le budget de la campagne de façon professionnelle comme tu sais bien le faire. La première chose, voici comment tu vas procéder. Tu prends le tableau. Nous allons travailler tous les montants, tous les montants qui sont là sont juste à titre illustratif. Donc, tu auras un grand 1. Dans le tableau, il faut faire un tableau où tu auras un grand 1. La première rubrique du grand 1 sera le lancement de la campagne à la mairie. Grand 2, mobilisation communautaire. Grand 2, mobilisation communautaire. Grand 1, lancement à la campagne. Grand 2, mobilisation communautaire. Donc, voilà. Et dans le grand 1, lancement de la campagne, il y aura location de bâches et chaises. Il faut mettre location de bâches et chaises à 80 000. Sonorisation, 50 000. Location de bâches et chaises, 80 000. Non, location de bâches et chaises, 50 000. Sonorisation, 50 000. C'est mieux. Location de bâches et chaises, 50 000. Sonorisation, 50 000. On va prévoir groupe électrogène. On ne sait jamais. Location de groupe électrogène, 20 000. Voilà, c'est tout. Pour la mobilisation communautaire, grand 2, nous aurons intéressement. de mobilisation communautaire, sensibilisation grand public, mobilisation communautaire, sensibilisation grand public, sensibilisation grand public, le budget, sensibilisation grand public pour trois localités. Sensibilisation grand public pour trois localités. Et la première localité, ce sera Houèto. Houèto, à Houèto, nous aurons besoin de location bâche, location bâche plus chère, 100 000. Sonorisation, 50 000. Et comment vous appelez ça? Sonorisation 50 000 et sandwich. pour parler de rafraîchissement de la population, on va mettre 150 000 ou 200 000. Et le même budget sera reporté pour Houèto, tu vas répliquer Houèto. Après Houèto nous aurons Togba. On va faire Houèto, on va faire Togba. Donc, le même budget sera répliqué pour Houèto, Togba. Et oublie la troisième zone d'abord. On va faire d'abord pour deux zones, Houèto, Togba, le même budget. Alors, pour la mobilisation communautaire, nous aurons les criers publics. Criers publics. Le montant, on va mettre les créateurs publics sur chaque site, 5 000, 5 000.  motivation des chefs de village pour l'activité, 5 000, 5 000. La mobilisation par les réseaux sociaux, 10 000. Mobilisation à travers les médias audio, radio, nous allons utiliser pour les radios au moins, nous allons utiliser disons 100 000. 100 000 pour les mobilisations, 100 000, on ira sur des radios différentes. Donc on va dire 100 000 par radio, ça fait 200 000. Donc prend en compte ces aspects les leaders religieux, voilà. Il y aura également toujours dans la mobilisation communautaire, les leaders religieux. Les leaders religieux qui viendront vont prendre également pour leur déplacement, chaque leader religieux va prendre 5 000. Il y aura, on aura quatre leaders religieux, chacun va prendre 5 000, ça fait 20 000. On aura des enseignants, on va inviter des enseignants qui prendront également chacun 5 000, 5 000, quatre enseignants par localité. Donc ça fait 20 000 pour Ouetau, 20 000 pour Touba. Les boîtes à images. les boîtes à images, nous allons mettre conception des boîtes à images, conception et impression des boîtes à images. On va prévoir un budget de 100 000 francs pour la conception, 100 000 pour 100 000 ou 150 000 pour la conception des boîtes et impression des boîtes à images. Voilà, donc on peut, c'est bon, tu peux arrêter le budget comme ça.