<?php

namespace Tests\Feature\Presence;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\QrCode;
use App\Models\Ue;
use App\Services\Groupes\GestionGroupes;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le QR Code que présente le délégué.
 *
 * Il prenait la première séance en cours parmi ses cours, sans regarder les
 * groupes : un délégué de G1 pouvait afficher le QR du TD de G2, que les
 * étudiants de G1 auraient scanné pour se voir refuser — le scan exige d'être
 * membre du groupe.
 */
class QrCodeDelegueTest extends TestCase
{
    private Etudiant $delegue;
    private Ec $ec;
    private Groupe $g1;
    private Groupe $g2;

    protected function setUp(): void
    {
        parent::setUp();

        $sfx = Str::upper(Str::random(5));
        $etab = Etablissement::create(['code' => 'QR' . $sfx, 'nom' => 'Faculté Q', 'email' => "qr-{$sfx}@test.local"]);

        AnneeAcademique::where('active', true)->update(['active' => false]);
        $annee = AnneeAcademique::create(['libelle' => '2054-2055', 'date_debut' => '2025-10-01', 'date_fin' => '2055-09-30', 'active' => true]);
        $filiere = Filiere::create(['code' => 'QR' . $sfx, 'intitule' => 'Délégué (L2)', 'niveau' => 'L2', 'etablissement_id' => $etab->id]);

        $ue = Ue::create(['code' => 'UQ' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 3, 'volume_horaire' => 0]);
        $this->ec = Ec::create(['ue_id' => $ue->id, 'code' => 'EQ' . $sfx, 'intitule' => 'EC', 'volume_cm' => 20, 'volume_td' => 10]);

        $this->g1 = Groupe::create(['filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'type' => 'td', 'libelle' => 'G1']);
        $this->g2 = Groupe::create(['filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'type' => 'td', 'libelle' => 'G2']);

        $this->delegue = Etudiant::factory()->pour($filiere, $annee)->create(['est_responsable' => true]);
        $this->delegue->autoEnroll();
        app(GestionGroupes::class)->affecter($this->delegue, $this->g1);
    }

    /** La séance en cours, avec son QR actif. */
    private function seanceEnCours(?Groupe $groupe): Evenement
    {
        $seance = Evenement::factory()->pourEc($this->ec)->fenetreOuverte()->create([
            'type_cours' => $groupe ? 'td' : 'cm',
            'groupe_id'  => $groupe?->id,
        ]);
        QrCode::factory()->create(['evenement_id' => $seance->id, 'actif' => true, 'expire_at' => now()->addMinutes(5)]);

        return $seance;
    }

    private function demanderLeQr(): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->delegue->createToken('mobile-app', ['etudiant'])->plainTextToken)
            ->getJson('/api/student/qrcode/current');
    }

    public function test_le_delegue_affiche_le_qr_du_td_de_son_groupe(): void
    {
        $seance = $this->seanceEnCours($this->g1);

        $this->demanderLeQr()->assertOk()->assertJsonPath('data.evenement.id', $seance->id);
    }

    public function test_le_delegue_ne_recoit_pas_le_qr_du_groupe_voisin(): void
    {
        $this->seanceEnCours($this->g2);

        $reponse = $this->demanderLeQr()->assertStatus(404);

        $this->assertStringContainsString('groupe G2', $reponse->json('message'));
        $this->assertStringContainsString('responsable de ce groupe', $reponse->json('message'));
    }

    public function test_une_seance_de_toute_la_promotion_lui_revient(): void
    {
        $seance = $this->seanceEnCours(null);

        $this->demanderLeQr()->assertOk()->assertJsonPath('data.evenement.id', $seance->id);
    }
}
