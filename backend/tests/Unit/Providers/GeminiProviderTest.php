<?php

namespace Tests\Unit\Providers;

use App\Services\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GeminiProvider face aux pannes du fournisseur — jusqu'ici non couvert :
 * aucun test n'appelait un fournisseur IA, même via Http::fake() (constat de
 * l'audit). Le pipeline d'import ne peut distinguer une erreur transitoire
 * d'une définitive que si ce comportement est verrouillé.
 */
class GeminiProviderTest extends TestCase
{
    private function fichierPdf(): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'gemini_test_') . '.pdf';
        file_put_contents($chemin, '%PDF-1.4 test');

        return $chemin;
    }

    private function reponseGemini(string $texte): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $texte]]]]]];
    }

    public function test_un_succes_immediat_ne_relance_pas(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->reponseGemini(
                '{"ues": [{"code": "INF101", "intitule": "Test", "ecs": []}]}'
            ), 200),
        ]);

        $resultat = (new GeminiProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        Http::assertSentCount(1);
        $this->assertTrue($resultat->isCompleted());
        // Sous 0,70 à dessein : aucun signal fiable par UE/EC pour ce type
        // d'extraction (voir le commentaire de processCoursesResult).
        $this->assertSame(0.69, $resultat->confidence);
        $this->assertTrue($resultat->requiresValidation());
    }

    public function test_un_429_est_relance_puis_reussit(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'quota'], 429)
            ->push($this->reponseGemini('{"ues": []}'), 200);

        $resultat = (new GeminiProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        Http::assertSentCount(2);
        $this->assertTrue($resultat->isCompleted());
    }

    public function test_un_500_persistant_epuise_les_relances_et_echoue(): void
    {
        Http::fake(['*generateContent*' => Http::response('erreur serveur', 500)]);

        $resultat = (new GeminiProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        // max_retries par défaut (3) + la tentative initiale.
        Http::assertSentCount(4);
        $this->assertTrue($resultat->isFailed());
        $this->assertStringContainsString('Gemini', $resultat->errorMessage);
    }

    public function test_un_404_de_modele_retire_nest_pas_relance(): void
    {
        // Régression du 2026-08-24 : gemini-2.0-flash retiré par Google. Une
        // erreur définitive (4xx hors 429) ne doit consommer qu'un appel.
        Http::fake(['*generateContent*' => Http::response('modèle introuvable', 404)]);

        $resultat = (new GeminiProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        Http::assertSentCount(1);
        $this->assertTrue($resultat->isFailed());
    }

    public function test_un_json_invalide_echoue_sans_relancer(): void
    {
        Http::fake(['*generateContent*' => Http::response($this->reponseGemini('ceci n\'est pas du JSON'), 200)]);

        $resultat = (new GeminiProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'courses');

        Http::assertSentCount(1);
        $this->assertTrue($resultat->isFailed());
        $this->assertStringContainsString('inattendu', $resultat->errorMessage);
    }

    public function test_une_cle_absente_nappelle_jamais_le_reseau(): void
    {
        Http::fake();

        $resultat = (new GeminiProvider(null))->analyzeDocument($this->fichierPdf(), 'courses');

        Http::assertNothingSent();
        $this->assertTrue($resultat->isFailed());
    }

    public function test_la_confiance_dun_emploi_du_temps_reflete_le_diagnostic_du_classificateur(): void
    {
        // Un créneau daté (compris) et un objet illisible (écarté) : la
        // confiance est le ratio retenus/total, pas un chiffre fixe.
        Http::fake(['*generateContent*' => Http::response($this->reponseGemini(json_encode([
            'events' => [
                ['ec' => 'Algo', 'date' => '2026-09-21', 'heure_debut' => '08:00', 'heure_fin' => '10:00'],
                'texte-inattendu-a-la-place-dun-objet',
            ],
        ])), 200)]);

        $resultat = (new GeminiProvider('cle-test'))->analyzeDocument($this->fichierPdf(), 'schedule');

        $this->assertTrue($resultat->isCompleted());
        $this->assertSame(0.5, $resultat->confidence);
        $this->assertNotNull($resultat->warning);
    }
}
