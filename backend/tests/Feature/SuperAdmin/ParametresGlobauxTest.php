<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\ParametreGlobal;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Réglages anti-fraude et de scan de l'université (écran « Paramètres »).
 *
 * Remplace config/presence.php : les valeurs vivent dans l'unique ligne de
 * parametres_globaux, créée par sa migration et jamais recréée ici.
 */
class ParametresGlobauxTest extends TestCase
{
    private string $jetonSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jetonSuperAdmin = User::factory()->create([
            'email' => 'pg-'.Str::random(6).'@example.test',
            'role' => 'super_admin',
            'two_factor_confirmed_at' => now(),
        ])->createToken('t')->plainTextToken;
    }

    private function valeursValides(array $ecart = []): array
    {
        return array_merge([
            'scan_minutes_avant_fin' => 15,
            'scan_minutes_apres_fin' => 10,
            'qr_ttl_secondes' => 60,
            'qr_visible_delegue_avant_fin' => 10,
            'seance_duree_max_heures' => 5,
            'limite_scan_par_etudiant' => 3,
            'limite_scan_par_ip' => 2000,
        ], $ecart);
    }

    public function test_le_super_admin_lit_les_reglages_actuels(): void
    {
        $this->withToken($this->jetonSuperAdmin)->getJson('/api/super-admin/parametres')
            ->assertOk()
            ->assertJsonPath('data.scan_minutes_avant_fin', 15)
            ->assertJsonPath('data.limite_scan_par_ip', 2000);
    }

    public function test_le_super_admin_modifie_les_reglages_et_ca_persiste(): void
    {
        $this->withToken($this->jetonSuperAdmin)
            ->putJson('/api/super-admin/parametres', $this->valeursValides(['scan_minutes_avant_fin' => 20, 'limite_scan_par_ip' => 3000]))
            ->assertOk()
            ->assertJsonPath('data.scan_minutes_avant_fin', 20)
            ->assertJsonPath('data.limite_scan_par_ip', 3000);

        $this->assertSame(20, ParametreGlobal::actuel()->scan_minutes_avant_fin);
        $this->assertSame(3000, ParametreGlobal::actuel()->limite_scan_par_ip);
    }

    /** @return array<string, array{0: string, 1: int|float}> */
    public static function valeursHorsBornes(): array
    {
        return [
            'scan_minutes_avant_fin trop bas' => ['scan_minutes_avant_fin', 0],
            'scan_minutes_avant_fin trop haut' => ['scan_minutes_avant_fin', 121],
            'qr_ttl_secondes trop bas' => ['qr_ttl_secondes', 29],
            'seance_duree_max_heures trop basse' => ['seance_duree_max_heures', 0.1],
            'limite_scan_par_etudiant a zero' => ['limite_scan_par_etudiant', 0],
            // Le plancher documenté : en dessous, une salle derrière une IP
            // partagée (CGNAT) se verrait refuser des scans légitimes.
            'limite_scan_par_ip trop bas' => ['limite_scan_par_ip', 999],
        ];
    }

    /** @dataProvider valeursHorsBornes */
    public function test_une_valeur_hors_bornes_est_refusee(string $champ, int|float $valeur): void
    {
        $this->withToken($this->jetonSuperAdmin)
            ->putJson('/api/super-admin/parametres', $this->valeursValides([$champ => $valeur]))
            ->assertStatus(422)
            ->assertJsonValidationErrors($champ);

        // Rien n'a bougé : les réglages restent ceux d'avant la tentative.
        $this->assertSame(2000, ParametreGlobal::actuel()->limite_scan_par_ip);
    }

    public function test_un_champ_manquant_est_refuse(): void
    {
        $incomplet = $this->valeursValides();
        unset($incomplet['limite_scan_par_etudiant']);

        $this->withToken($this->jetonSuperAdmin)
            ->putJson('/api/super-admin/parametres', $incomplet)
            ->assertStatus(422)
            ->assertJsonValidationErrors('limite_scan_par_etudiant');
    }
}
