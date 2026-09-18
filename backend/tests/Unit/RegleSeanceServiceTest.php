<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Ec;
use App\Services\RegleSeanceService;
use App\Support\TypeCours;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les règles de calcul d'une séance, éprouvées SANS base de données.
 *
 * Elles ne l'étaient jusqu'ici qu'à travers HTTP (EventAndYearRulesTest, 17
 * cas, chacun avec un établissement complet en base) : ajouter un cas limite
 * du calcul du volume coûtait un cursus entier. Ces fonctions sont pures.
 */
class RegleSeanceServiceTest extends TestCase
{
    private function ec(int $cm, int $td, int $tp, int $tdTp): Ec
    {
        $ec = new Ec();
        $ec->volume_cm = $cm;
        $ec->volume_td = $td;
        $ec->volume_tp = $tp;
        $ec->volume_td_tp = $tdTp;

        return $ec;
    }

    // ── duree() ──────────────────────────────────────────────────────────────

    #[DataProvider('durees')]
    public function test_duree_d_un_creneau(string $debut, string $fin, float $attendu): void
    {
        $this->assertEqualsWithDelta($attendu, RegleSeanceService::duree($debut, $fin), 0.0001);
    }

    public static function durees(): array
    {
        return [
            'deux heures pleines'        => ['08:00', '10:00', 2.0],
            'avec secondes (colonne TIME)' => ['08:00:00', '09:30:00', 1.5],
            'quart d\'heure'             => ['14:15', '15:00', 0.75],
            'heure sans zéro initial'    => ['8:00', '9:00', 1.0],
            'à cheval sur midi'          => ['11:30', '13:00', 1.5],
        ];
    }

    // ── format() ─────────────────────────────────────────────────────────────

    #[DataProvider('formats')]
    public function test_format_lisible_pour_un_message_humain(float $heures, string $attendu): void
    {
        $this->assertSame($attendu, RegleSeanceService::format($heures));
    }

    public static function formats(): array
    {
        return [
            'heures pleines'      => [2.0, '2h'],
            'heure et demie'      => [1.5, '1h30'],
            'moins d\'une heure'  => [0.75, '0h45'],
            'vingt minutes'       => [1 / 3, '0h20'],
            'arrondi à la minute' => [2.9999, '3h'],
            'zéro'                => [0.0, '0h'],
        ];
    }

    // ── restantesDepuis() : la réserve TP/TD ─────────────────────────────────

    public function test_sans_rien_de_pris_chaque_type_recoit_sa_part_et_la_reserve_entiere(): void
    {
        $restantes = RegleSeanceService::restantesDepuis($this->ec(cm: 10, td: 4, tp: 6, tdTp: 5), []);

        $this->assertEqualsWithDelta(10.0, $restantes[TypeCours::CM], 0.0001);
        $this->assertEqualsWithDelta(9.0, $restantes[TypeCours::TD], 0.0001, 'TD : 4 h propres + 5 h de réserve.');
        $this->assertEqualsWithDelta(11.0, $restantes[TypeCours::TP], 0.0001, 'TP : 6 h propres + 5 h de réserve.');
    }

    public function test_un_debordement_de_td_entame_la_reserve_partagee_avec_les_tp(): void
    {
        // 6 h de TD pour 4 h propres : 2 h puisées dans la réserve de 5 h.
        $restantes = RegleSeanceService::restantesDepuis(
            $this->ec(cm: 10, td: 4, tp: 6, tdTp: 5),
            [TypeCours::TD => 6.0],
        );

        $this->assertEqualsWithDelta(3.0, $restantes[TypeCours::TD], 0.0001, 'Plus de TD propre : il ne reste que les 3 h de réserve.');
        $this->assertEqualsWithDelta(9.0, $restantes[TypeCours::TP], 0.0001, 'Les TP perdent 2 h de réserve : 6 + 3.');
        $this->assertEqualsWithDelta(10.0, $restantes[TypeCours::CM], 0.0001);
    }

    public function test_la_reserve_ne_devient_jamais_negative(): void
    {
        // 12 h de TD + 9 h de TP pour 4 + 6 propres et 5 de réserve : elle est vidée deux fois.
        $restantes = RegleSeanceService::restantesDepuis(
            $this->ec(cm: 10, td: 4, tp: 6, tdTp: 5),
            [TypeCours::TD => 12.0, TypeCours::TP => 9.0],
        );

        $this->assertEqualsWithDelta(0.0, $restantes[TypeCours::TD], 0.0001);
        $this->assertEqualsWithDelta(0.0, $restantes[TypeCours::TP], 0.0001);
    }

    public function test_un_cm_depasse_donne_zero_pas_un_negatif(): void
    {
        $restantes = RegleSeanceService::restantesDepuis($this->ec(cm: 10, td: 0, tp: 0, tdTp: 0), [TypeCours::CM => 12.0]);

        $this->assertEqualsWithDelta(0.0, $restantes[TypeCours::CM], 0.0001);
    }

    // ── prisDepuis() : chaque groupe reçoit tout le volume ───────────────────

    public function test_le_cm_reunit_toujours_la_promotion_meme_avec_un_groupe(): void
    {
        $pris = RegleSeanceService::prisDepuis([
            ['type' => TypeCours::CM, 'groupe_id' => null, 'heures' => 2.0],
            ['type' => TypeCours::CM, 'groupe_id' => 5, 'heures' => 1.0],
        ]);

        $this->assertEqualsWithDelta(3.0, $pris[TypeCours::CM], 0.0001);
    }

    public function test_sans_groupe_vise_les_td_comptent_pour_le_groupe_le_plus_avance(): void
    {
        $pris = RegleSeanceService::prisDepuis([
            ['type' => TypeCours::TD, 'groupe_id' => 1, 'heures' => 2.0],
            ['type' => TypeCours::TD, 'groupe_id' => 2, 'heures' => 3.0],
            ['type' => TypeCours::TP, 'groupe_id' => null, 'heures' => 1.0],
        ]);

        $this->assertEqualsWithDelta(3.0, $pris[TypeCours::TD], 0.0001, 'Une séance de toute la promotion compte pour chaque groupe : le plus avancé fait foi.');
        $this->assertEqualsWithDelta(1.0, $pris[TypeCours::TP], 0.0001);
    }

    public function test_pour_un_groupe_on_compte_la_promotion_et_ses_propres_seances(): void
    {
        $pris = RegleSeanceService::prisDepuis([
            ['type' => TypeCours::TD, 'groupe_id' => null, 'heures' => 1.0],
            ['type' => TypeCours::TD, 'groupe_id' => 1, 'heures' => 2.0],
            ['type' => TypeCours::TD, 'groupe_id' => 2, 'heures' => 5.0],
        ], groupeId: 1, pourType: TypeCours::TD);

        $this->assertEqualsWithDelta(3.0, $pris[TypeCours::TD], 0.0001, '1 h de promotion + 2 h du groupe 1 ; les 5 h du groupe 2 ne le concernent pas.');
    }

    public function test_une_evaluation_ne_consomme_aucun_volume(): void
    {
        $pris = RegleSeanceService::prisDepuis([
            ['type' => TypeCours::EVALUATION, 'groupe_id' => null, 'heures' => 3.0],
        ]);

        $this->assertSame([TypeCours::CM => 0.0, TypeCours::TD => 0.0, TypeCours::TP => 0.0], $pris);
    }
}
