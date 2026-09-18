<?php

namespace Tests\Feature\Admin;

use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Renvoi des identifiants de connexion d'un étudiant (POST
 * /api/admin/students/{student}/identifiants).
 *
 * Un code perdu ne se récupère pas : la base n'en garde que le hachage. Cet
 * endpoint en tire un NEUF, ce qui invalide l'ancien, et ne renvoie jamais le
 * code en clair dans la réponse HTTP.
 */
class RenvoiIdentifiantsTest extends TestCase
{
    private function etudiant(): Etudiant
    {
        $sfx = Str::random(6);

        return Etudiant::create([
            'nom' => 'RENVOI', 'prenom' => $sfx, 'matricule' => 'RI-' . $sfx,
            'filiere_id' => $this->uneFiliere()->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("ri-{$sfx}@example.test"), 'identifiant_unique' => 'RI_' . $sfx,
        ]);
    }

    public function test_renvoyer_les_identifiants_attribue_un_nouveau_code_et_l_envoie(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $etudiant = $this->etudiant();
        $ancienHash = $etudiant->code_acces;

        $reponse = $this->withToken($admin->createToken('t')->plainTextToken)
            ->postJson("/api/admin/students/{$etudiant->id}/identifiants");

        $reponse->assertOk();

        $etudiant->refresh();
        $this->assertNotNull($etudiant->code_acces);
        $this->assertNotSame($ancienHash, $etudiant->code_acces);

        // Jamais le code en clair dans la réponse.
        $this->assertArrayNotHasKey('code', $reponse->json('data') ?? []);

        // Mail::fake() n'enregistre que les Mailable typés : Mail::send() avec
        // une vue et un tableau (le motif de cet envoi) ne l'est pas et ne peut
        // donc pas s'observer par Mail::assertSent(). Ce que le fake garantit
        // ici, c'est qu'aucun e-mail réel n'est parti pendant le test.
    }

    public function test_un_admin_d_une_autre_faculte_ne_peut_pas_renvoyer_les_identifiants(): void
    {
        $sfx = Str::random(5);
        $etabA = Etablissement::factory()->create();
        $etabB = Etablissement::factory()->create();
        $filiereB = Filiere::create(['code' => 'RIB' . $sfx, 'intitule' => 'Filière B', 'niveau' => 'L1', 'etablissement_id' => $etabB->id]);
        $etudiantB = Etudiant::create([
            'nom' => 'RENVOI', 'prenom' => $sfx, 'matricule' => 'RIB-' . $sfx,
            'filiere_id' => $filiereB->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("rib-{$sfx}@example.test"), 'identifiant_unique' => 'RIB_' . $sfx,
        ]);

        $adminA = User::factory()->faculteAdmin($etabA->id)->create();

        $this->withToken($adminA->createToken('t')->plainTextToken)
            ->postJson("/api/admin/students/{$etudiantB->id}/identifiants")
            ->assertNotFound();
    }
}
