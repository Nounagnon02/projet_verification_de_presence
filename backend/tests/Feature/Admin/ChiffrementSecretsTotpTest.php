<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migration 2026_09_18_110000 : chiffrement des secrets TOTP écrits en clair
 * avant que le modèle User ne porte le cast « encrypted ».
 *
 * La migration a déjà tourné sur la base de test (une fois pour toute la
 * suite, voir TestCase::setUpTraits()) : ces tests instancient directement la
 * classe de migration pour vérifier son comportement sur des données
 * délibérément remises en clair, sans dépendre de son exécution globale.
 */
class ChiffrementSecretsTotpTest extends TestCase
{
    private function migration(): object
    {
        return require base_path('database/migrations/2026_09_18_110000_chiffrer_secrets_totp_existants.php');
    }

    private function utilisateurEnClair(): User
    {
        $user = User::factory()->create();

        // Écriture SQL brute : passer par le modèle chiffrerait déjà la
        // valeur (cast « encrypted »), ce qui ne simulerait pas l'état d'une
        // base migrée depuis avant son introduction.
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret'         => 'SECRET-EN-CLAIR',
            'two_factor_recovery_codes' => json_encode(['AAA', 'BBB']),
        ]);

        return $user;
    }

    public function test_un_secret_en_clair_est_chiffre(): void
    {
        $user = $this->utilisateurEnClair();

        $this->migration()->up();

        $brut = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame('SECRET-EN-CLAIR', $brut->two_factor_secret);
        $this->assertSame('SECRET-EN-CLAIR', Crypt::decryptString($brut->two_factor_secret));

        // Et le modèle continue de lire la valeur en clair, de façon transparente.
        $this->assertSame('SECRET-EN-CLAIR', $user->fresh()->two_factor_secret);
    }

    public function test_la_forme_json_des_codes_de_recuperation_traverse_le_chiffrement(): void
    {
        $user = $this->utilisateurEnClair();

        $this->migration()->up();

        $codes = json_decode($user->fresh()->two_factor_recovery_codes, true);
        $this->assertSame(['AAA', 'BBB'], $codes);
    }

    public function test_relancer_la_migration_ne_rechiffre_pas_une_valeur_deja_chiffree(): void
    {
        $user = $this->utilisateurEnClair();
        $migration = $this->migration();

        $migration->up();
        $premierChiffre = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $migration->up();
        $secondChiffre = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertSame($premierChiffre, $secondChiffre);
    }

    public function test_un_utilisateur_sans_2fa_n_est_pas_touche(): void
    {
        $user = User::factory()->create();

        $this->migration()->up();

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
    }
}
