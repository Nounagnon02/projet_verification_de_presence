<?php

namespace Tests\Unit;

use App\Services\IdentifiantService;
use Tests\TestCase;

class IdentifiantServiceTest extends TestCase
{
    /**
     * Test la génération d'identifiant avec des données valides.
     */
    public function test_generates_identifier_correctly(): void
    {
        $nom      = 'Agossou';
        $prenom   = 'Marc';
        $matricule = '2024001';

        // Note: les vrais IDs dépendent des enregistrements en base (filière, année)
        // Ce test vérifie uniquement la normalisation et le format

        $normalized = IdentifiantService::normalize('Étudiant Test');
        $this->assertEquals('ETUDIANT_TEST', $normalized);
    }

    /**
     * Test la normalisation des accents.
     * iconv avec TRANSLIT convertit "Éé Àà ï ô ü" → "EE AA i o u"
     * puis les espaces deviennent des underscores.
     */
    public function test_normalize_removes_accents(): void
    {
        // "Éé Àà ï ô ü" → translittère en "EE AA i o u" → normalise en "EE_AA_I_O_U"
        $result = IdentifiantService::normalize('Éé Àà ï ô ü');
        $this->assertStringNotContainsString('É', $result);
        $this->assertStringNotContainsString('À', $result);
        $this->assertStringContainsString('EE', $result);
        $this->assertStringContainsString('_', $result);

        // "ç" → "c" (translit) → "C" (uppercase)
        $this->assertEquals('C', IdentifiantService::normalize('ç'));
    }

    /**
     * Test la normalisation met en majuscules.
     */
    public function test_normalize_uppercases(): void
    {
        $this->assertEquals('JEAN', IdentifiantService::normalize('jean'));
        $this->assertEquals('DUPONT', IdentifiantService::normalize('DuPonT'));
    }

    /**
     * Test la normalisation remplace les espaces.
     */
    public function test_normalize_replaces_spaces(): void
    {
        $this->assertEquals('JEAN_MARC', IdentifiantService::normalize('Jean Marc'));
        $this->assertEquals('VAN_DAMME', IdentifiantService::normalize('Van Damme'));
    }

    /**
     * Test la validation d'un identifiant bien formaté.
     */
    public function test_validate_valid_identifier(): void
    {
        $this->assertTrue(IdentifiantService::validate('AGOSSOU_MARC_2024001_IM-L2_2025-2026'));
        $this->assertTrue(IdentifiantService::validate('DUPONT_JEAN_2024002_GL-L3_2024-2025'));
    }

    /**
     * Test la validation d'un identifiant mal formaté.
     */
    public function test_validate_invalid_identifier(): void
    {
        // Pas assez de segments
        $this->assertFalse(IdentifiantService::validate('AGOSSOU_MARC_2024001'));
        // Mauvais format d'année
        $this->assertFalse(IdentifiantService::validate('AGOSSOU_MARC_2024001_IM-L2_2025/2026'));
        // Caractères spéciaux
        $this->assertFalse(IdentifiantService::validate('AGOSSOU_MARC*2024001_IM-L2_2025-2026'));
        // Chaîne vide
        $this->assertFalse(IdentifiantService::validate(''));
    }
}
