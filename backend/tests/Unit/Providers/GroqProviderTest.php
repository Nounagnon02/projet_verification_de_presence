<?php

namespace Tests\Unit\Providers;

use App\Services\Providers\GroqProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Régression : Groq n'avait aucune relance (contrairement à Gemini) et une
 * confiance fixe (0,8) indépendante du document et de la réponse.
 */
class GroqProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!shell_exec('command -v pdftotext 2>/dev/null')) {
            $this->markTestSkipped('pdftotext (poppler-utils) absent de cet environnement.');
        }
    }

    private function fichierPdfAvecTexte(): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'groq_test_') . '.pdf';
        // Un PDF minimal mais réel : pdftotext doit y lire du texte.
        $contenu = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/MediaBox[0 0 2000 200]/Contents 5 0 R>>endobj\n"
            . "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            . "5 0 obj<</Length 90>>stream\nBT /F1 12 Tf 10 100 Td (Bonjour ceci est un texte de test suffisamment long pour depasser le seuil) Tj ET\nendstream endobj\n"
            . "trailer<</Root 1 0 R>>";
        file_put_contents($chemin, $contenu);

        return $chemin;
    }

    public function test_un_429_est_relance_puis_reussit(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'quota'], 429)
            ->push(['choices' => [['message' => ['content' => '{"ues": []}']]]], 200);

        $resultat = (new GroqProvider('cle-test'))->analyzeDocument($this->fichierPdfAvecTexte(), 'courses');

        $this->assertTrue($resultat->isCompleted(), (string) $resultat->errorMessage);
        Http::assertSentCount(2);
        $this->assertSame(0.69, $resultat->confidence);
    }

    public function test_la_confiance_dun_emploi_du_temps_vient_du_classificateur_pas_dun_chiffre_fixe(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'events' => [['ec' => 'Algo', 'date' => '2026-09-21', 'heure_debut' => '08:00', 'heure_fin' => '10:00']],
            ])]]],
        ], 200)]);

        $resultat = (new GroqProvider('cle-test'))->analyzeDocument($this->fichierPdfAvecTexte(), 'schedule');

        $this->assertTrue($resultat->isCompleted(), (string) $resultat->errorMessage);
        $this->assertSame(1.0, $resultat->confidence, 'Un seul créneau, entièrement compris : ratio de 1,0, pas le 0,8 fixe d\'avant.');
    }
}
