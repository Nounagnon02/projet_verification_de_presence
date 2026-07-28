<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Services\AttendanceRateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le taux de présence doit se baser sur les étudiants réellement inscrits à
 * l'EC de chaque événement, et non sur tous les étudiants × tous les
 * événements.
 */
class AttendanceRateTest extends TestCase
{
    public function test_denominateur_base_sur_les_inscrits_reels(): void
    {
        $sfx = Str::random(5);
        $annee = AnneeAcademique::where('active', true)->firstOrFail();
        $filiere = Filiere::create(['code' => 'AR' . $sfx, 'intitule' => 'Test', 'niveau' => 'L1']);
        $ue = Ue::create(['code' => 'UE' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 20]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'EC' . $sfx, 'intitule' => 'EC', 'volume_horaire' => 20]);

        $evenement = Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $annee->id,
            'date' => today()->subDay()->format('Y-m-d'),
            'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00',
            'salle' => 'T', 'statut' => 'termine',
        ]);

        // 2 étudiants inscrits à l'EC, 1 seul présent.
        $inscrits = collect(range(1, 2))->map(function ($i) use ($filiere, $annee, $ec, $sfx) {
            $e = Etudiant::create([
                'nom' => 'AR', 'prenom' => "E$i", 'matricule' => "AR-$sfx-$i",
                'filiere_id' => $filiere->id, 'annee_id' => $annee->id,
                'email' => "ar-$sfx-$i@example.test", 'identifiant_unique' => "AR_{$sfx}_$i",
            ]);
            $e->ecs()->syncWithoutDetaching([$ec->id => ['annee_id' => $annee->id]]);
            return $e;
        });

        // Un 3e étudiant NON inscrit à cet EC ne doit pas gonfler le dénominateur.
        Etudiant::create([
            'nom' => 'AR', 'prenom' => 'HORS', 'matricule' => "AR-$sfx-x",
            'filiere_id' => $filiere->id, 'annee_id' => $annee->id,
            'email' => "ar-$sfx-x@example.test", 'identifiant_unique' => "AR_{$sfx}_x",
        ]);

        Presence::create([
            'etudiant_id' => $inscrits[0]->id, 'evenement_id' => $evenement->id,
            'heure_scan' => Carbon::parse($evenement->date->format('Y-m-d') . ' 08:05:00'),
            'device_fingerprint' => 'd', 'statut' => 'valide',
        ]);

        $svc = app(AttendanceRateService::class);
        $filtre = fn ($q) => $q->where('e.id', $evenement->id);

        $this->assertSame(2, $svc->expected($filtre), 'Attendus = 2 inscrits (pas 3).');
        $this->assertSame(1, $svc->recorded($filtre), 'Présences validées = 1.');
        $this->assertSame(50.0, $svc->rate($filtre));
    }
}
