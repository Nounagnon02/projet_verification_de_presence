<?php

namespace Tests\Unit\Providers;

use App\Services\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Régression : OpenRouter n'avait aucune relance et une confiance fixe (0,85)
 * indépendante du document et de la réponse.
 */
class OpenRouterProviderTest extends TestCase
{
    private function fichierPdf(): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'or_test_') . '.pdf';
        file_put_contents($chemin, '%PDF-1.4 test');

        return $chemin;
    }

    public function test_un_503_est_relance_puis_reussit(): void
    {
        Http::fakeSequence()
            ->push('indisponible', 503)
            ->push(['choices' => [['message' => ['content' => '{"ues": []}']]]], 200);

        $resultat = (new OpenRouterProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        $this->assertTrue($resultat->isCompleted(), (string) $resultat->errorMessage);
        Http::assertSentCount(2);
        $this->assertSame(0.69, $resultat->confidence);
    }

    public function test_un_401_nest_pas_relance(): void
    {
        Http::fake(['*' => Http::response('clé invalide', 401)]);

        $resultat = (new OpenRouterProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        Http::assertSentCount(1);
        $this->assertTrue($resultat->isFailed());
        $this->assertStringContainsString('OpenRouter', $resultat->errorMessage);
    }

    public function test_la_confiance_dun_emploi_du_temps_vient_du_classificateur(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'events' => [['ec' => 'Algo', 'date' => '2026-09-21', 'heure_debut' => '08:00', 'heure_fin' => '10:00']],
            ])]]],
        ], 200)]);

        $resultat = (new OpenRouterProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'schedule');

        $this->assertTrue($resultat->isCompleted(), (string) $resultat->errorMessage);
        $this->assertSame(1.0, $resultat->confidence);
    }
}
