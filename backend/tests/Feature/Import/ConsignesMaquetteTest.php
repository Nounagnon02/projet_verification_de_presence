<?php

namespace Tests\Feature\Import;

use App\Services\Providers\ConsignesMaquette;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\GroqProvider;
use App\Services\Providers\OpenRouterProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Sur la maquette réelle de l'IFRI, l'IA mettait le CTT dans le volume d'un EC
 * (125 h pour 5 crédits, TPE compris) : chaque fournisseur avait sa consigne,
 * et toutes demandaient un « volume_horaire ». Une seule consigne, par type.
 */
class ConsignesMaquetteTest extends TestCase
{
    public function test_les_trois_fournisseurs_demandent_les_heures_par_type_jamais_le_ctt(): void
    {
        $consignes = [
            'gemini'     => (new ReflectionMethod(GeminiProvider::class, 'getCoursesPrompt'))->invoke(new GeminiProvider('cle')),
            'groq'       => (new ReflectionMethod(GroqProvider::class, 'buildPrompt'))->invoke(new GroqProvider('cle'), 'courses', 'texte'),
            'openrouter' => (new ReflectionMethod(OpenRouterProvider::class, 'getPrompt'))->invoke(new OpenRouterProvider('cle'), 'courses'),
        ];

        foreach ($consignes as $fournisseur => $consigne) {
            $this->assertStringContainsString(ConsignesMaquette::cours(), $consigne, $fournisseur);
            $this->assertStringNotContainsString('volume_horaire', $consigne, $fournisseur);
        }

        foreach (['cm :', 'td :', 'tp :', 'td_tp :', 'Ne mets JAMAIS le TPE ni le CTT'] as $attendu) {
            $this->assertStringContainsString($attendu, ConsignesMaquette::cours());
        }
    }
}
