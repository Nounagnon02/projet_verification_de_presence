<?php

namespace Tests\Feature\Auth;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Régression : /api/auth/student/me résolvait le token par son seul ID
 * (PersonalAccessToken::find), sans vérifier la partie secrète. N'importe
 * qui pouvait lire le profil — et donc l'identifiant de connexion — de
 * n'importe quel étudiant en énumérant les IDs de token.
 */
class StudentTokenAuthTest extends TestCase
{
    private function etudiant(): Etudiant
    {
        return Etudiant::create([
            'nom'                => 'AUTHTEST',
            'prenom'             => 'AUTHTEST',
            'matricule'          => 'AUTH-' . Str::random(8),
            'filiere_id'         => Filiere::query()->firstOrFail()->id,
            'annee_id'           => AnneeAcademique::query()->firstOrFail()->id,
            'email'              => 'auth-' . Str::random(8) . '@example.test',
            'identifiant_unique' => 'AUTH_' . Str::random(10),
        ]);
    }

    public function test_un_token_forge_a_partir_dun_id_valide_est_rejete(): void
    {
        $etudiant = $this->etudiant();
        $vrai     = $etudiant->createToken('mobile-app')->plainTextToken;
        $id       = explode('|', $vrai)[0];

        $this->withHeader('Authorization', "Bearer {$id}|signature-bidon")
            ->getJson('/api/auth/student/me')
            ->assertStatus(401);
    }

    public function test_un_token_valide_donne_acces_au_profil(): void
    {
        $etudiant = $this->etudiant();
        $token    = $etudiant->createToken('mobile-app')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/student/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $etudiant->email);
    }

    public function test_le_logout_ne_revoque_que_le_token_presente(): void
    {
        $victime = $this->etudiant();
        $tokenVictime = $victime->createToken('mobile-app')->plainTextToken;
        $idVictime = explode('|', $tokenVictime)[0];

        $attaquant = $this->etudiant();
        $tokenAttaquant = $attaquant->createToken('mobile-app')->plainTextToken;

        // L'attaquant tente de révoquer le token de la victime par son ID
        $this->withHeader('Authorization', "Bearer {$idVictime}|signature-bidon")
            ->postJson('/api/auth/student/logout')
            ->assertStatus(401);

        // Le token de la victime doit toujours fonctionner
        $this->withHeader('Authorization', "Bearer {$tokenVictime}")
            ->getJson('/api/auth/student/me')
            ->assertStatus(200);
    }
}
