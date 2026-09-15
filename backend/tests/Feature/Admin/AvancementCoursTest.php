<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le statut d'un cours se lit dans ses séances terminées, sans commande
 * nocturne : le planificateur arrêté, il dérivait en silence. Un TD est fait
 * quand chaque groupe l'a suivi.
 */
class AvancementCoursTest extends TestCase
{
    private string $jeton;
    private AnneeAcademique $annee;
    private Ue $ue;
    private Ec $ec;
    private Groupe $g1;
    private Groupe $g2;

    protected function setUp(): void
    {
        parent::setUp();

        $sfx = Str::upper(Str::random(5));
        $etab = Etablissement::create(['code' => 'AV' . $sfx, 'nom' => 'Faculté A', 'email' => "av-{$sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "avancement-{$sfx}@test.local"])->createToken('t')->plainTextToken;

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $this->annee = AnneeAcademique::create(['libelle' => '2050-2051', 'date_debut' => '2025-10-01', 'date_fin' => '2051-09-30', 'active' => true]);
        $filiere = Filiere::create(['code' => 'AV' . $sfx, 'intitule' => 'Avancement (L2)', 'niveau' => 'L2', 'etablissement_id' => $etab->id]);

        $this->ue = Ue::create(['code' => 'UA' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $this->ec = Ec::create(['ue_id' => $this->ue->id, 'code' => 'EA' . $sfx, 'intitule' => 'EC', 'volume_cm' => 4, 'volume_td' => 4]);

        $this->g1 = Groupe::create(['filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'type' => 'td', 'libelle' => 'G1']);
        $this->g2 = Groupe::create(['filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'type' => 'td', 'libelle' => 'G2']);
    }

    private function api(string $uri): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->jeton)->getJson("/api/admin/{$uri}")->assertOk();
    }

    private function statutEc(): ?string
    {
        return collect($this->api('ecs')->json('data'))->firstWhere('id', $this->ec->id)['statut'] ?? null;
    }

    private function statutUe(): ?string
    {
        return collect($this->api("ues?annee_id={$this->annee->id}")->json('data'))->firstWhere('id', $this->ue->id)['statut'] ?? null;
    }

    private function seanceTerminee(string $type, ?Groupe $groupe, int $heures): void
    {
        Evenement::factory()->pourEc($this->ec)->passe()->create([
            'type_cours' => $type, 'groupe_id' => $groupe?->id,
            'heure_debut' => '08:00:00', 'heure_fin' => sprintf('%02d:00:00', 8 + $heures),
        ]);
    }

    public function test_le_statut_suit_les_seances_terminees_groupe_par_groupe(): void
    {
        $this->assertSame('non_demarre', $this->statutEc());

        $this->seanceTerminee('cm', null, 4);
        $this->seanceTerminee('td', $this->g1, 4);

        $this->assertSame('en_cours', $this->statutEc(), "G2 n'a pas encore eu son TD.");
        $this->assertSame('en_cours', $this->statutUe());

        $this->seanceTerminee('td', $this->g2, 4);

        $this->assertSame('termine', $this->statutEc());
        $this->assertSame('termine', $this->statutUe());
    }

    public function test_ni_une_evaluation_ni_une_seance_a_venir_ne_font_avancer_le_cours(): void
    {
        $this->seanceTerminee('evaluation', null, 2);
        Evenement::factory()->pourEc($this->ec)->create(['date' => today()->addWeek()->toDateString(), 'statut' => 'planifie']);

        $this->assertSame('non_demarre', $this->statutEc());
        $this->assertSame('non_demarre', $this->ec->fresh()->statut, 'Un EC isolé calcule son statut à la demande.');
    }
}
