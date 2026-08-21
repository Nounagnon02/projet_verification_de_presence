<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Anomaly;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tableau de bord administrateur (US05) — DashboardController.
 *
 * Les quatre endpoints sont vérifiés sur un jeu de données entièrement
 * fabriqué : les assertions portent sur les VALEURS renvoyées (effectifs,
 * comptages, taux, ordre des séries) et pas seulement sur la forme du JSON.
 *
 * Toutes les mesures sont lues avec un jeton d'admin de faculté : le
 * cloisonnement rend les comptages déterministes même quand la base de test
 * contient par ailleurs les données d'autres suites.
 *
 * Jeu de données de l'établissement A :
 *   3 étudiants, tous inscrits à l'EC de A ;
 *   5 événements — 2 aujourd'hui (08 h et 14 h, planifiés), 1 hier (terminé),
 *   1 il y a 5 jours, 1 il y a 40 jours (hors fenêtre de la tendance) ;
 *   7 présences dont 3 aujourd'hui (2 valides, 1 suspecte).
 */
class DashboardControllerTest extends TestCase
{
    /** Les quatre routes du tableau de bord, utilisées par les tests de sécurité. */
    private const ROUTES = [
        '/api/admin/dashboard',
        '/api/admin/dashboard/attendance-trend',
        '/api/admin/dashboard/top-absences',
        '/api/admin/dashboard/today-events',
    ];

    private string $sfx;
    private AnneeAcademique $annee;

    private string $jetonA;
    private string $jetonB;
    private string $jetonVide;

    private Filiere $filiereA;
    private Ec $ecA;

    /** @var array<int, Etudiant> */
    private array $etudiantsA = [];

    /** @var array<int, Etudiant> */
    private array $etudiantsB = [];

    private Evenement $coursMatin;
    private Evenement $coursApresMidi;
    private Evenement $coursHier;
    private Evenement $coursJ5;
    private Evenement $coursJ40;
    private Evenement $coursDuJourB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx   = Str::random(6);
        $this->annee = $this->anneeActive();

        $etabA    = $this->creerEtablissement('A');
        $etabB    = $this->creerEtablissement('B');
        $etabVide = $this->creerEtablissement('V');

        $this->jetonA    = $this->jetonAdmin($etabA);
        $this->jetonB    = $this->jetonAdmin($etabB);
        $this->jetonVide = $this->jetonAdmin($etabVide);

        // ── Établissement A ───────────────────────────────────────────────
        [$this->filiereA, $this->ecA] = $this->creerCursus($etabA, 'A');

        foreach ([1, 2, 3] as $i) {
            $this->etudiantsA[$i] = $this->creerEtudiant($this->filiereA, "A{$i}", $this->ecA);
        }

        // Créés dans le désordre : la timeline du jour doit les réordonner.
        $this->coursApresMidi = $this->creerEvenement($this->filiereA, $this->ecA, today(), '14:00:00', '16:00:00', 'planifie', 'A-Aprem');
        $this->coursMatin     = $this->creerEvenement($this->filiereA, $this->ecA, today(), '08:00:00', '10:00:00', 'planifie', 'A-Matin');

        // Seul cours terminé de A : il porte à lui seul le taux de présence.
        $this->coursHier = $this->creerEvenement($this->filiereA, $this->ecA, today()->subDay(), '08:00:00', '10:00:00', 'termine', 'A-Hier');
        $this->coursJ5   = $this->creerEvenement($this->filiereA, $this->ecA, today()->subDays(5), '08:00:00', '10:00:00', 'planifie', 'A-J5');
        // Hors de la fenêtre de 30 jours de la tendance.
        $this->coursJ40  = $this->creerEvenement($this->filiereA, $this->ecA, today()->subDays(40), '08:00:00', '10:00:00', 'planifie', 'A-J40');

        // Présences du jour : 2 valides à 08 h, 1 suspecte à 09 h.
        $this->creerPresence($this->etudiantsA[1], $this->coursMatin, today()->setTime(8, 15), 'valide');
        $this->creerPresence($this->etudiantsA[2], $this->coursMatin, today()->setTime(8, 20), 'valide');
        $this->creerPresence($this->etudiantsA[3], $this->coursMatin, today()->setTime(9, 30), 'suspect');

        // Cours terminé d'hier : 2 présents sur 3 inscrits → taux de 66,7 %.
        $this->creerPresence($this->etudiantsA[1], $this->coursHier, today()->subDay()->setTime(9, 0), 'valide');
        $this->creerPresence($this->etudiantsA[2], $this->coursHier, today()->subDay()->setTime(9, 5), 'valide');

        $this->creerPresence($this->etudiantsA[1], $this->coursJ5, today()->subDays(5)->setTime(9, 0), 'valide');
        $this->creerPresence($this->etudiantsA[1], $this->coursJ40, today()->subDays(40)->setTime(9, 0), 'valide');

        // Deux anomalies ouvertes (dates distinctes pour un tri déterministe)
        // et une déjà résolue, qui ne doit jamais être comptée.
        $this->creerAnomalie($this->etudiantsA[1], 'Anomalie ancienne A', now()->subHours(3));
        $this->creerAnomalie($this->etudiantsA[2], 'Anomalie récente A', now()->subMinutes(5));
        $this->creerAnomalie($this->etudiantsA[3], 'Anomalie résolue A', now()->subMinute(), true);

        // ── Établissement B : chiffres volontairement tous différents ─────
        [$filiereB, $ecB] = $this->creerCursus($etabB, 'B');

        foreach ([1, 2, 3, 4, 5] as $i) {
            $this->etudiantsB[$i] = $this->creerEtudiant($filiereB, "B{$i}", $ecB);
        }

        $this->coursDuJourB = $this->creerEvenement($filiereB, $ecB, today(), '10:00:00', '12:00:00', 'planifie', 'B-Jour');
        $coursJ3B           = $this->creerEvenement($filiereB, $ecB, today()->subDays(3), '08:00:00', '10:00:00', 'termine', 'B-J3');

        // 1 présent sur 5 inscrits (taux de 20 %), un jour où A n'a aucune présence.
        $this->creerPresence($this->etudiantsB[1], $coursJ3B, today()->subDays(3)->setTime(9, 0), 'valide');

        $this->creerAnomalie($this->etudiantsB[1], 'Anomalie de B', now()->subMinutes(2));
    }

    // ── GET /admin/dashboard ──────────────────────────────────────────────

    public function test_le_tableau_de_bord_renvoie_les_chiffres_de_letablissement(): void
    {
        $reponse = $this->enTantQue($this->jetonA)->getJson('/api/admin/dashboard');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_etudiants', 3)
            ->assertJsonPath('data.cours_du_jour', 2)
            ->assertJsonPath('data.presences_aujourd_hui', 3)
            ->assertJsonPath('data.presences_valides', 2)
            ->assertJsonPath('data.presences_suspectes', 1)
            ->assertJsonPath('data.fraudes_suspectees', 2);

        // 2 présences valides pour 3 inscrits au seul cours terminé.
        $this->assertSame(66.7, (float) $reponse->json('data.taux_presence_global'));

        // Anomalies ouvertes, la plus récente en tête ; la résolue est exclue.
        $anomalies = $reponse->json('data.dernieres_anomalies');
        $this->assertCount(2, $anomalies);
        $this->assertSame('Anomalie récente A', $anomalies[0]['description']);
        $this->assertSame('Anomalie ancienne A', $anomalies[1]['description']);
        $this->assertSame('high', $anomalies[0]['severite']);

        // Heatmap du jour : 2 scans à 8 h, 1 à 9 h, heures croissantes.
        $heatmap = $reponse->json('data.heatmap');
        $this->assertSame(['8', '9'], array_map('strval', array_keys($heatmap)));
        $this->assertSame([2, 1], array_map('intval', array_values($heatmap)));
    }

    public function test_un_etablissement_sans_donnees_renvoie_des_zeros_coherents(): void
    {
        $reponse = $this->enTantQue($this->jetonVide)->getJson('/api/admin/dashboard');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_etudiants', 0)
            ->assertJsonPath('data.cours_du_jour', 0)
            ->assertJsonPath('data.presences_aujourd_hui', 0)
            ->assertJsonPath('data.presences_valides', 0)
            ->assertJsonPath('data.presences_suspectes', 0)
            ->assertJsonPath('data.fraudes_suspectees', 0)
            ->assertJsonPath('data.dernieres_anomalies', [])
            ->assertJsonPath('data.heatmap', []);

        // Aucune division par zéro : le taux reste un nombre fini valant 0.
        $taux = $reponse->json('data.taux_presence_global');
        $this->assertIsNumeric($taux);
        $this->assertTrue(is_finite((float) $taux), 'Le taux ne doit jamais valoir NAN ou INF.');
        $this->assertSame(0.0, (float) $taux);

        // Les trois autres endpoints renvoient des collections vides, pas d'erreur.
        $this->enTantQue($this->jetonVide)->getJson('/api/admin/dashboard/attendance-trend')
            ->assertStatus(200)->assertJsonPath('data', []);
        $this->enTantQue($this->jetonVide)->getJson('/api/admin/dashboard/top-absences')
            ->assertStatus(200)->assertJsonPath('data', []);
        $this->enTantQue($this->jetonVide)->getJson('/api/admin/dashboard/today-events')
            ->assertStatus(200)->assertJsonPath('data', []);
    }

    public function test_les_chiffres_dun_etablissement_nincluent_jamais_ceux_dun_autre(): void
    {
        $reponse = $this->enTantQue($this->jetonB)->getJson('/api/admin/dashboard');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_etudiants', 5)
            ->assertJsonPath('data.cours_du_jour', 1)
            ->assertJsonPath('data.presences_aujourd_hui', 0)
            ->assertJsonPath('data.presences_valides', 0)
            ->assertJsonPath('data.presences_suspectes', 0)
            ->assertJsonPath('data.fraudes_suspectees', 1)
            ->assertJsonPath('data.heatmap', []);

        // 1 présent sur 5 inscrits : le taux de A (66,7 %) ne fuit pas ici.
        $this->assertSame(20.0, (float) $reponse->json('data.taux_presence_global'));

        $anomalies = $reponse->json('data.dernieres_anomalies');
        $this->assertCount(1, $anomalies);
        $this->assertSame('Anomalie de B', $anomalies[0]['description']);
    }

    // ── GET /admin/dashboard/attendance-trend ─────────────────────────────

    public function test_la_tendance_couvre_la_fenetre_de_trente_jours_et_reste_ordonnee(): void
    {
        $reponse = $this->enTantQue($this->jetonA)->getJson('/api/admin/dashboard/attendance-trend');
        $reponse->assertStatus(200);

        $serie = $reponse->json('data');
        $dates = array_column($serie, 'date');

        $this->assertSame([
            today()->subDays(5)->format('Y-m-d'),
            today()->subDay()->format('Y-m-d'),
            today()->format('Y-m-d'),
        ], $dates, 'Les points doivent être ordonnés par date croissante.');

        $this->assertSame([1, 2, 3], array_map('intval', array_column($serie, 'total')));
        $this->assertSame([1, 2, 2], array_map('intval', array_column($serie, 'valides')));
        $this->assertSame([0, 0, 1], array_map('intval', array_column($serie, 'suspectes')));

        // Hors fenêtre (40 jours) et hors périmètre (établissement B) exclus.
        $this->assertNotContains(today()->subDays(40)->format('Y-m-d'), $dates);
        $this->assertNotContains(today()->subDays(3)->format('Y-m-d'), $dates);

        $plancher = today()->subDays(30);
        foreach ($dates as $date) {
            $this->assertTrue(
                Carbon::parse($date)->greaterThanOrEqualTo($plancher),
                "La date {$date} sort de la fenêtre de 30 jours."
            );
        }
    }

    public function test_la_tendance_est_cloisonnee_par_etablissement(): void
    {
        $serie = $this->enTantQue($this->jetonB)
            ->getJson('/api/admin/dashboard/attendance-trend')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $serie, 'B n\'a qu\'un seul jour de présences.');
        $this->assertSame(today()->subDays(3)->format('Y-m-d'), $serie[0]['date']);
        $this->assertSame(1, (int) $serie[0]['total']);
    }

    // ── GET /admin/dashboard/top-absences ─────────────────────────────────

    public function test_le_top_des_absences_est_ordonne_du_plus_absent_au_moins_absent(): void
    {
        $reponse = $this->enTantQue($this->jetonA)->getJson('/api/admin/dashboard/top-absences');
        $reponse->assertStatus(200);

        $liste = $reponse->json('data');
        $this->assertCount(3, $liste);

        // 5 événements passés dans A ; présences cumulées : A1 = 4, A2 = 2, A3 = 1.
        // Le classement attendu est donc A3 (4 absences), A2 (3), A1 (1).
        $this->assertSame([
            $this->etudiantsA[3]->matricule,
            $this->etudiantsA[2]->matricule,
            $this->etudiantsA[1]->matricule,
        ], array_column($liste, 'matricule'));

        $this->assertSame([4, 3, 1], array_map('intval', array_column($liste, 'absences')));
        $this->assertSame([1, 2, 4], array_map('intval', array_column($liste, 'total_presences')));
        $this->assertSame($this->filiereA->code, $liste[0]['filiere_code']);
        $this->assertSame($this->etudiantsA[3]->nom, $liste[0]['nom']);

        // Aucun étudiant de l'établissement B dans le classement de A.
        $matriculesB = array_map(fn (Etudiant $e) => $e->matricule, $this->etudiantsB);
        $this->assertSame([], array_intersect($matriculesB, array_column($liste, 'matricule')));
    }

    public function test_le_top_des_absences_respecte_la_limite_de_dix(): void
    {
        $etablissement = $this->creerEtablissement('L');
        $jeton         = $this->jetonAdmin($etablissement);
        [$filiere, $ec] = $this->creerCursus($etablissement, 'L');

        $cours = $this->creerEvenement($filiere, $ec, today()->subDay(), '08:00:00', '10:00:00', 'termine', 'L-Hier');

        $sansAbsence = [];
        for ($i = 1; $i <= 12; $i++) {
            $etudiant = $this->creerEtudiant($filiere, "L{$i}", $ec);

            // Deux étudiants présents à l'unique cours passé : zéro absence,
            // ils doivent être évincés par les dix autres.
            if ($i <= 2) {
                $this->creerPresence($etudiant, $cours, today()->subDay()->setTime(9, 0), 'valide');
                $sansAbsence[] = $etudiant->matricule;
            }
        }

        $liste = $this->enTantQue($jeton)
            ->getJson('/api/admin/dashboard/top-absences')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(10, $liste, 'Le classement est plafonné à 10 étudiants.');

        $absences    = array_map('intval', array_column($liste, 'absences'));
        $decroissant = $absences;
        rsort($decroissant);
        $this->assertSame($decroissant, $absences, 'Le classement doit rester décroissant.');
        $this->assertSame(array_fill(0, 10, 1), $absences);

        $matricules = array_column($liste, 'matricule');
        foreach ($sansAbsence as $matricule) {
            $this->assertNotContains($matricule, $matricules, 'Un étudiant sans absence ne doit pas figurer au classement.');
        }
    }

    // ── GET /admin/dashboard/today-events ─────────────────────────────────

    public function test_les_evenements_du_jour_sont_ordonnes_et_limites_a_aujourdhui(): void
    {
        $reponse = $this->enTantQue($this->jetonA)->getJson('/api/admin/dashboard/today-events');
        $reponse->assertStatus(200);

        $evenements = $reponse->json('data');
        $this->assertCount(2, $evenements);

        $this->assertSame([$this->coursMatin->id, $this->coursApresMidi->id], array_column($evenements, 'id'));
        $this->assertSame(['08:00:00', '14:00:00'], array_column($evenements, 'heure_debut'));
        $this->assertSame([3, 0], array_map('intval', array_column($evenements, 'presences_count')));

        $this->assertSame($this->ecA->intitule, $evenements[0]['cours']);
        $this->assertSame($this->filiereA->code, $evenements[0]['filiere']);
        $this->assertSame('A-Matin', $evenements[0]['salle']);
        $this->assertSame('10:00:00', $evenements[0]['heure_fin']);
        $this->assertSame('planifie', $evenements[0]['statut']);

        // Ni les autres jours, ni l'établissement voisin.
        $ids = array_column($evenements, 'id');
        $this->assertNotContains($this->coursHier->id, $ids);
        $this->assertNotContains($this->coursJ5->id, $ids);
        $this->assertNotContains($this->coursJ40->id, $ids);
        $this->assertNotContains($this->coursDuJourB->id, $ids);
    }

    public function test_les_evenements_du_jour_sont_cloisonnes_par_etablissement(): void
    {
        $evenements = $this->enTantQue($this->jetonB)
            ->getJson('/api/admin/dashboard/today-events')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $evenements);
        $this->assertSame($this->coursDuJourB->id, $evenements[0]['id']);
        $this->assertSame(0, (int) $evenements[0]['presences_count']);
    }

    // ── Sécurité ──────────────────────────────────────────────────────────

    public function test_les_endpoints_du_tableau_de_bord_exigent_un_jeton(): void
    {
        foreach (self::ROUTES as $route) {
            $this->getJson($route)->assertStatus(401);
        }
    }

    public function test_un_jeton_detudiant_est_refuse_sur_le_tableau_de_bord(): void
    {
        $jeton = $this->etudiantsA[1]->createToken('mobile-app', ['etudiant'])->plainTextToken;

        foreach (self::ROUTES as $route) {
            $this->enTantQue($jeton)->getJson($route)->assertStatus(403);
        }
    }

    // ── Fabrication des données ───────────────────────────────────────────

    /**
     * Ajoute l'en-tête d'authentification du jeton donné.
     */
    private function enTantQue(string $jeton): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $jeton);
    }

    private function creerEtablissement(string $cle): int
    {
        return DB::table('etablissements')->insertGetId([
            'code'       => "DASH{$cle}{$this->sfx}",
            'nom'        => "Établissement {$cle} {$this->sfx}",
            'email'      => strtolower("dash-{$cle}-{$this->sfx}@example.test"),
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function jetonAdmin(int $etablissementId): string
    {
        return User::factory()
            ->faculteAdmin($etablissementId)
            ->create()
            ->createToken('test-token')
            ->plainTextToken;
    }

    /**
     * Filière + UE + EC rattachés à un établissement.
     *
     * @return array{0: Filiere, 1: Ec}
     */
    private function creerCursus(int $etablissementId, string $cle): array
    {
        $filiere = Filiere::create([
            'code'             => "FIL{$cle}{$this->sfx}",
            'intitule'         => "Filière {$cle}",
            'niveau'           => 'L1',
            'etablissement_id' => $etablissementId,
        ]);

        $ue = Ue::create([
            'code'           => "UE{$cle}{$this->sfx}",
            'intitule'       => "UE {$cle}",
            'filiere_id'     => $filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 20,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => "EC{$cle}{$this->sfx}",
            'intitule'       => "Cours magistral {$cle}",
            'volume_horaire' => 20,
        ]);

        return [$filiere, $ec];
    }

    private function creerEtudiant(Filiere $filiere, string $cle, Ec $ec): Etudiant
    {
        $etudiant = Etudiant::create([
            'nom'                => 'DASH' . $cle,
            'prenom'             => 'Etudiant ' . $cle,
            'matricule'          => "DASH-{$this->sfx}-{$cle}",
            'filiere_id'         => $filiere->id,
            'annee_id'           => $this->annee->id,
            'email'              => strtolower("dash.{$this->sfx}.{$cle}@example.test"),
            'identifiant_unique' => "DASH_{$this->sfx}_{$cle}",
        ]);

        // Inscription explicite à l'EC : c'est elle qui forme le dénominateur
        // du taux de présence.
        $etudiant->ecs()->attach([$ec->id => ['annee_id' => $this->annee->id]]);

        return $etudiant;
    }

    private function creerEvenement(
        Filiere $filiere,
        Ec $ec,
        Carbon $date,
        string $heureDebut,
        string $heureFin,
        string $statut,
        string $salle
    ): Evenement {
        return Evenement::create([
            'ec_id'       => $ec->id,
            'filiere_id'  => $filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => $date->format('Y-m-d'),
            'heure_debut' => $heureDebut,
            'heure_fin'   => $heureFin,
            'salle'       => $salle,
            'statut'      => $statut,
        ]);
    }

    private function creerPresence(Etudiant $etudiant, Evenement $evenement, Carbon $heureScan, string $statut): Presence
    {
        return Presence::create([
            'etudiant_id'        => $etudiant->id,
            'evenement_id'       => $evenement->id,
            'heure_scan'         => $heureScan,
            'device_fingerprint' => 'dash-' . $this->sfx,
            'statut'             => $statut,
        ]);
    }

    private function creerAnomalie(Etudiant $etudiant, string $description, Carbon $creeeLe, bool $resolue = false): Anomaly
    {
        $anomalie = Anomaly::create([
            'etudiant_id' => $etudiant->id,
            'type'        => 'appareil_partage',
            'description' => $description,
            'severity'    => 'high',
            'resolved'    => $resolue,
        ]);

        // created_at imposé : `latest()` doit trier sur des dates distinctes.
        $anomalie->created_at = $creeeLe;
        $anomalie->save();

        return $anomalie;
    }
}
