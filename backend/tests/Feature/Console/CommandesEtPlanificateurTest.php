<?php

namespace Tests\Feature\Console;

use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Garde-fou sur la couche console.
 *
 * Deux defauts ont vecu longtemps ici sans qu'aucun test ne les voie :
 *
 *  - CleanExpiredQrCodes ne compilait pas (arithmetique dans une interpolation
 *    de chaine), si bien que la commande « qr:clean-expired » n'existait pas.
 *  - Le planificateur declare dans app/Console/Kernel.php n'etait plus charge
 *    depuis Laravel 11 : deux taches quotidiennes ne tournaient pas, en silence.
 *
 * Aucun test unitaire de commande n'aurait suffi : il fallait verifier que les
 * commandes sont ENREGISTREES et que les taches sont PLANIFIEES.
 */
class CommandesEtPlanificateurTest extends TestCase
{
    /**
     * Commandes metier que l'application doit exposer.
     */
    private const COMMANDES_ATTENDUES = [
        'qr:clean-expired',
        'qrcode:auto-generate',
        'ecs:sync-statut',
        'events:generate-from-schedule',
        'students:promote',
        'app:enroll-existing-students',
    ];

    public function test_toutes_les_commandes_metier_sont_enregistrees(): void
    {
        $enregistrees = array_keys(Artisan::all());

        foreach (self::COMMANDES_ATTENDUES as $commande) {
            $this->assertContains(
                $commande,
                $enregistrees,
                "La commande « {$commande} » n'est pas enregistree. "
                . "Une erreur de syntaxe dans sa classe suffit a la faire disparaitre du registre."
            );
        }
    }

    public function test_chaque_classe_de_commande_est_analysable(): void
    {
        // Un fichier qui ne compile pas ne fait pas echouer « artisan list » :
        // la classe est simplement absente du registre. On verifie donc les
        // fichiers eux-memes.
        $repertoire = app_path('Console/Commands');
        $fichiers = glob($repertoire . '/*.php') ?: [];

        $this->assertNotEmpty($fichiers, 'Aucune classe de commande trouvee.');

        foreach ($fichiers as $fichier) {
            $sortie = [];
            $code = 0;
            exec('php -l ' . escapeshellarg($fichier) . ' 2>&1', $sortie, $code);

            $this->assertSame(
                0,
                $code,
                "Erreur de syntaxe dans " . basename($fichier) . " : " . implode("\n", $sortie)
            );
        }
    }

    public function test_les_taches_quotidiennes_sont_planifiees(): void
    {
        $planifiees = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($evenement) => $evenement->command . ' ' . $evenement->expression)
            ->implode("\n");

        foreach (['qr:clean-expired', 'ecs:sync-statut', 'queue:work'] as $attendue) {
            $this->assertStringContainsString(
                $attendue,
                $planifiees,
                "La tache « {$attendue} » n'est pas planifiee. Verifier routes/console.php — "
                . "une declaration dans app/Console/Kernel.php n'est plus chargee depuis Laravel 11."
            );
        }
    }

    public function test_le_nettoyage_supprime_les_qr_expires_et_epargne_les_recents(): void
    {
        $evenement = $this->unEvenementPasse();

        $ancien = QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) \Illuminate\Support\Str::uuid(),
            'expire_at'    => Carbon::now()->subDays(40),
            'actif'        => false,
        ]);

        $recent = QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) \Illuminate\Support\Str::uuid(),
            'expire_at'    => Carbon::now()->subDays(2),
            'actif'        => false,
        ]);

        // Un QR encore actif ne doit jamais etre supprime, meme expire.
        $actif = QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) \Illuminate\Support\Str::uuid(),
            'expire_at'    => Carbon::now()->subDays(40),
            'actif'        => true,
        ]);

        $this->artisan('qr:clean-expired', ['--force' => true, '--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseMissing('qrcodes', ['id' => $ancien->id]);
        $this->assertDatabaseHas('qrcodes', ['id' => $recent->id]);
        $this->assertDatabaseHas('qrcodes', ['id' => $actif->id]);
    }

    public function test_le_nettoyage_en_mode_simulation_ne_supprime_rien(): void
    {
        $evenement = $this->unEvenementPasse();

        $qr = QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) \Illuminate\Support\Str::uuid(),
            'expire_at'    => Carbon::now()->subDays(40),
            'actif'        => false,
        ]);

        $this->artisan('qr:clean-expired', ['--dry-run' => true, '--days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseHas('qrcodes', ['id' => $qr->id]);
    }

    public function test_la_synchronisation_des_statuts_ec_s_execute(): void
    {
        $this->artisan('ecs:sync-statut')->assertSuccessful();
    }

    /**
     * Un evenement passe minimal, rattache a l'annee active.
     */
    private function unEvenementPasse(): \App\Models\Evenement
    {
        $ue = $this->uneUe();
        $ec = \App\Models\Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-CMD-' . \Illuminate\Support\Str::random(4),
            'intitule'       => 'EC de test console',
            'volume_horaire' => 20,
        ]);

        return \App\Models\Evenement::create([
            'ec_id'       => $ec->id,
            'filiere_id'  => $ue->filiere_id,
            'annee_id'    => $ue->annee_id,
            'date'        => Carbon::now()->subDays(3)->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'CMD-1',
            'statut'      => 'termine',
        ]);
    }
}
