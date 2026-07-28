<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Etablissement;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deux événements ne peuvent pas occuper la même salle sur des créneaux qui
 * se chevauchent. Les créneaux consécutifs (10h-12h après 8h-10h) sont, eux,
 * autorisés.
 */
class RoomConflictTest extends TestCase
{
    private string $token;
    private int $ecId;
    private int $salleId;
    private string $demain;
    private int $etabId;

    protected function setUp(): void
    {
        parent::setUp();
        $sfx = Str::random(5);

        $this->token = User::factory()->create([
            'email' => 'room-' . $sfx . '@example.test', 'role' => 'super_admin',
        ])->createToken('t')->plainTextToken;

        $annee = AnneeAcademique::where('active', true)->firstOrFail();
        $filiere = Filiere::create(['code' => 'RC' . $sfx, 'intitule' => 'T', 'niveau' => 'L1']);
        $ue = Ue::create(['code' => 'UE' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 40]);
        $this->ecId = Ec::create(['ue_id' => $ue->id, 'code' => 'EC' . $sfx, 'intitule' => 'EC', 'volume_horaire' => 40])->id;
        $etab = Etablissement::query()->firstOrFail()->id;
        $this->salleId = Salle::create(['nom' => 'Salle ' . $sfx, 'code' => 'S' . $sfx, 'actif' => true, 'etablissement_id' => $etab])->id;
        $this->etabId = $etab;
        $this->demain = today()->addDay()->format('Y-m-d');
    }

    private function creer(array $o = [])
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/admin/evenements', array_merge([
                'ec_id' => $this->ecId, 'date' => $this->demain,
                'heure_debut' => '08:00', 'heure_fin' => '10:00',
                'salle_id' => $this->salleId,
            ], $o));
    }

    public function test_chevauchement_meme_salle_refuse(): void
    {
        $this->creer()->assertStatus(201);
        // 09:00-11:00 chevauche 08:00-10:00 dans la même salle.
        $this->creer(['heure_debut' => '09:00', 'heure_fin' => '11:00'])->assertStatus(422);
    }

    public function test_creneaux_consecutifs_autorises(): void
    {
        $this->creer()->assertStatus(201);
        // 10:00-12:00 juste après 08:00-10:00 : pas de chevauchement.
        $this->creer(['heure_debut' => '10:00', 'heure_fin' => '12:00'])->assertStatus(201);
    }

    public function test_autre_salle_autorisee(): void
    {
        $this->creer()->assertStatus(201);
        $autreSalle = Salle::create(['nom' => 'Autre', 'code' => 'A' . Str::random(4), 'actif' => true, 'etablissement_id' => $this->etabId])->id;
        $this->creer(['salle_id' => $autreSalle])->assertStatus(201);
    }
}
