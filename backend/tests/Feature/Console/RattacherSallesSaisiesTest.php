<?php

namespace Tests\Feature\Console;

use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reprise des séances dont la salle n'est qu'un nom saisi (salles:rattacher).
 *
 * Les assertions portent sur les enregistrements créés ici, jamais sur des
 * totaux : la commande agit sur toute la base.
 */
class RattacherSallesSaisiesTest extends TestCase
{
    private Etablissement $etab;
    private Filiere $filiere;
    private Ec $ec;
    private string $sfx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::random(5);
        $this->etab = Etablissement::create([
            'code'  => 'RS' . $this->sfx,
            'nom'   => 'Établissement ' . $this->sfx,
            'email' => strtolower("rs.{$this->sfx}@test.local"),
        ]);
        $this->filiere = Filiere::create([
            'code' => 'FRS' . $this->sfx, 'intitule' => 'Filière', 'niveau' => 'L1',
            'etablissement_id' => $this->etab->id,
        ]);
        $ue = Ue::create([
            'code' => 'UERS' . $this->sfx, 'intitule' => 'UE', 'filiere_id' => $this->filiere->id,
            'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 40,
        ]);
        $this->ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECRS' . $this->sfx, 'intitule' => 'EC', 'volume_horaire' => 40]);
    }

    private function seance(string $salle, ?Filiere $filiere = null): Evenement
    {
        return Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => ($filiere ?? $this->filiere)->id,
            'annee_id'    => $this->ec->ue->annee_id,
            'date'        => today()->addDay(),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'salle'       => $salle,
            'statut'      => 'planifie',
        ]);
    }

    private function sallesNommees(string $nom)
    {
        return Salle::where('etablissement_id', $this->etab->id)->get()
            ->filter(fn (Salle $s) => Str::lower(preg_replace('/\s+/', ' ', trim($s->nom))) === Str::lower($nom));
    }

    public function test_la_simulation_ne_modifie_rien(): void
    {
        $e = $this->seance('Labo Zeta');

        $this->artisan('salles:rattacher', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($e->fresh()->salle_id);
        $this->assertCount(0, $this->sallesNommees('labo zeta'));
    }

    public function test_cree_une_salle_par_nom_et_y_rattache_les_seances(): void
    {
        // Deux saisies d'une même salle, casse et espaces différents : une seule
        // salle doit en résulter.
        $a = $this->seance('Labo Zeta');
        $b = $this->seance('  labo   zeta ');

        $this->artisan('salles:rattacher')->assertSuccessful();

        $salles = $this->sallesNommees('labo zeta');
        $this->assertCount(1, $salles);
        $salle = $salles->first();

        $this->assertSame($salle->id, $a->fresh()->salle_id);
        $this->assertSame($salle->id, $b->fresh()->salle_id);
        // Le nom affiché est réaligné sur celui de la salle.
        $this->assertSame($salle->nom, $b->fresh()->salle);
        // Aucune coordonnée inventée : la salle vérifie par QR seul.
        $this->assertFalse($salle->verifieGps());
        $this->assertFalse($salle->verifieWifi());
    }

    public function test_reutilise_la_salle_existante_du_meme_nom(): void
    {
        $existante = Salle::create([
            'etablissement_id' => $this->etab->id, 'nom' => 'Amphi Quartz',
            'code' => 'AQ' . $this->sfx, 'actif' => true,
        ]);
        $e = $this->seance('amphi quartz');

        $this->artisan('salles:rattacher')->assertSuccessful();

        $this->assertSame($existante->id, $e->fresh()->salle_id);
        $this->assertCount(1, $this->sallesNommees('amphi quartz'));
    }

    public function test_une_seconde_execution_ne_cree_rien(): void
    {
        $this->seance('Labo Zeta');
        $this->artisan('salles:rattacher')->assertSuccessful();
        $apres = Salle::count();

        $this->artisan('salles:rattacher')->assertSuccessful();

        $this->assertSame($apres, Salle::count());
    }

    public function test_une_seance_sans_etablissement_est_ecartee_sans_bloquer_les_autres(): void
    {
        // Une filière peut ne pas être rattachée à un établissement : ses
        // séances ne peuvent pas recevoir de salle, mais la reprise des autres
        // doit aboutir.
        $sansEtab = Filiere::create(['code' => 'FSE' . $this->sfx, 'intitule' => 'Sans', 'niveau' => 'L1']);
        $ecartee = $this->seance('Salle perdue', $sansEtab);
        $traitee = $this->seance('Labo Zeta');

        $this->artisan('salles:rattacher')->assertSuccessful();

        $this->assertNull($ecartee->fresh()->salle_id);
        $this->assertNotNull($traitee->fresh()->salle_id);
    }
}
