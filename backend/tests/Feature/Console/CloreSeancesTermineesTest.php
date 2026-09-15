<?php

namespace Tests\Feature\Console;

use App\Models\Ec;
use App\Models\Evenement;
use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Clôture automatique des séances (events:close-finished).
 *
 * Les assertions portent sur les séances créées ici, jamais sur des totaux : la
 * commande agit sur toute la base. L'horloge est figée pour tester les bornes à
 * la seconde.
 */
class CloreSeancesTermineesTest extends TestCase
{
    private Ec $ec;

    protected function setUp(): void
    {
        parent::setUp();

        // Fenêtre fixée ici : les bornes testées ne doivent pas dépendre du .env.
        config(['presence.scan.minutes_avant_fin' => 15, 'presence.scan.minutes_apres_fin' => 10]);
        $this->travelTo(Carbon::parse('2026-03-10 12:00:00'));

        $this->ec = Ec::create([
            'ue_id'          => $this->uneUe()->id,
            'code'           => 'CLO' . Str::random(5),
            'intitule'       => 'Cours clôture',
            'volume_horaire' => 40,
        ]);
    }

    private function seance(string $date, string $debut, string $fin, string $statut = 'planifie'): Evenement
    {
        return Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->ec->ue->filiere_id,
            'annee_id'    => $this->ec->ue->annee_id,
            'date'        => $date,
            'heure_debut' => $debut,
            'heure_fin'   => $fin,
            'statut'      => $statut,
        ]);
    }

    private function qrActif(Evenement $evenement): QrCode
    {
        return QrCode::create([
            'evenement_id' => $evenement->id,
            'token'        => (string) Str::uuid(),
            'expire_at'    => $evenement->fermetureScan(),
            'actif'        => true,
        ]);
    }

    public function test_clot_les_seances_dont_la_fenetre_de_presence_est_fermee(): void
    {
        $planifiee = $this->seance('2026-03-10', '08:00', '10:00');
        $enCours   = $this->seance('2026-03-10', '09:00', '11:00', 'en_cours');
        $hier      = $this->seance('2026-03-09', '14:00', '16:00');

        $this->artisan('events:close-finished')->assertSuccessful();

        // Une séance jamais passée « en cours » est close aussi : son horaire est passé.
        $this->assertSame('termine', $planifiee->fresh()->statut);
        $this->assertSame('termine', $enCours->fresh()->statut);
        $this->assertSame('termine', $hier->fresh()->statut);
    }

    public function test_attend_la_fermeture_du_scan_et_non_l_heure_de_fin(): void
    {
        // Fin à 11:55, scan accepté jusqu'à 12:05 : clore plus tôt arrêterait
        // la rotation du QR pendant que les étudiants scannent encore.
        $seance = $this->seance('2026-03-10', '10:00', '11:55', 'en_cours');

        $this->artisan('events:close-finished')->assertSuccessful();
        $this->assertSame('en_cours', $seance->fresh()->statut);

        // À 12:05 pile, qrcode:auto-generate la sert encore (borne incluse).
        $this->travelTo(Carbon::parse('2026-03-10 12:05:00'));
        $this->artisan('events:close-finished')->assertSuccessful();
        $this->assertSame('en_cours', $seance->fresh()->statut);

        $this->travelTo(Carbon::parse('2026-03-10 12:05:01'));
        $this->artisan('events:close-finished')->assertSuccessful();
        $this->assertSame('termine', $seance->fresh()->statut);
    }

    public function test_ne_touche_ni_aux_seances_a_venir_ni_aux_annulees(): void
    {
        $plusTard = $this->seance('2026-03-10', '14:00', '16:00');
        $demain   = $this->seance('2026-03-11', '08:00', '10:00');
        $annulee  = $this->seance('2026-03-09', '08:00', '10:00', 'annule');

        $this->artisan('events:close-finished')->assertSuccessful();

        $this->assertSame('planifie', $plusTard->fresh()->statut);
        $this->assertSame('planifie', $demain->fresh()->statut);
        $this->assertSame('annule', $annulee->fresh()->statut);
    }

    public function test_une_seance_a_cheval_sur_minuit_reste_ouverte_jusqu_a_sa_fermeture(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 00:30:00'));
        // Datée de la veille, elle finit à 01:00 le lendemain.
        $seance = $this->seance('2026-03-09', '22:00', '01:00', 'en_cours');

        $this->artisan('events:close-finished')->assertSuccessful();
        $this->assertSame('en_cours', $seance->fresh()->statut);

        $this->travelTo(Carbon::parse('2026-03-10 01:11:00'));
        $this->artisan('events:close-finished')->assertSuccessful();
        $this->assertSame('termine', $seance->fresh()->statut);
    }

    public function test_desactive_le_qr_encore_actif_d_une_seance_close(): void
    {
        $seance = $this->seance('2026-03-10', '08:00', '10:00', 'en_cours');
        $qr = $this->qrActif($seance);

        $this->artisan('events:close-finished')->assertSuccessful();

        $this->assertFalse((bool) $qr->fresh()->actif);
    }

    public function test_la_simulation_ne_modifie_rien(): void
    {
        $seance = $this->seance('2026-03-10', '08:00', '10:00', 'en_cours');
        $qr = $this->qrActif($seance);

        $this->artisan('events:close-finished', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('en_cours', $seance->fresh()->statut);
        $this->assertTrue((bool) $qr->fresh()->actif);
    }
}
