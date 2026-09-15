<?php

namespace Tests\Feature\Import;

use App\Services\Providers\GeminiProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * « Emploi du temps ... du 15 juin 2026 » : la version du document, lue par
 * l'IA, accompagne les créneaux ; une date inventée ou impossible est écartée.
 */
class ValiditeEmploiDuTempsIaTest extends TestCase
{
    private function traiter(array $donnees): array
    {
        $fournisseur = new GeminiProvider('cle');
        $methode = new ReflectionMethod($fournisseur, 'processScheduleResult');

        return $methode->invoke($fournisseur, $donnees, base_path('../tests/ia/corpus/edt-01-simple.pdf'))->data;
    }

    public function test_la_date_du_document_accompagne_les_creneaux(): void
    {
        $creneau = ['ec' => 'Algorithmique', 'jour' => 'Lundi', 'heure_debut' => '8h', 'heure_fin' => '10h'];

        $this->assertSame('2026-06-15', $this->traiter(['events' => [$creneau], 'valide_du' => '2026-06-15'])['valide_du']);
        $this->assertNull($this->traiter(['events' => [$creneau], 'valide_du' => '2026-02-30'])['valide_du']);
        $this->assertNull($this->traiter(['events' => [$creneau], 'valide_du' => 'le 15 juin'])['valide_du']);
        $this->assertNull($this->traiter(['events' => [$creneau]])['valide_du']);
    }
}
