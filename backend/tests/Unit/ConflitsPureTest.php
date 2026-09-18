<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Planning\Conflits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les fonctions pures de la règle de conflit commune (Conflits) : recouvrement
 * de périodes de validité, extraction des enseignants, clé de comparaison d'un
 * nom. Sans base de données — elles servent aux quatre chemins de création de
 * séances, et chaque cas limite demandait auparavant un emploi du temps complet.
 */
class ConflitsPureTest extends TestCase
{
    #[DataProvider('validites')]
    public function test_recouvrement_de_deux_periodes_de_validite(?string $du1, ?string $au1, ?string $du2, ?string $au2, bool $attendu): void
    {
        $this->assertSame($attendu, Conflits::validitesSeRecouvrent($du1, $au1, $du2, $au2));
        // La relation est symétrique : l'ordre des deux périodes ne change rien.
        $this->assertSame($attendu, Conflits::validitesSeRecouvrent($du2, $au2, $du1, $au1));
    }

    public static function validites(): array
    {
        return [
            'deux périodes sans borne'              => [null, null, null, null, true],
            'l\'une sans borne, l\'autre datée'     => [null, null, '2026-03-01', '2026-06-30', true],
            'périodes qui se chevauchent'           => ['2026-01-01', '2026-03-31', '2026-03-01', '2026-06-30', true],
            'se touchent le même jour'              => ['2026-01-01', '2026-03-01', '2026-03-01', '2026-06-30', true],
            'l\'une finit la veille de l\'autre'    => ['2026-01-01', '2026-02-28', '2026-03-01', '2026-06-30', false],
            'ouverte à droite contre une passée'    => ['2026-09-01', null, '2026-01-01', '2026-06-30', false],
            'ouverte à gauche contre une future'    => [null, '2026-06-30', '2026-09-01', '2026-12-31', false],
            'ouverte à droite sur une plus ancienne' => ['2026-01-01', null, '2026-09-01', '2026-12-31', true],
        ];
    }

    #[DataProvider('textesEnseignants')]
    public function test_extraction_des_enseignants_d_un_texte(?string $texte, array $attendu): void
    {
        $this->assertSame($attendu, Conflits::enseignants($texte));
    }

    public static function textesEnseignants(): array
    {
        return [
            'un seul nom'                       => ['HOUNDJI', ['HOUNDJI']],
            'séparés par une barre'             => ['HOUNDJI / AGBO', ['HOUNDJI', 'AGBO']],
            'séparés par virgule et point-virgule' => ['HOUNDJI, AGBO; DOSSOU', ['HOUNDJI', 'AGBO', 'DOSSOU']],
            'civilités retirées'                => ['Dr AGBO / Pr. HOUNDJI / M. DOSSOU', ['AGBO', 'HOUNDJI', 'DOSSOU']],
            'doublon après retrait de la civilité' => ['M. HOUNDJI / HOUNDJI', ['HOUNDJI']],
            'texte vide'                        => ['', []],
            'null'                              => [null, []],
            'séparateurs seuls'                 => [' / ; ', []],
        ];
    }

    #[DataProvider('clesDeNoms')]
    public function test_cle_de_comparaison_d_un_nom(string $nom, string $attendu): void
    {
        $this->assertSame($attendu, Conflits::cle($nom));
    }

    public static function clesDeNoms(): array
    {
        return [
            'civilité et casse'   => ['M. HOUNDJI', 'houndji'],
            'sans civilité'       => ['HOUNDJI', 'houndji'],
            'accents'             => ['Dr Élodie Kpèdé', 'elodie kpede'],
            'ponctuation'         => ['Ag-bo, Jean', 'ag bo jean'],
            'espaces multiples'   => ['  Jean   Paul  ', 'jean paul'],
        ];
    }

    public function test_deux_ecritures_du_meme_enseignant_ont_la_meme_cle(): void
    {
        $this->assertSame(Conflits::cle('M. HOUNDJI'), Conflits::cle('Houndji'));
    }
}
