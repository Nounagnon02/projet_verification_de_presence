<?php

namespace Tests\Feature;

use App\Models\Etudiant;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Limites de débit du scan de présence (CDC 9.2.4).
 *
 * Aucun test ne couvrait ce limiteur : toutes les suites de scan désactivent
 * ThrottleRequests pour ne pas se bloquer elles-mêmes. Il limitait pourtant à
 * TROIS requêtes par minute par adresse IP, en plus de trois par étudiant. Une
 * salle, un campus derrière un même NAT ou un opérateur mobile font arriver des
 * dizaines d'étudiants sous une seule adresse — le cas nominal —, et à trois par
 * minute la quatrième personne d'une salle recevait 429. Une campagne de charge
 * à 500 utilisateurs depuis une seule machine l'a montré : 497 refus.
 *
 * Le limiteur compte les requêtes qu'il laisse passer, quelle qu'en soit l'issue :
 * ces tests postent donc un jeton de QR Code quelconque, sans monter de séance.
 */
class ScanRateLimitTest extends TestCase
{
    private function unEtudiant(int $n): Etudiant
    {
        return Etudiant::create([
            'id' => (string) Str::uuid(),
            'nom' => "LIMITE{$n}",
            'prenom' => 'TEST',
            'matricule' => "LIM-{$n}-".Str::random(4),
            'filiere_id' => $this->uneFiliere()->id,
            'annee_id' => $this->anneeActive()->id,
            'email' => "limite{$n}-".Str::random(4).'@test.local',
            'identifiant_unique' => "LIMITE{$n}_TEST_".Str::random(6),
        ]);
    }

    private function scanner(Etudiant $etudiant, string $ip = '203.0.113.10')
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->jetonDeScan($etudiant)])
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/presence/scan', [
                'token' => (string) Str::uuid(),
                'device_fingerprint' => 'empreinte-'.$etudiant->id,
            ]);
    }

    public function test_un_etudiant_est_limite_a_trois_scans_par_minute(): void
    {
        $etudiant = $this->unEtudiant(1);

        foreach ([1, 2, 3] as $essai) {
            $this->assertNotSame(429, $this->scanner($etudiant)->status(), "essai {$essai}");
        }

        $this->scanner($etudiant)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_des_etudiants_distincts_d_une_meme_adresse_ne_se_bloquent_pas(): void
    {
        // Vingt étudiants, une seule adresse : une salle derrière un même NAT.
        // Avec l'ancienne limite (3 par IP), le quatrième était refusé.
        foreach (range(1, 20) as $n) {
            $this->assertNotSame(
                429,
                $this->scanner($this->unEtudiant($n))->status(),
                "l'étudiant n° {$n} de la même adresse ne doit pas être limité"
            );
        }
    }

    public function test_le_plafond_par_adresse_existe_toujours(): void
    {
        // Un filet contre l'inondation, pas une règle de fraude : il doit
        // toujours couper, mais à un niveau que les salles n'atteignent pas.
        config(['presence.limites.scan_par_ip' => 5]);

        foreach (range(1, 5) as $n) {
            $this->assertNotSame(429, $this->scanner($this->unEtudiant($n))->status(), "étudiant n° {$n}");
        }

        $this->scanner($this->unEtudiant(6))->assertStatus(429);

        // Une autre adresse n'est pas touchée.
        $this->assertNotSame(429, $this->scanner($this->unEtudiant(7), '198.51.100.7')->status());
    }

    public function test_le_plafond_par_defaut_reste_au_dessus_de_l_hypothese_h3(): void
    {
        // H3 : 500 scans simultanés. Un plafond proche de 500 bloquerait une salle
        // qui les atteint depuis une seule adresse, dès que des retardataires
        // s'ajoutent dans la même minute (constaté à 600).
        $this->assertGreaterThanOrEqual(1000, config('presence.limites.scan_par_ip'));
        $this->assertSame(3, config('presence.limites.scan_par_etudiant'));
    }
}
