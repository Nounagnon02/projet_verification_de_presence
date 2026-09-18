<?php

namespace Tests\Feature\Admin;

use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Fermeture;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Programme;
use App\Models\Salle;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Garde structurel du cloisonnement (RestrictModelsToEtablissement) — surface
 * complète.
 *
 * Chacun des modèles classés dans le middleware est exercé au moins une fois,
 * sur une route qui le lie par la route, avec un admin d'une AUTRE faculté.
 * Aucune réponse 2xx n'est admise. Complète IsolationEcrituresEtRapportsTest
 * (Evenement, Filiere, Etudiant, exports) sans le redoubler.
 *
 * Non couvertes ici, volontairement — même modèle déjà exercé ailleurs par un
 * autre chemin, ou route non pertinente pour ce garde :
 *   - Etudiant, Evenement, Filiere : IsolationEcrituresEtRapportsTest,
 *     EtablissementIsolationTest.
 *   - AnneeAcademique, Notification, SupportTicket : exemptés du garde (voir
 *     RestrictModelsToEtablissement::MODELES_EXEMPTES), testés par ailleurs
 *     (NotificationScopingTest, TicketScopingTest) sur leur propre mécanisme.
 */
class CloisonnementModelesTest extends TestCase
{
    private string $jetonA;

    protected function setUp(): void
    {
        parent::setUp();

        $etabA = Etablissement::factory()->create();
        $this->jetonA = User::factory()->faculteAdmin($etabA->id)->create()->createToken('t')->plainTextToken;
    }

    private function commeAdminA()
    {
        return $this->withToken($this->jetonA);
    }

    private function filiereB(): Filiere
    {
        $etabB = Etablissement::factory()->create();
        $sfx = Str::random(5);

        return Filiere::create(['code' => 'CB' . $sfx, 'intitule' => 'Filière B', 'niveau' => 'L1', 'etablissement_id' => $etabB->id]);
    }

    // ─── Ec ────────────────────────────────────────────────────────────────

    public function test_modifier_l_ec_d_une_autre_faculte_est_refuse(): void
    {
        $filiere = $this->filiereB();
        $ue = Ue::create(['code' => 'UEB' . Str::random(4), 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECB' . Str::random(4), 'intitule' => 'EC', 'volume_horaire' => 20]);

        $this->commeAdminA()->putJson("/api/admin/ecs/{$ec->id}", ['intitule' => 'Modifié'])->assertNotFound();
        $this->commeAdminA()->deleteJson("/api/admin/ecs/{$ec->id}")->assertNotFound();
    }

    public function test_desinscrire_un_etudiant_d_un_ec_d_une_autre_faculte_est_refuse(): void
    {
        // L'étudiant appartient à la faculté DE L'APPELANT (première
        // vérification satisfaite) ; c'est l'EC qui appartient à une autre.
        // Le garde doit refuser sur CE SEUL paramètre, pas seulement le premier.
        $etabA = Etablissement::factory()->create();
        $sfx = Str::random(6);
        $etudiantA = Etudiant::create([
            'nom' => 'CLOISON', 'prenom' => $sfx, 'matricule' => 'CM-' . $sfx,
            'filiere_id' => Filiere::create(['code' => 'CMA' . $sfx, 'intitule' => 'A', 'niveau' => 'L1', 'etablissement_id' => $etabA->id])->id,
            'annee_id' => $this->anneeActive()->id, 'email' => strtolower("cm-{$sfx}@example.test"), 'identifiant_unique' => 'CM_' . $sfx,
        ]);
        $jetonA = User::factory()->faculteAdmin($etabA->id)->create()->createToken('t')->plainTextToken;

        $filiereB = $this->filiereB();
        $ueB = Ue::create(['code' => 'UEB2' . Str::random(4), 'intitule' => 'UE', 'filiere_id' => $filiereB->id, 'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30]);
        $ecB = Ec::create(['ue_id' => $ueB->id, 'code' => 'ECB2' . Str::random(4), 'intitule' => 'EC', 'volume_horaire' => 20]);

        $this->withToken($jetonA)
            ->deleteJson("/api/admin/students/{$etudiantA->id}/ecs/{$ecB->id}")
            ->assertNotFound();
    }

    // ─── Ue ────────────────────────────────────────────────────────────────

    public function test_modifier_l_ue_d_une_autre_faculte_est_refuse(): void
    {
        $filiere = $this->filiereB();
        $ue = Ue::create(['code' => 'UEB3' . Str::random(4), 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30]);

        $this->commeAdminA()->putJson("/api/admin/ues/{$ue->id}", ['intitule' => 'Modifiée'])->assertNotFound();
        $this->commeAdminA()->deleteJson("/api/admin/ues/{$ue->id}")->assertNotFound();
    }

    // ─── Salle ─────────────────────────────────────────────────────────────

    public function test_modifier_la_salle_d_une_autre_faculte_est_refuse(): void
    {
        $etabB = Etablissement::factory()->create();
        $salle = Salle::factory()->create(['etablissement_id' => $etabB->id]);

        $this->commeAdminA()->putJson("/api/admin/salles/{$salle->id}", ['nom' => 'Modifiée'])->assertNotFound();
        $this->commeAdminA()->deleteJson("/api/admin/salles/{$salle->id}")->assertNotFound();
    }

    // ─── Groupe ────────────────────────────────────────────────────────────

    public function test_supprimer_le_groupe_d_une_autre_faculte_est_refuse(): void
    {
        $filiere = $this->filiereB();
        $groupe = Groupe::create(['filiere_id' => $filiere->id, 'annee_id' => $this->anneeActive()->id, 'type' => 'td', 'libelle' => 'G1']);

        $this->commeAdminA()->deleteJson("/api/admin/groupes/{$groupe->id}")->assertNotFound();
    }

    // ─── EmploiDuTemps ─────────────────────────────────────────────────────

    public function test_modifier_le_creneau_d_une_autre_faculte_est_refuse(): void
    {
        $filiere = $this->filiereB();
        $ue = Ue::create(['code' => 'UEB4' . Str::random(4), 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECB4' . Str::random(4), 'intitule' => 'EC', 'volume_horaire' => 20]);
        $creneau = EmploiDuTemps::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $this->anneeActive()->id,
            'jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'type_cours' => 'cm',
        ]);

        $this->commeAdminA()->putJson("/api/admin/emploi-du-temps/{$creneau->id}", ['heure_debut' => '09:00'])->assertNotFound();
        $this->commeAdminA()->deleteJson("/api/admin/emploi-du-temps/{$creneau->id}")->assertNotFound();
    }

    // ─── Programme ─────────────────────────────────────────────────────────

    public function test_modifier_le_programme_d_une_autre_faculte_est_refuse(): void
    {
        $etabB = Etablissement::factory()->create();
        $programme = Programme::create(['etablissement_id' => $etabB->id, 'code' => 'PB' . Str::random(4), 'intitule' => 'Programme B']);

        $this->commeAdminA()->putJson("/api/admin/programmes/{$programme->id}", ['intitule' => 'Modifié'])->assertNotFound();
    }

    // ─── Fermeture (réelle, pas un jour férié national) ────────────────────

    public function test_supprimer_une_fermeture_d_une_autre_faculte_est_refuse(): void
    {
        $filiere = $this->filiereB();
        $fermeture = Fermeture::create([
            'etablissement_id' => $filiere->etablissement_id, 'annee_id' => $this->anneeActive()->id,
            'type' => 'vacances', 'libelle' => 'Vacances B', 'date_debut' => today()->addMonth(), 'date_fin' => today()->addMonth()->addWeek(),
        ]);

        $this->commeAdminA()->deleteJson("/api/admin/calendrier/fermetures/{$fermeture->id}")->assertNotFound();
    }
}
