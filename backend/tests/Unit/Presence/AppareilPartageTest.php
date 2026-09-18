<?php

namespace Tests\Unit\Presence;

use App\Models\Anomaly;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\User;
use App\Services\Presence\AppareilPartage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Détection d'appareil partagé — nécessite la base : la décision porte sur les
 * présences déjà enregistrées pour l'événement.
 */
class AppareilPartageTest extends TestCase
{
    private Evenement $evenement;

    protected function setUp(): void
    {
        parent::setUp();

        $ue = $this->uneUe();
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'AP-' . Str::random(5), 'intitule' => 'EC', 'volume_horaire' => 20]);
        $this->evenement = Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $ue->filiere_id, 'annee_id' => $ue->annee_id,
            'date' => today()->format('Y-m-d'), 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'planifie',
        ]);
    }

    private function etudiant(): Etudiant
    {
        $sfx = Str::random(6);

        return Etudiant::create([
            'nom' => 'AP', 'prenom' => $sfx, 'matricule' => 'AP-' . $sfx,
            'filiere_id' => $this->evenement->filiere_id, 'annee_id' => $this->evenement->annee_id,
            'email' => strtolower("ap-{$sfx}@example.test"), 'identifiant_unique' => 'AP_' . $sfx,
        ]);
    }

    public function test_premier_scan_d_un_appareil_est_valide(): void
    {
        $statut = AppareilPartage::arbitrer($this->evenement, $this->etudiant(), 'device-seul');

        $this->assertSame('valide', $statut);
        $this->assertSame(0, Anomaly::where('type', 'appareil_partage')->count());
    }

    public function test_second_etudiant_sur_le_meme_appareil_est_marque_suspect_et_journalise(): void
    {
        $premier = $this->etudiant();
        Presence::create([
            'etudiant_id' => $premier->id, 'evenement_id' => $this->evenement->id,
            'heure_scan' => now(), 'device_fingerprint' => 'device-partage', 'statut' => 'valide',
        ]);

        $second = $this->etudiant();
        $statut = AppareilPartage::arbitrer($this->evenement, $second, 'device-partage');

        $this->assertSame('suspect', $statut);
        $this->assertDatabaseHas('anomalies', [
            'etudiant_id' => $second->id, 'type' => 'appareil_partage', 'severity' => 'high',
        ]);
        // Le premier scan bascule aussi : c'est le groupe entier qu'il faut
        // voir pour arbitrer, pas seulement le dernier arrivé.
        $this->assertDatabaseHas('presences', ['etudiant_id' => $premier->id, 'statut' => 'suspect']);
    }

    public function test_une_presence_deja_arbitree_par_un_administrateur_n_est_pas_rouverte(): void
    {
        $premier = $this->etudiant();
        Presence::create([
            'etudiant_id' => $premier->id, 'evenement_id' => $this->evenement->id,
            'heure_scan' => now(), 'device_fingerprint' => 'device-partage',
            'statut' => 'valide', 'validated_by' => User::factory()->create()->id, 'validated_at' => now(),
        ]);

        AppareilPartage::arbitrer($this->evenement, $this->etudiant(), 'device-partage');

        $this->assertDatabaseHas('presences', ['etudiant_id' => $premier->id, 'statut' => 'valide']);
    }

    public function test_deux_appareils_distincts_restent_tous_deux_valides(): void
    {
        $a = $this->etudiant();
        Presence::create([
            'etudiant_id' => $a->id, 'evenement_id' => $this->evenement->id,
            'heure_scan' => now(), 'device_fingerprint' => 'device-a', 'statut' => 'valide',
        ]);

        $statut = AppareilPartage::arbitrer($this->evenement, $this->etudiant(), 'device-b');

        $this->assertSame('valide', $statut);
    }
}
