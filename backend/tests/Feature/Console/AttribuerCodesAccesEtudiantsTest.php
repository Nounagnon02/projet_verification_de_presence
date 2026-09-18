<?php

namespace Tests\Feature\Console;

use App\Models\Etudiant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Commande « etudiants:codes-acces » : rattrapage des étudiants inscrits
 * avant l'introduction du code d'accès.
 */
class AttribuerCodesAccesEtudiantsTest extends TestCase
{
    private function etudiantSansCode(): Etudiant
    {
        $sfx = Str::random(6);

        return Etudiant::create([
            'nom' => 'RATTRAPAGE', 'prenom' => $sfx, 'matricule' => 'RAT-' . $sfx,
            'filiere_id' => $this->uneFiliere()->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("rat-{$sfx}@example.test"), 'identifiant_unique' => 'RAT_' . $sfx,
        ]);
    }

    public function test_sans_l_option_envoyer_la_commande_ne_modifie_rien(): void
    {
        $etudiant = $this->etudiantSansCode();

        $this->artisan('etudiants:codes-acces')->assertSuccessful();

        $this->assertNull($etudiant->fresh()->code_acces);
    }

    public function test_avec_envoyer_chaque_etudiant_sans_code_en_recoit_un(): void
    {
        Mail::fake();
        $etudiant = $this->etudiantSansCode();

        $this->artisan('etudiants:codes-acces --envoyer')->assertSuccessful();

        $this->assertNotNull($etudiant->fresh()->code_acces);
    }

    public function test_un_etudiant_deja_muni_d_un_code_n_est_pas_retire(): void
    {
        Mail::fake();
        $etudiant = $this->etudiantSansCode();
        $etudiant->forceFill(['code_acces' => \Illuminate\Support\Facades\Hash::make('123456')])->save();
        $hachageInitial = $etudiant->code_acces;

        $this->artisan('etudiants:codes-acces --envoyer')->assertSuccessful();

        $this->assertSame($hachageInitial, $etudiant->fresh()->code_acces);
    }
}
