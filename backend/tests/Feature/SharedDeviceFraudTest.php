<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\QrCode;
use App\Models\Ue;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fraude « un seul téléphone pour toute la classe » : un même appareil
 * utilisé par plusieurs étudiants pour le même événement doit marquer les
 * scans suivants comme suspects et créer une anomalie.
 */
class SharedDeviceFraudTest extends TestCase
{
    private Ec $ec;
    private Evenement $evenement;
    private string $token;
    private AnneeAcademique $annee;

    private function scanChallenge(string $device): string
    {
        return hash('sha256', $device . ':' . (Config::get('app.key') ?? 'uac-presence-secret'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $sfx = Str::random(5);
        $this->annee = AnneeAcademique::where('active', true)->firstOrFail();

        $filiere = Filiere::create(['code' => 'SD' . $sfx, 'intitule' => 'Test', 'niveau' => 'L3']);
        $ue = Ue::create(['code' => 'UE' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id, 'semestre' => 1, 'volume_horaire' => 30]);
        $this->ec = Ec::create(['ue_id' => $ue->id, 'code' => 'EC' . $sfx, 'intitule' => 'EC', 'volume_horaire' => 30]);

        $this->evenement = Evenement::create([
            'ec_id' => $this->ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $this->annee->id,
            'date' => today()->format('Y-m-d'),
            'heure_debut' => Carbon::now()->subHour()->format('H:i:s'),
            'heure_fin'   => Carbon::now()->addHour()->format('H:i:s'),
            'salle' => 'Test', 'statut' => 'en_cours',
        ]);

        $this->token = (string) Str::uuid();
        QrCode::create(['evenement_id' => $this->evenement->id, 'token' => $this->token, 'expire_at' => Carbon::now()->addMinutes(5), 'actif' => true]);
    }

    private function etudiantInscrit(string $sfx): Etudiant
    {
        $e = Etudiant::create([
            'nom' => 'SD', 'prenom' => $sfx, 'matricule' => 'SD-' . $sfx,
            'filiere_id' => $this->evenement->filiere_id, 'annee_id' => $this->annee->id,
            'email' => 'sd-' . $sfx . '@example.test', 'identifiant_unique' => 'SD_' . $sfx,
        ]);
        $e->ecs()->syncWithoutDetaching([$this->ec->id => ['annee_id' => $this->annee->id]]);
        return $e;
    }

    private function scan(Etudiant $e, string $device): \Illuminate\Testing\TestResponse
    {
        // Le scan invalide le QR : on le régénère avant chaque scan pour
        // enchaîner plusieurs étudiants sur le même événement.
        QrCode::where('evenement_id', $this->evenement->id)->update(['actif' => false]);
        $token = (string) Str::uuid();
        QrCode::create(['evenement_id' => $this->evenement->id, 'token' => $token, 'expire_at' => Carbon::now()->addMinutes(5), 'actif' => true]);

        return $this->postJson('/api/presence/scan', [
            'identifiant_unique' => $e->identifiant_unique,
            'token'              => $token,
            'device_fingerprint' => $device,
            'scan_challenge'     => $this->scanChallenge($device),
        ]);
    }

    public function test_deuxieme_etudiant_sur_le_meme_appareil_est_marque_suspect(): void
    {
        $device = 'device-partage-xyz';
        $a = $this->etudiantInscrit('A' . Str::random(4));
        $b = $this->etudiantInscrit('B' . Str::random(4));

        // Premier étudiant : valide.
        $this->scan($a, $device)->assertStatus(201);
        $this->assertDatabaseHas('presences', ['etudiant_id' => $a->id, 'evenement_id' => $this->evenement->id, 'statut' => 'valide']);

        // Second étudiant, même appareil : enregistré mais suspect + anomalie.
        $this->scan($b, $device)->assertStatus(201);
        $this->assertDatabaseHas('presences', ['etudiant_id' => $b->id, 'evenement_id' => $this->evenement->id, 'statut' => 'suspect']);
        $this->assertDatabaseHas('anomalies', ['type' => 'appareil_partage', 'etudiant_id' => $b->id]);
    }

    public function test_deux_etudiants_sur_leurs_propres_appareils_restent_valides(): void
    {
        $a = $this->etudiantInscrit('C' . Str::random(4));
        $b = $this->etudiantInscrit('D' . Str::random(4));

        $this->scan($a, 'device-a-' . Str::random(4))->assertStatus(201);
        $this->scan($b, 'device-b-' . Str::random(4))->assertStatus(201);

        $this->assertDatabaseHas('presences', ['etudiant_id' => $b->id, 'statut' => 'valide']);
    }
}
