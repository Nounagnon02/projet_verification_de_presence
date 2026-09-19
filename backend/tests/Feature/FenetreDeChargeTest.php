<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * preparer-charge.php (tests/load/) pose la date de l'événement sur le jour de
 * FIN du cours. Evenement::finCours() suppose que « date » est le jour de
 * DÉBUT (elle ajoute elle-même un jour si heure_fin <= heure_debut) : les deux
 * hypothèses divergent quand le cours chevauche minuit heure locale
 * (Africa/Porto-Novo), et la fenêtre de scan se retrouve calculée un jour
 * entier trop tard. Constaté en pleine campagne de charge : tous les scans
 * suivant le passage de minuit refusés en 403.
 *
 * Ce test n'exécute pas le script (il écrit en base hors transaction) : il
 * reproduit sa construction d'événement, juste avant et juste après minuit.
 */
class FenetreDeChargeTest extends TestCase
{
    protected function tearDown(): void
    {
        // Carbon::setTestNow() n'est pas réinitialisée par TestCase : sans ce
        // retrait, l'horloge gelée en 2090 fuit sur les tests suivants du même
        // processus. Constaté : 5 tests d'autres classes en échec, tous liés à
        // « aujourd'hui ».
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function evenementCommeLeGenerateurDeCharge(Carbon $fin): Evenement
    {
        $etab = Etablissement::create(['code' => 'CHG-T', 'nom' => 'Charge test', 'email' => 'c@t.test', 'actif' => true]);
        $annee = AnneeAcademique::create(['libelle' => '2090-2091', 'date_debut' => '2090-10-01', 'date_fin' => '2091-09-30', 'active' => false, 'etablissement_id' => $etab->id]);
        $filiere = Filiere::create(['code' => 'CHG-T', 'intitule' => 'Filière charge', 'niveau' => 'L3', 'etablissement_id' => $etab->id]);
        $salle = Salle::create(['etablissement_id' => $etab->id, 'nom' => 'Salle charge', 'code' => 'S-CHG-T', 'hors_reseau' => true, 'actif' => true]);
        $ue = Ue::create(['code' => 'UE-CHG-T', 'intitule' => 'UE charge', 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 30]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'EC-CHG-T', 'intitule' => 'EC charge', 'volume_horaire' => 20]);

        // Reproduit exactement la construction corrigée de preparer-charge.php.
        $debut = $fin->copy()->subHours(2);

        return Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $filiere->id, 'annee_id' => $annee->id,
            'date' => $debut->toDateString(), 'heure_debut' => $debut->format('H:i:s'), 'heure_fin' => $fin->format('H:i:s'),
            'salle' => $salle->nom, 'salle_id' => $salle->id, 'statut' => 'planifie',
        ]);
    }

    public function test_la_fenetre_est_deja_ouverte_juste_apres_minuit(): void
    {
        // now = 00:03 : fin = now+9min = 00:12, chevauche minuit (heure_debut = 22:12 la veille).
        Carbon::setTestNow(Carbon::parse('2090-01-02 00:03:00', 'Africa/Porto-Novo'));
        $fin = now()->addMinutes(9);

        $evenement = $this->evenementCommeLeGenerateurDeCharge($fin);

        $this->assertTrue(
            now()->greaterThanOrEqualTo($evenement->ouvertureScan()),
            "La fenêtre devrait déjà être ouverte (ouverture attendue à {$evenement->ouvertureScan()}, maintenant ".now().')'
        );
        $this->assertTrue(now()->lessThanOrEqualTo($evenement->fermetureScan()));
    }

    public function test_la_fenetre_est_deja_ouverte_juste_avant_minuit(): void
    {
        // now = 23:53 : fin = now+9min = 00:02 le lendemain, chevauche minuit.
        Carbon::setTestNow(Carbon::parse('2090-01-01 23:53:00', 'Africa/Porto-Novo'));
        $fin = now()->addMinutes(9);

        $evenement = $this->evenementCommeLeGenerateurDeCharge($fin);

        $this->assertTrue(
            now()->greaterThanOrEqualTo($evenement->ouvertureScan()),
            "La fenêtre devrait déjà être ouverte (ouverture attendue à {$evenement->ouvertureScan()}, maintenant ".now().')'
        );
        $this->assertTrue(now()->lessThanOrEqualTo($evenement->fermetureScan()));
    }
}
