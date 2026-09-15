<?php

namespace Tests\Feature\Console;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Ue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La génération planifiée tourne à 00:05, sans date ni --force. Elle refusait
 * « aujourd'hui », lu comme minuit donc déjà passé : aucune séance n'était
 * générée par le planificateur.
 */
class GenerationQuotidienneTest extends TestCase
{
    public function test_la_generation_de_00h05_genere_les_seances_du_jour(): void
    {
        $this->travelTo(today()->setTime(0, 5));

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $annee = AnneeAcademique::create(['libelle' => '2041-2042', 'date_debut' => '2041-10-01', 'date_fin' => '2042-09-30', 'active' => true]);
        $filiere = Filiere::create(['code' => 'GQ' . Str::random(5), 'intitule' => 'Génération (L1)', 'niveau' => 'L1']);
        $ec = Ec::factory()->create(['ue_id' => Ue::factory()->pour($filiere, $annee)->create(['semestre' => 1])->id, 'volume_horaire' => 40]);

        EmploiDuTemps::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $annee->id,
            'jour_semaine' => today()->dayOfWeekIso, 'heure_debut' => '10:00:00', 'heure_fin' => '12:00:00',
            'salle_libelle' => 'Amphi', 'type_cours' => 'cours',
        ]);

        $this->ouvrirSemestres($annee);

        $this->artisan('events:generate-from-schedule', ['--days' => 1])
            ->doesntExpectOutputToContain('dans le passé')
            ->assertSuccessful();

        $this->assertTrue(Evenement::where('ec_id', $ec->id)->whereDate('date', today())->exists());
    }

    public function test_une_date_anterieure_reste_refusee_sans_force(): void
    {
        $this->artisan('events:generate-from-schedule', ['--date' => today()->subDay()->toDateString(), '--days' => 1])
            ->expectsOutputToContain('dans le passé')
            ->assertFailed();
    }
}
