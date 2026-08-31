<?php

namespace Tests\Unit;

use App\Services\Schedule\NormalisateurCreneau;
use PHPUnit\Framework\TestCase;

/**
 * Normalisation d'un créneau extrait.
 *
 * Régression : l'extraction exigeait une date calendaire YYYY-MM-DD, puis
 * écartait en silence tout créneau qui n'en avait pas. L'emploi du temps
 * universitaire habituel — vérifié sur un fichier réel de l'IFRI présent dans le
 * dépôt — est hebdomadaire : colonnes « Lundi … Samedi », lignes « 8h – 13h ».
 * Les quatre EDT hebdomadaires du corpus rendaient donc VIDE, mesuré à 0 %.
 */
class NormalisateurCreneauTest extends TestCase
{
    private NormalisateurCreneau $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new NormalisateurCreneau();
    }

    public function test_un_creneau_hebdomadaire_est_accepte(): void
    {
        $r = $this->n->normaliser([
            'ec' => 'Algorithmique', 'jour' => 'Lundi',
            'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        $this->assertTrue($r['ok'], 'Un créneau hebdomadaire doit être accepté.');
        $this->assertSame(1, $r['creneau']['jour_semaine']);
        $this->assertNull($r['creneau']['date'], 'Aucune date ne doit être inventée.');
    }

    public function test_un_creneau_date_reste_accepte(): void
    {
        $r = $this->n->normaliser([
            'ec' => 'Algorithmique', 'date' => '2026-03-09',
            'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        $this->assertTrue($r['ok']);
        // Le 9 mars 2026 est un lundi : le jour se déduit de la date.
        $this->assertSame(1, $r['creneau']['jour_semaine']);
        $this->assertSame('2026-03-09', $r['creneau']['date']);
    }

    /**
     * Notation des fiches réelles : « 8h – 13h ». L'ancien pipeline n'acceptait
     * que HH:mm.
     */
    public function test_les_heures_a_la_francaise_sont_comprises(): void
    {
        $cas = [
            ['8h', '13h', '08:00', '13:00'],
            ['8h30', '10h45', '08:30', '10:45'],
            ['8 h 30', '13 h', '08:30', '13:00'],
            ['08.00', '10.30', '08:00', '10:30'],
            ['8', '13', '08:00', '13:00'],
        ];

        foreach ($cas as [$d, $f, $attD, $attF]) {
            $r = $this->n->normaliser(['ec' => 'X', 'jour' => 'mardi', 'heure_debut' => $d, 'heure_fin' => $f]);

            $this->assertTrue($r['ok'], "« {$d} – {$f} » doit être compris.");
            $this->assertSame($attD, $r['creneau']['heure_debut']);
            $this->assertSame($attF, $r['creneau']['heure_fin']);
        }
    }

    public function test_les_variantes_de_jour_sont_comprises(): void
    {
        foreach (['Lundi' => 1, 'lundi' => 1, 'LUN' => 1, 'Mercredi' => 3, 'MERCREDI' => 3,
                  'mer' => 3, 'Samedi' => 6, 'Lundi 09/03' => 1, '4' => 4] as $entree => $attendu) {
            $r = $this->n->normaliser([
                'ec' => 'X', 'jour' => $entree, 'heure_debut' => '08:00', 'heure_fin' => '10:00',
            ]);

            $this->assertTrue($r['ok'], "« {$entree} » doit être compris.");
            $this->assertSame($attendu, $r['creneau']['jour_semaine'], "« {$entree} »");
        }
    }

    public function test_les_parasites_typographiques_sont_nettoyes(): void
    {
        $r = $this->n->normaliser([
            // Espace insécable, apostrophe courbe, espaces multiples : ce que
            // produit un document Word.
            'ec'    => "  Concepts et Applications de\u{00A0}l\u{2019}Apprentissage   Automatique ",
            'jour'  => 'Jeudi', 'heure_debut' => '14h', 'heure_fin' => '18h',
            'salle' => " Zone\u{00A0}Master A2 ",
        ]);

        $this->assertTrue($r['ok']);
        $this->assertSame("Concepts et Applications de l'Apprentissage Automatique", $r['creneau']['ec_libelle']);
        $this->assertSame('Zone Master A2', $r['creneau']['salle']);
    }

    public function test_plusieurs_enseignants_sont_separes_et_les_civilites_retirees(): void
    {
        $r = $this->n->normaliser([
            'ec' => 'X', 'jour' => 'Jeudi', 'heure_debut' => '14h', 'heure_fin' => '18h',
            'enseignant' => 'M. Ratheil HOUNDJI / Mme. Mélène TONOU',
        ]);

        $this->assertTrue($r['ok']);
        $this->assertSame(['Ratheil HOUNDJI', 'Mélène TONOU'], $r['creneau']['enseignants']);
    }

    // ── Ce qui doit être REFUSÉ, avec un motif exploitable ──────────────

    public function test_un_creneau_sans_jour_ni_date_est_refuse_avec_son_motif(): void
    {
        $r = $this->n->normaliser(['ec' => 'X', 'heure_debut' => '08:00', 'heure_fin' => '10:00']);

        $this->assertFalse($r['ok']);
        $this->assertSame('jour', $r['champ']);
        $this->assertStringContainsString('Jour introuvable', $r['motif']);
    }

    public function test_un_creneau_sans_cours_est_refuse(): void
    {
        $r = $this->n->normaliser(['jour' => 'Lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00']);

        $this->assertFalse($r['ok']);
        $this->assertSame('ec', $r['champ']);
    }

    public function test_des_heures_inversees_sont_refusees(): void
    {
        $r = $this->n->normaliser([
            'ec' => 'X', 'jour' => 'Lundi', 'heure_debut' => '14:00', 'heure_fin' => '10:00',
        ]);

        $this->assertFalse($r['ok']);
        $this->assertSame('heure', $r['champ']);
    }

    /**
     * « Lundi » n'est pas une date. Carbon::parse l'accepterait pourtant et
     * fabriquerait une date arbitraire — un créneau inventé, donc un cours
     * fantôme et des absences pour des étudiants réels.
     */
    public function test_un_nom_de_jour_n_est_jamais_lu_comme_une_date(): void
    {
        $r = $this->n->normaliser([
            'ec' => 'X', 'jour' => 'Lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        $this->assertTrue($r['ok']);
        $this->assertNull($r['creneau']['date']);
    }

    public function test_une_date_mal_formee_n_est_pas_devinee(): void
    {
        $r = $this->n->normaliser([
            'ec' => 'X', 'date' => 'mars 2026', 'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        $this->assertFalse($r['ok'], 'Une date non reconnue ne doit pas être devinée.');
        $this->assertSame('jour', $r['champ']);
    }
}
