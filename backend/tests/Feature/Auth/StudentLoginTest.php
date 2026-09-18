<?php

namespace Tests\Feature\Auth;

use App\Models\Etudiant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Connexion étudiante avec le code d'accès.
 *
 * Régression : l'email et l'identifiant unique sont tous deux déterministes
 * (NOM_PRENOM_MATRICULE_FILIERE_ANNEE, voir IdentifiantService) — n'importe
 * quel camarade de promotion pouvait les reconstituer et se connecter à la
 * place d'un autre. Le code d'accès est le seul des trois facteurs à être
 * secret.
 */
class StudentLoginTest extends TestCase
{
    /**
     * « code_acces » n'est pas dans le $fillable du modèle : ce champ n'est
     * écrit que par forceFill() (voir CodeAccesEtudiant::attribuer(), qui suit
     * le même motif), pour qu'aucune création ou mise à jour en masse ne
     * puisse le fixer à une valeur choisie par l'appelant.
     */
    private function etudiantAvecCode(string $code = '654321'): Etudiant
    {
        $sfx = Str::random(6);
        $etudiant = Etudiant::create([
            'nom' => 'CONNEXION', 'prenom' => $sfx, 'matricule' => 'SL-' . $sfx,
            'filiere_id' => $this->uneFiliere()->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("sl-{$sfx}@example.test"), 'identifiant_unique' => 'SL_' . $sfx,
        ]);

        $etudiant->forceFill(['code_acces' => Hash::make($code)])->save();

        return $etudiant;
    }

    private function connecter(Etudiant $etudiant, string $code): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/student/login', [
            'email'              => $etudiant->email,
            'identifiant_unique' => $etudiant->identifiant_unique,
            'code'               => $code,
        ]);
    }

    public function test_connexion_reussie_avec_le_bon_code(): void
    {
        $etudiant = $this->etudiantAvecCode('111222');

        $reponse = $this->connecter($etudiant, '111222');

        $reponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $etudiant->email);
        $this->assertNotEmpty($reponse->json('data.token'));

        // Le code ne ressort jamais, ni dans la réponse ni ailleurs.
        $this->assertStringNotContainsString('111222', $reponse->getContent());
    }

    public function test_mauvais_code_est_refuse_avec_le_message_generique(): void
    {
        $etudiant = $this->etudiantAvecCode('111222');

        $this->connecter($etudiant, '999999')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Identifiants invalides.');
    }

    public function test_email_inconnu_donne_exactement_le_meme_message_que_le_mauvais_code(): void
    {
        // Aucun oracle : un email qui n'existe pas ne doit pas se distinguer
        // d'un email existant avec un mauvais code.
        $reponse = $this->postJson('/api/auth/student/login', [
            'email'              => 'personne-'.Str::random(8).'@example.test',
            'identifiant_unique' => 'PEU_IMPORTE',
            'code'               => '000000',
        ]);

        $reponse->assertStatus(422)->assertJsonPath('message', 'Identifiants invalides.');
    }

    public function test_identifiant_errone_donne_le_meme_message_generique(): void
    {
        $etudiant = $this->etudiantAvecCode('111222');

        $this->postJson('/api/auth/student/login', [
            'email'              => $etudiant->email,
            'identifiant_unique' => 'UN_AUTRE_IDENTIFIANT',
            'code'               => '111222',
        ])->assertStatus(422)->assertJsonPath('message', 'Identifiants invalides.');
    }

    public function test_etudiant_sans_code_recoit_un_409_explicite(): void
    {
        $sfx = Str::random(6);
        $etudiant = Etudiant::create([
            'nom' => 'SANSCODE', 'prenom' => $sfx, 'matricule' => 'NC-' . $sfx,
            'filiere_id' => $this->uneFiliere()->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("nc-{$sfx}@example.test"), 'identifiant_unique' => 'NC_' . $sfx,
        ]);

        $this->connecter($etudiant, '000000')
            ->assertStatus(409)
            ->assertJsonPath('code', 'code_absent');
    }

    public function test_la_connexion_revoque_les_jetons_precedents(): void
    {
        $etudiant = $this->etudiantAvecCode('111222');
        $ancien = $etudiant->createToken('mobile-app', ['etudiant'])->plainTextToken;

        $this->connecter($etudiant, '111222')->assertOk();

        $this->withToken($ancien)->getJson('/api/auth/student/me')->assertStatus(401);
    }

    public function test_le_jeton_emis_porte_la_capacite_etudiant_et_non_admin(): void
    {
        $etudiant = $this->etudiantAvecCode('111222');
        $token = $this->connecter($etudiant, '111222')->json('data.token');

        // Un jeton étudiant ne doit pas franchir une route admin.
        $this->withToken($token)->getJson('/api/admin/dashboard')->assertStatus(403);
    }
}
