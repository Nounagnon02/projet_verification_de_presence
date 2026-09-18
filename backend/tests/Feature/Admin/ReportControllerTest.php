<?php

namespace Tests\Feature\Admin;

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
 * Suite de tests du contrôleur de rapports (ReportController).
 *
 * Couvre les 9 endpoints :
 *   - GET /admin/reports/presence/{evenementId}/pdf   exportPdf
 *   - GET /admin/reports/department/{filiere}          departmentReport
 *   - GET /admin/reports/semester/{anneeAcademique}    semesterReport
 *   - GET /admin/reports/semester-comparison           semesterComparison
 *   - GET /admin/reports/filiere-stats                 filiereStats
 *   - GET /admin/reports/filtered                      filteredStats
 *   - GET /admin/reports/excel/export                  excelExport
 *   - GET /admin/reports/etudiants-absents             etudiantsAbsents
 *   - GET /admin/reports/annee-stats                   anneeStats
 *
 * Jeu de données principal : 1 filière (établissement A, niveau L1), 1 UE de
 * semestre 1, 1 EC, 1 événement passé, 4 étudiants inscrits à l'EC et 3
 * présences validées. Le taux attendu partout est donc 3/4 = 75 %.
 *
 * Certains tests constatent un DÉFAUT du contrôleur au lieu de vérifier le
 * comportement souhaitable : ils sont annotés « DÉFAUT CONSTATÉ ». Le
 * contrôleur n'étant pas modifiable dans le cadre de cette mission, ces
 * assertions figent le comportement actuel et devront être inversées lors de
 * la correction.
 */
class ReportControllerTest extends TestCase
{
    /** Date figée : les rapports dépendent tous de now(). */
    private const MAINTENANT = '2026-03-15 10:00:00';

    /** Horodatage attendu dans les noms de fichiers exportés. */
    private const HORODATAGE = '20260315_100000';

    /** Date de l'événement du jeu de données principal (mars → trimestre 3). */
    private const DATE_EVENEMENT = '2026-03-10';

    private int $etabA;
    private int $etabB;
    private string $jetonA;
    private string $jetonB;

    private \App\Models\AnneeAcademique $annee;
    private Filiere $filiere;
    private Ue $ue;
    private Ec $ec;
    private Evenement $evenement;

    /** @var \Illuminate\Support\Collection<int, Etudiant> */
    private $etudiants;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::MAINTENANT);

        $this->annee = $this->anneeActive();

        $this->etabA = $this->creerEtablissement('A');
        $this->etabB = $this->creerEtablissement('B');

        $this->jetonA = User::factory()->faculteAdmin($this->etabA)->create()
            ->createToken('rapports-a')->plainTextToken;
        $this->jetonB = User::factory()->faculteAdmin($this->etabB)->create()
            ->createToken('rapports-b')->plainTextToken;

        $jeu = $this->creerJeuDeDonnees($this->etabA, 'PRI', 'L1', 1, 4, 3);

        $this->filiere   = $jeu['filiere'];
        $this->ue        = $jeu['ue'];
        $this->ec        = $jeu['ec'];
        $this->evenement = $jeu['evenement'];
        $this->etudiants = $jeu['etudiants'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─── Fabrication des données ──────────────────────────────────

    /**
     * Crée un établissement et retourne son identifiant.
     */
    private function creerEtablissement(string $lettre): int
    {
        $sfx = Str::random(6);

        return DB::table('etablissements')->insertGetId([
            'code'       => $lettre . $sfx,
            'nom'        => 'Établissement ' . $lettre . ' ' . $sfx,
            'email'      => strtolower("etab-{$lettre}-{$sfx}@example.test"),
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Fabrique une filière complète : UE, EC, un événement passé, des
     * étudiants inscrits à l'EC et des présences validées.
     *
     * @return array{filiere: Filiere, ue: Ue, ec: Ec, evenement: Evenement, etudiants: \Illuminate\Support\Collection<int, Etudiant>}
     */
    private function creerJeuDeDonnees(
        int $etablissementId,
        string $code,
        string $niveau,
        int $semestre,
        int $nbEtudiants,
        int $nbPresencesValides,
        string $date = self::DATE_EVENEMENT
    ): array {
        $sfx = Str::random(6);

        $filiere = Filiere::create([
            'code'             => $code . '-' . $sfx,
            'intitule'         => 'Filière ' . $code,
            'niveau'           => $niveau,
            'etablissement_id' => $etablissementId,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-' . $sfx,
            'intitule'       => 'Programmation ' . $code,
            'filiere_id'     => $filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => $semestre,
            'volume_horaire' => 30,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-' . $sfx,
            'intitule'       => 'Algorithmique ' . $code,
            'volume_horaire' => 30,
        ]);

        $evenement = Evenement::create([
            'ec_id'       => $ec->id,
            'filiere_id'  => $filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => $date,
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'Amphi ' . $code,
            'statut'      => 'termine',
        ]);

        $etudiants = $nbEtudiants > 0
            ? collect(range(1, $nbEtudiants))->map(function ($i) use ($filiere, $ec, $sfx) {
                $etudiant = Etudiant::create([
                    'nom'                => 'RAPPORT' . $i,
                    'prenom'             => 'Prenom' . $i,
                    'matricule'          => "MAT-{$sfx}-{$i}",
                    'filiere_id'         => $filiere->id,
                    'annee_id'           => $this->annee->id,
                    'email'              => strtolower("etu-{$sfx}-{$i}@example.test"),
                    'identifiant_unique' => "RAPPORT_{$sfx}_{$i}",
                ]);

                $etudiant->ecs()->syncWithoutDetaching([$ec->id => ['annee_id' => $this->annee->id]]);

                return $etudiant;
            })->values()
            : collect();

        for ($i = 0; $i < $nbPresencesValides; $i++) {
            Presence::create([
                'etudiant_id'        => $etudiants[$i]->id,
                'evenement_id'       => $evenement->id,
                'heure_scan'         => Carbon::parse($date . ' 08:05:00'),
                'device_fingerprint' => "empreinte-{$sfx}-{$i}",
                'ip_address'         => '10.0.0.' . ($i + 1),
                'statut'             => 'valide',
            ]);
        }

        return compact('filiere', 'ue', 'ec', 'evenement', 'etudiants');
    }

    /** Séance supplémentaire de l'EC du jeu principal. */
    private function creerSeance(string $date, string $statut): Evenement
    {
        return Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => $date,
            'heure_debut' => '10:00:00',
            'heure_fin'   => '12:00:00',
            'statut'      => $statut,
        ]);
    }

    private function creerPresence(Etudiant $etudiant, Evenement $evenement, string $statut): Presence
    {
        return Presence::create([
            'etudiant_id'        => $etudiant->id,
            'evenement_id'       => $evenement->id,
            'heure_scan'         => Carbon::parse($evenement->date->format('Y-m-d') . ' 10:05:00'),
            'device_fingerprint' => 'empreinte-' . Str::random(8),
            'ip_address'         => '10.0.1.' . random_int(1, 250),
            'statut'             => $statut,
        ]);
    }

    /**
     * Filière sans UE, sans événement et sans étudiant : sert aux cas de
     * division par zéro.
     */
    private function creerFiliereVide(int $etablissementId): Filiere
    {
        return Filiere::create([
            'code'             => 'VIDE-' . Str::random(6),
            'intitule'         => 'Filière sans effectif',
            'niveau'           => 'L2',
            'etablissement_id' => $etablissementId,
        ]);
    }

    /**
     * Liste des URL du contrôleur, pour les tests transverses.
     *
     * @return array<int, string>
     */
    private function toutesLesUrls(): array
    {
        return [
            '/api/admin/reports/presence/' . $this->evenement->id . '/pdf',
            '/api/admin/reports/department/' . $this->filiere->id,
            '/api/admin/reports/semester/' . $this->annee->id,
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id . '&filiere_id=' . $this->filiere->id,
            '/api/admin/reports/filiere-stats?annee_id=' . $this->annee->id,
            '/api/admin/reports/filtered',
            '/api/admin/reports/excel/export',
            '/api/admin/reports/etudiants-absents',
            '/api/admin/reports/annee-stats',
        ];
    }

    /**
     * Lit une valeur numérique de la réponse en garantissant qu'elle est
     * exploitable : ni null, ni NAN, ni chaîne non numérique.
     */
    private function nombre(\Illuminate\Testing\TestResponse $reponse, string $chemin): float
    {
        $valeur = $reponse->json($chemin);

        $this->assertNotNull($valeur, "La valeur {$chemin} ne doit jamais être null.");
        $this->assertIsNumeric($valeur, "La valeur {$chemin} doit être numérique.");
        $this->assertFalse(is_nan((float) $valeur), "La valeur {$chemin} ne doit jamais être NAN.");

        return (float) $valeur;
    }

    // ─── Sécurité : authentification et capacités ─────────────────

    public function test_sans_jeton_tous_les_endpoints_repondent_401(): void
    {
        foreach ($this->toutesLesUrls() as $url) {
            $this->getJson($url)
                ->assertStatus(401)
                ->assertJsonPath('message', 'Non authentifié.');
        }
    }

    public function test_un_jeton_etudiant_est_refuse_avec_403(): void
    {
        $jetonEtudiant = $this->etudiants[0]->createToken('mobile-app', ['etudiant'])->plainTextToken;

        foreach ($this->toutesLesUrls() as $url) {
            $this->withToken($jetonEtudiant)->getJson($url)->assertStatus(403);
        }
    }

    // ─── Sécurité : cloisonnement par établissement ───────────────

    public function test_le_rapport_filtre_ne_fuit_pas_vers_un_autre_etablissement(): void
    {
        $reponse = $this->withToken($this->jetonB)->getJson('/api/admin/reports/filtered');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_evenements', 0)
            ->assertJsonPath('data.total_etudiants', 0)
            ->assertJsonPath('data.presences_valides', 0)
            ->assertJsonPath('data.presences_suspectes', 0)
            ->assertJsonPath('data.evolution', [])
            ->assertJsonPath('data.stats_par_ue', []);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_les_stats_par_filiere_ne_fuient_pas_vers_un_autre_etablissement(): void
    {
        $reponse = $this->withToken($this->jetonB)
            ->getJson('/api/admin/reports/filiere-stats?annee_id=' . $this->annee->id);

        $reponse->assertStatus(200)->assertJsonPath('data', []);
    }

    public function test_l_export_csv_ne_fuit_pas_vers_un_autre_etablissement(): void
    {
        $reponse = $this->withToken($this->jetonB)->get('/api/admin/reports/excel/export');

        $reponse->assertStatus(200);

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('Matricule', $contenu);
        $this->assertStringNotContainsString($this->etudiants[0]->matricule, $contenu);
        $this->assertStringNotContainsString($this->filiere->code, $contenu);
    }

    public function test_le_rapport_semestriel_cloisonne_les_agregats_par_etablissement(): void
    {
        $reponse = $this->withToken($this->jetonB)
            ->getJson('/api/admin/reports/semester/' . $this->annee->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.stats_par_semestre', [])
            ->assertJsonPath('data.stats_par_filiere', [])
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_evenements', 0);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_presence'));

        // Régression : total_etudiants était compté sur TOUTE la base
        // (Etudiant::where('annee_id', ...) sans filtre d'établissement) —
        // l'admin de l'établissement B lisait l'effectif de l'établissement A.
        $this->assertSame(0, $reponse->json('data.total_etudiants'));
    }

    public function test_le_rapport_par_filiere_est_cloisonne(): void
    {
        // Régression : departmentReport recevait la filière par route model
        // binding sans appeler authorizeEtablissement(), et un admin de
        // l'établissement B obtenait l'intégralité du rapport d'une filière de
        // l'établissement A.
        $this->withToken($this->jetonB)
            ->getJson('/api/admin/reports/department/' . $this->filiere->id)
            ->assertStatus(404);
    }

    public function test_l_export_pdf_est_cloisonne(): void
    {
        // Régression : exportPdf faisait un Evenement::findOrFail() sans
        // contrôle d'établissement, et un admin de l'établissement B
        // téléchargeait la feuille de présence d'un événement de
        // l'établissement A.
        $this->withToken($this->jetonB)
            ->get('/api/admin/reports/presence/' . $this->evenement->id . '/pdf')
            ->assertStatus(404);
    }

    public function test_la_comparaison_semestrielle_est_cloisonnee(): void
    {
        // La filière vient du client : un autre établissement ne lit pas ses taux.
        $this->withToken($this->jetonB)->getJson(
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id
            . '&filiere_id=' . $this->filiere->id
        )->assertStatus(404);
    }

    // ─── exportPdf ────────────────────────────────────────────────

    public function test_export_pdf_retourne_un_binaire_pdf_nomme(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->get('/api/admin/reports/presence/' . $this->evenement->id . '/pdf');

        $reponse->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');

        $disposition = $reponse->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString(
            "presence_{$this->evenement->id}_" . self::HORODATAGE . '.pdf',
            $disposition
        );

        $contenu = $reponse->getContent();
        $this->assertStringStartsWith('%PDF', $contenu);
        $this->assertGreaterThan(1000, strlen($contenu), 'Le PDF doit être un binaire non vide.');
    }

    public function test_export_pdf_d_un_evenement_inexistant_renvoie_404(): void
    {
        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/presence/999999/pdf')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Ressource non trouvée.');
    }

    public function test_export_pdf_d_un_evenement_sans_presence_reste_valide(): void
    {
        $evenementVide = Evenement::create([
            'ec_id'       => $this->ec->id,
            'filiere_id'  => $this->filiere->id,
            'annee_id'    => $this->annee->id,
            'date'        => self::DATE_EVENEMENT,
            'heure_debut' => '14:00:00',
            'heure_fin'   => '16:00:00',
            'salle'       => 'Salle B',
            'statut'      => 'termine',
        ]);

        $reponse = $this->withToken($this->jetonA)
            ->get('/api/admin/reports/presence/' . $evenementVide->id . '/pdf');

        $reponse->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    // ─── departmentReport ─────────────────────────────────────────

    public function test_rapport_par_filiere_calcule_le_taux_sur_les_inscrits(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/department/' . $this->filiere->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.filiere.id', $this->filiere->id)
            ->assertJsonPath('data.filiere.code', $this->filiere->code)
            ->assertJsonPath('data.filiere.intitule', $this->filiere->intitule)
            ->assertJsonPath('data.total_etudiants', 4)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonCount(1, 'data.presences_par_cours')
            ->assertJsonPath('data.presences_par_cours.0.cours', $this->ec->intitule)
            ->assertJsonPath('data.presences_par_cours.0.date', self::DATE_EVENEMENT);

        // 3 présences validées sur 4 inscrits attendus = 75 %.
        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_presence'));
        $this->assertSame(3, (int) $reponse->json('data.presences_par_cours.0.presences_count'));
    }

    public function test_rapport_par_filiere_sans_etudiant_renvoie_un_taux_nul(): void
    {
        $vide = $this->creerFiliereVide($this->etabA);

        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/department/' . $vide->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_etudiants', 0)
            ->assertJsonPath('data.total_evenements', 0)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.presences_par_cours', []);

        // Division par zéro : le taux doit valoir 0, jamais null ni NAN.
        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_presence'));
    }

    public function test_rapport_par_filiere_dont_les_inscrits_n_ont_pas_scanne(): void
    {
        // 2 inscrits, 0 présence : le dénominateur est non nul, le taux vaut 0.
        $jeu = $this->creerJeuDeDonnees($this->etabA, 'SEC', 'L2', 3, 2, 0);

        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/department/' . $jeu['filiere']->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_etudiants', 2)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.total_presences', 0);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_presence'));
    }

    public function test_rapport_par_filiere_inexistante_renvoie_404(): void
    {
        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/department/999999')
            ->assertStatus(404);
    }

    // ─── semesterReport ───────────────────────────────────────────

    public function test_rapport_semestriel_detaille_les_taux_par_semestre(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/semester/' . $this->annee->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.annee_academique.id', $this->annee->id)
            ->assertJsonPath('data.annee_academique.annee', $this->annee->libelle)
            ->assertJsonCount(1, 'data.stats_par_semestre')
            ->assertJsonPath('data.stats_par_semestre.0.semestre', 1)
            ->assertJsonPath('data.stats_par_semestre.0.label', 'S1')
            ->assertJsonPath('data.stats_par_semestre.0.total_presences', 3)
            ->assertJsonPath('data.stats_par_semestre.0.total_evenements', 1)
            ->assertJsonPath('data.stats_par_semestre.0.total_etudiants', 4)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.total_etudiants', 4)
            ->assertJsonCount(1, 'data.stats_par_filiere')
            ->assertJsonPath('data.stats_par_filiere.0.id', $this->filiere->id)
            ->assertJsonPath('data.stats_par_filiere.0.code', $this->filiere->code)
            ->assertJsonPath('data.stats_par_filiere.0.niveau', 'L1')
            ->assertJsonPath('data.stats_par_filiere.0.total_presences', 3);

        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_presence'));
        $this->assertSame(75.0, $this->nombre($reponse, 'data.stats_par_semestre.0.taux'));
        $this->assertSame(75.0, $this->nombre($reponse, 'data.stats_par_filiere.0.taux'));
    }

    public function test_rapport_semestriel_filtre_par_semestre(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/semester/' . $this->annee->id . '?semestre=2');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.stats_par_semestre', [])
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_evenements', 0);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_presence'));
    }

    public function test_rapport_semestriel_filtre_par_filiere(): void
    {
        $autre = $this->creerFiliereVide($this->etabA);

        $reponse = $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/semester/' . $this->annee->id . '?filiere_id=' . $autre->id
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.stats_par_semestre', []);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_presence'));

        // DÉFAUT CONSTATÉ — ReportController.php:170-183 : le bloc
        // stats_par_filiere n'applique jamais le filtre filiere_id (contrairement
        // aux blocs stats_par_semestre et taux_presence). La réponse contient
        // donc la filière principale alors que le rapport est filtré sur une
        // autre filière. Après correction, ce tableau doit être vide.
        $reponse->assertJsonCount(1, 'data.stats_par_filiere')
            ->assertJsonPath('data.stats_par_filiere.0.id', $this->filiere->id);
    }

    public function test_rapport_semestriel_d_une_annee_inexistante_renvoie_404(): void
    {
        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/semester/999999')
            ->assertStatus(404);
    }

    // ─── semesterComparison ───────────────────────────────────────

    public function test_comparaison_semestrielle_couvre_les_deux_semestres_du_niveau(): void
    {
        $reponse = $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id
            . '&filiere_id=' . $this->filiere->id
        );

        // Niveau L1 → semestres 1 et 2.
        $reponse->assertStatus(200)
            ->assertJsonPath('data.filiere.id', $this->filiere->id)
            ->assertJsonPath('data.filiere.niveau', 'L1')
            ->assertJsonCount(2, 'data.semestres')
            ->assertJsonPath('data.semestres.0.semestre', 1)
            ->assertJsonPath('data.semestres.0.label', 'S1')
            ->assertJsonPath('data.semestres.0.total_presences', 3)
            ->assertJsonPath('data.semestres.1.semestre', 2)
            ->assertJsonPath('data.semestres.1.label', 'S2')
            ->assertJsonPath('data.semestres.1.total_presences', 0);

        // S1 : 3 présences pour 1 événement × 4 inscrits = 75 %.
        $this->assertSame(75.0, $this->nombre($reponse, 'data.semestres.0.taux'));
        // S2 : aucune UE, donc division par zéro évitée → 0.
        $this->assertSame(0.0, $this->nombre($reponse, 'data.semestres.1.taux'));
    }

    public function test_comparaison_semestrielle_exige_annee_id_et_filiere_id(): void
    {
        $message = 'Les paramètres annee_id et filiere_id sont requis.';

        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/semester-comparison')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', $message);

        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id)
            ->assertStatus(422)
            ->assertJsonPath('message', $message);

        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/semester-comparison?filiere_id=' . $this->filiere->id)
            ->assertStatus(422)
            ->assertJsonPath('message', $message);
    }

    public function test_comparaison_semestrielle_d_une_filiere_inexistante_renvoie_404(): void
    {
        $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id . '&filiere_id=999999'
        )->assertStatus(404);
    }

    public function test_comparaison_semestrielle_d_une_filiere_sans_donnee_renvoie_des_taux_nuls(): void
    {
        $vide = $this->creerFiliereVide($this->etabA);

        $reponse = $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id
            . '&filiere_id=' . $vide->id
        );

        // Niveau L2 → semestres 3 et 4, tous deux sans données.
        $reponse->assertStatus(200)
            ->assertJsonCount(2, 'data.semestres')
            ->assertJsonPath('data.semestres.0.semestre', 3)
            ->assertJsonPath('data.semestres.1.semestre', 4)
            ->assertJsonPath('data.semestres.0.total_presences', 0)
            ->assertJsonPath('data.semestres.1.total_presences', 0);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.semestres.0.taux'));
        $this->assertSame(0.0, $this->nombre($reponse, 'data.semestres.1.taux'));
    }

    public function test_comparaison_semestrielle_donne_le_taux_du_reste_des_rapports(): void
    {
        // Une seconde séance terminée sans scan, et une séance à venir : seule
        // la première compte, comme au classement des filières.
        $this->creerSeance('2026-03-11', 'termine');
        $this->creerSeance('2026-03-20', 'planifie');

        $semestres = $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id . '&filiere_id=' . $this->filiere->id
        )->assertStatus(200)
            ->assertJsonPath('data.semestres.0.presences_attendues', 8)
            ->assertJsonPath('data.semestres.0.total_evenements', 2);

        $classement = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filiere-stats?annee_id=' . $this->annee->id)
            ->assertStatus(200);

        // 3 présents sur 2 séances × 4 inscrits.
        $this->assertSame(37.5, $this->nombre($semestres, 'data.semestres.0.taux'));
        $this->assertSame($this->nombre($classement, 'data.0.taux'), $this->nombre($semestres, 'data.semestres.0.taux'));
    }

    // ─── filiereStats ─────────────────────────────────────────────

    public function test_stats_par_filiere_calculent_le_taux_attendu(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filiere-stats?annee_id=' . $this->annee->id);

        $reponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->filiere->id)
            ->assertJsonPath('data.0.code', $this->filiere->code)
            ->assertJsonPath('data.0.niveau', 'L1')
            ->assertJsonPath('data.0.etudiants_count', 4)
            ->assertJsonPath('data.0.total_evenements', 1)
            ->assertJsonPath('data.0.total_presences', 3);

        // 3 présences / (1 événement × 4 étudiants) = 75 %.
        $this->assertSame(75.0, $this->nombre($reponse, 'data.0.taux'));
    }

    public function test_stats_par_filiere_sans_etudiant_renvoient_un_taux_nul(): void
    {
        $vide = $this->creerFiliereVide($this->etabA);

        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filiere-stats?annee_id=' . $this->annee->id);

        $reponse->assertStatus(200)->assertJsonCount(2, 'data');

        $ligne = collect($reponse->json('data'))->firstWhere('id', $vide->id);

        $this->assertNotNull($ligne, 'La filière vide doit apparaître dans les stats.');
        $this->assertSame(0, $ligne['etudiants_count']);
        $this->assertSame(0, $ligne['total_evenements']);
        $this->assertSame(0, $ligne['total_presences']);
        $this->assertNotNull($ligne['taux']);
        $this->assertEqualsWithDelta(0.0, (float) $ligne['taux'], 0.001);
        $this->assertFalse(is_nan((float) $ligne['taux']));
    }

    public function test_stats_par_filiere_sont_triees_par_taux_decroissant(): void
    {
        // Filière à 100 % (2 inscrits, 2 présences) et filière vide à 0 %.
        $haute = $this->creerJeuDeDonnees($this->etabA, 'TOP', 'L3', 5, 2, 2);
        $vide  = $this->creerFiliereVide($this->etabA);

        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filiere-stats?annee_id=' . $this->annee->id);

        $reponse->assertStatus(200)->assertJsonCount(3, 'data');

        $this->assertSame(
            [$haute['filiere']->id, $this->filiere->id, $vide->id],
            collect($reponse->json('data'))->pluck('id')->all()
        );

        $this->assertSame(100.0, $this->nombre($reponse, 'data.0.taux'));
        $this->assertSame(75.0, $this->nombre($reponse, 'data.1.taux'));
        $this->assertSame(0.0, $this->nombre($reponse, 'data.2.taux'));
    }

    public function test_stats_par_filiere_sans_annee_id_renvoient_des_compteurs_nuls(): void
    {
        // Sans annee_id, integer() vaut 0 : aucun rattachement ne correspond.
        $reponse = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filiere-stats');

        $reponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.etudiants_count', 0)
            ->assertJsonPath('data.0.total_evenements', 0)
            ->assertJsonPath('data.0.total_presences', 0);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.0.taux'));
    }

    // ─── filteredStats ────────────────────────────────────────────

    public function test_rapport_filtre_sans_filtre_agrege_l_etablissement(): void
    {
        $reponse = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.total_etudiants', 4)
            ->assertJsonPath('data.presences_valides', 3)
            ->assertJsonPath('data.presences_suspectes', 0)
            ->assertJsonPath('data.presences_attendues', 4)
            ->assertJsonPath('data.absences', 1)
            // Évolution hebdomadaire : la semaine du 10/03 commence le lundi 09/03.
            ->assertJsonCount(1, 'data.evolution')
            ->assertJsonPath('data.evolution.0.semaine', '2026-03-09')
            ->assertJsonPath('data.evolution.0.attendus', 4)
            ->assertJsonCount(1, 'data.stats_par_ue')
            ->assertJsonPath('data.stats_par_ue.0.ue_id', $this->ue->id)
            ->assertJsonPath('data.stats_par_ue.0.code', $this->ue->code)
            ->assertJsonPath('data.stats_par_ue.0.intitule', $this->ue->intitule)
            ->assertJsonPath('data.stats_par_ue.0.semestre', 1)
            ->assertJsonPath('data.stats_par_ue.0.filiere_code', $this->filiere->code)
            ->assertJsonPath('data.stats_par_ue.0.total_presences', 3)
            ->assertJsonPath('data.stats_par_ue.0.total_evenements', 1)
            ->assertJsonPath('data.stats_par_ue.0.total_etudiants', 4)
            ->assertJsonPath('data.filtres_appliques', [
                'filiere_id' => null,
                'annee_id'   => null,
                'semestre'   => null,
                'trimestre'  => null,
                'ue_id'      => null,
                'ec_id'      => null,
            ]);

        $this->assertSame(3, (int) $reponse->json('data.evolution.0.presents'));
        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_global'));
        $this->assertSame(75.0, $this->nombre($reponse, 'data.stats_par_ue.0.taux'));
    }

    public function test_rapport_filtre_par_filiere(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?filiere_id=' . $this->filiere->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.total_etudiants', 4)
            ->assertJsonPath('data.filtres_appliques.filiere_id', $this->filiere->id);

        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_rapport_filtre_par_annee(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?annee_id=' . $this->annee->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.filtres_appliques.annee_id', $this->annee->id)
            ->assertJsonCount(1, 'data.stats_par_ue');

        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_rapport_filtre_par_semestre(): void
    {
        $correspond = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?semestre=1');

        $correspond->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.filtres_appliques.semestre', 1)
            ->assertJsonCount(1, 'data.stats_par_ue');

        $hors = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered?semestre=2');

        $hors->assertStatus(200)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_evenements', 0)
            ->assertJsonPath('data.stats_par_ue', [])
            ->assertJsonPath('data.evolution', []);

        $this->assertSame(0.0, $this->nombre($hors, 'data.taux_global'));
    }

    public function test_rapport_filtre_par_ue(): void
    {
        $correspond = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?ue_id=' . $this->ue->id);

        $correspond->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.filtres_appliques.ue_id', $this->ue->id);

        $this->assertSame(75.0, $this->nombre($correspond, 'data.taux_global'));

        $hors = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered?ue_id=999999');

        $hors->assertStatus(200)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.evolution', []);
    }

    public function test_rapport_filtre_par_ec(): void
    {
        $correspond = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?ec_id=' . $this->ec->id);

        $correspond->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.filtres_appliques.ec_id', $this->ec->id);

        $hors = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered?ec_id=999999');

        $hors->assertStatus(200)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.evolution', []);
    }

    public function test_rapport_filtre_par_plage_de_dates(): void
    {
        $dedans = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?date_debut=2026-03-01&date_fin=2026-03-31');

        $dedans->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.presences_valides', 3);

        $this->assertSame(75.0, $this->nombre($dedans, 'data.taux_global'));

        $dehors = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?date_debut=2026-04-01&date_fin=2026-04-30');

        $dehors->assertStatus(200)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_evenements', 0)
            ->assertJsonPath('data.presences_valides', 0)
            ->assertJsonPath('data.presences_suspectes', 0);

        $this->assertSame(0.0, $this->nombre($dehors, 'data.taux_global'));

        // Les UE et l'évolution suivent la période, comme les compteurs : elles
        // l'ignoraient et restaient peuplées quand les totaux tombaient à zéro.
        $dehors->assertJsonPath('data.stats_par_ue', [])
            ->assertJsonPath('data.evolution', []);
    }

    public function test_rapport_filtre_par_trimestre(): void
    {
        // L'événement est daté de mars → trimestre 3 (mars-avril-mai).
        $correspond = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?trimestre=3');

        $correspond->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.filtres_appliques.trimestre', 3)
            ->assertJsonCount(1, 'data.evolution');

        $this->assertSame(75.0, $this->nombre($correspond, 'data.taux_global'));

        foreach ([1, 2, 4] as $trimestre) {
            $hors = $this->withToken($this->jetonA)
                ->getJson('/api/admin/reports/filtered?trimestre=' . $trimestre);

            $hors->assertStatus(200)
                ->assertJsonPath('data.total_presences', 0)
                ->assertJsonPath('data.total_evenements', 0)
                ->assertJsonPath('data.evolution', []);

            $this->assertSame(0.0, $this->nombre($hors, 'data.taux_global'));
        }
    }

    public function test_rapport_filtre_avec_toutes_les_combinaisons_de_parametres(): void
    {
        $requete = '/api/admin/reports/filtered?'
            . 'filiere_id=' . $this->filiere->id
            . '&annee_id=' . $this->annee->id
            . '&semestre=1'
            . '&ue_id=' . $this->ue->id
            . '&ec_id=' . $this->ec->id
            . '&trimestre=3'
            . '&date_debut=2026-03-01'
            . '&date_fin=2026-03-31'
            . '&jours=60';

        $reponse = $this->withToken($this->jetonA)->getJson($requete);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 3)
            ->assertJsonPath('data.total_evenements', 1)
            ->assertJsonPath('data.total_etudiants', 4)
            ->assertJsonPath('data.presences_valides', 3)
            ->assertJsonPath('data.presences_suspectes', 0)
            ->assertJsonPath('data.filtres_appliques', [
                'filiere_id' => $this->filiere->id,
                'annee_id'   => $this->annee->id,
                'semestre'   => 1,
                'trimestre'  => 3,
                'ue_id'      => $this->ue->id,
                'ec_id'      => $this->ec->id,
            ]);

        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_rapport_filtre_sans_resultat_renvoie_une_structure_vide(): void
    {
        $reponse = $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/filtered?filiere_id=999999&annee_id=999999&semestre=9&ue_id=999999&ec_id=999999'
        );

        $reponse->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => [
                'taux_global', 'total_presences', 'total_evenements', 'total_etudiants',
                'presences_valides', 'presences_suspectes', 'evolution', 'stats_par_ue',
                'filtres_appliques',
            ]])
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_evenements', 0)
            ->assertJsonPath('data.total_etudiants', 0)
            ->assertJsonPath('data.presences_valides', 0)
            ->assertJsonPath('data.presences_suspectes', 0)
            ->assertJsonPath('data.evolution', [])
            ->assertJsonPath('data.stats_par_ue', []);

        // Division par zéro : jamais NAN, jamais null.
        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_rapport_filtre_distingue_presences_valides_et_suspectes(): void
    {
        Presence::create([
            'etudiant_id'        => $this->etudiants[3]->id,
            'evenement_id'       => $this->evenement->id,
            'heure_scan'         => Carbon::parse(self::DATE_EVENEMENT . ' 08:20:00'),
            'device_fingerprint' => 'empreinte-suspecte',
            'ip_address'         => '10.0.0.99',
            'statut'             => 'suspect',
        ]);

        $reponse = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered');

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 4)
            ->assertJsonPath('data.presences_valides', 3)
            ->assertJsonPath('data.presences_suspectes', 1);

        // Seules les présences valides comptent, comme dans les autres rapports
        // et au tableau de bord : le scan suspect ne fait pas monter le taux.
        $this->assertSame(75.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_l_evolution_donne_le_taux_de_chaque_semaine_de_la_periode(): void
    {
        // Mars 2026 : de la semaine du 1er mars (lundi 23/02) au lundi 30/03.
        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?date_debut=2026-03-01&date_fin=2026-03-31')
            ->assertStatus(200);

        $semaines = collect($reponse->json('data.evolution'));

        $this->assertSame(
            ['2026-02-23', '2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23', '2026-03-30'],
            $semaines->pluck('semaine')->all()
        );
        // La semaine du cours : 3 présents sur 4 attendus. Les autres, sans
        // séance, n'ont pas de taux plutôt qu'un 0 % trompeur.
        $this->assertSame(75.0, (float) $semaines->firstWhere('semaine', '2026-03-09')['taux']);
        $this->assertSame([null], $semaines->where('semaine', '!=', '2026-03-09')->pluck('taux')->unique()->values()->all());
    }

    public function test_rapport_filtre_refuse_des_parametres_non_numeriques(): void
    {
        // Une valeur non numérique était convertie en 0 en silence.
        $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?filiere_id=abc&semestre=xyz&trimestre=99&ue_id=%20')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filiere_id', 'semestre', 'trimestre']);
    }

    public function test_le_taux_compte_les_seances_sans_scan_et_ignore_les_non_inscrits(): void
    {
        // Une seconde séance terminée où personne n'a scanné, et une annulée.
        foreach (['2026-03-11' => 'termine', '2026-03-12' => 'annule'] as $date => $statut) {
            Evenement::create([
                'ec_id' => $this->ec->id, 'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id,
                'date' => $date, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'statut' => $statut,
            ]);
        }
        // Un étudiant de la filière, inscrit à aucun cours : jamais attendu.
        Etudiant::create([
            'nom' => 'HORS', 'prenom' => 'Cours', 'matricule' => 'HORS-' . Str::random(5),
            'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id,
            'email' => strtolower('hors-' . Str::random(5) . '@example.test'), 'identifiant_unique' => 'HORS_' . Str::random(5),
        ]);

        $reponse = $this->withToken($this->jetonA)
            ->getJson('/api/admin/reports/filtered?filiere_id=' . $this->filiere->id)
            ->assertStatus(200)
            // 2 séances terminées × 4 inscrits ; l'annulée ne compte pas.
            ->assertJsonPath('data.total_evenements', 2)
            ->assertJsonPath('data.presences_attendues', 8)
            ->assertJsonPath('data.presences_valides', 3)
            ->assertJsonPath('data.absences', 5);

        $this->assertSame(37.5, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_le_rapport_et_le_tableau_de_bord_donnent_le_meme_taux(): void
    {
        $rapport = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered')->assertStatus(200);
        $tableau = $this->withToken($this->jetonA)->getJson('/api/admin/dashboard')->assertStatus(200);

        $this->assertSame(
            $this->nombre($tableau, 'data.taux_presence_global'),
            $this->nombre($rapport, 'data.taux_global')
        );
    }

    public function test_le_rapport_filtre_nomme_l_entite(): void
    {
        $code = DB::table('etablissements')->where('id', $this->etabA)->value('code');

        $reponse = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered')->assertStatus(200);

        $this->assertStringContainsString($code, $reponse->json('data.entite'));
    }

    // ─── anneeStats ───────────────────────────────────────────────

    public function test_stats_par_annee_portent_sur_les_seances_terminees(): void
    {
        // Une séance à venir ne compte pas encore comme une absence.
        $this->creerSeance('2026-03-20', 'planifie');

        $reponse = $this->withToken($this->jetonA)->getJson('/api/admin/reports/annee-stats')->assertStatus(200);

        $ligne = collect($reponse->json('data'))->firstWhere('id', $this->annee->id);

        $this->assertNotNull($ligne, "L'année du jeu de données doit figurer dans la liste.");
        $this->assertSame(4, $ligne['presences_attendues']);
        $this->assertSame(3, $ligne['total_presences']);
        $this->assertSame(1, $ligne['total_evenements']);
        $this->assertEqualsWithDelta(75.0, (float) $ligne['taux'], 0.001);
    }

    public function test_stats_par_annee_sont_cloisonnees(): void
    {
        $reponse = $this->withToken($this->jetonB)->getJson('/api/admin/reports/annee-stats')->assertStatus(200);

        $ligne = collect($reponse->json('data'))->firstWhere('id', $this->annee->id);

        $this->assertSame(0, $ligne['presences_attendues']);
        // Aucune séance : pas de taux, plutôt qu'un 0 %.
        $this->assertNull($ligne['taux']);
    }

    // ─── etudiantsAbsents ─────────────────────────────────────────

    public function test_la_liste_des_absents_classe_les_plus_absents_d_abord(): void
    {
        // Seconde séance terminée, où seul le premier étudiant scanne.
        $seconde = $this->creerSeance('2026-03-12', 'termine');
        $this->creerPresence($this->etudiants[0], $seconde, 'valide');
        // Un scan rejeté n'efface pas l'absence.
        $this->creerPresence($this->etudiants[3], $this->evenement, 'rejete');

        $reponse = $this->withToken($this->jetonA)->getJson('/api/admin/reports/etudiants-absents')
            ->assertStatus(200)
            ->assertJsonPath('data.etudiants_attendus', 4)
            ->assertJsonCount(3, 'data.etudiants');

        $lignes = collect($reponse->json('data.etudiants'));

        // Le quatrième étudiant a manqué les deux séances ; les deuxième et
        // troisième, la seconde ; le premier n'en a manqué aucune.
        $this->assertSame($this->etudiants[3]->id, $lignes[0]['etudiant_id']);
        $this->assertSame(2, $lignes[0]['absences']);
        $this->assertSame(0, $lignes[0]['presents']);
        $this->assertSame(2, $lignes[0]['attendus']);
        $this->assertEqualsWithDelta(0.0, (float) $lignes[0]['taux'], 0.001);
        $this->assertSame('2026-03-12', $lignes[0]['dernier_manque']['date']);
        $this->assertSame($this->ec->intitule, $lignes[0]['dernier_manque']['ec']);
        $this->assertSame([1, 1], $lignes->slice(1)->pluck('absences')->values()->all());
        $this->assertNotContains($this->etudiants[0]->id, $lignes->pluck('etudiant_id')->all());
    }

    public function test_la_liste_des_absents_suit_les_filtres_et_l_etablissement(): void
    {
        $this->withToken($this->jetonA)->getJson('/api/admin/reports/etudiants-absents?semestre=2')
            ->assertStatus(200)
            ->assertJsonPath('data.etudiants', [])
            ->assertJsonPath('data.etudiants_attendus', 0);

        // Le garde garde en mémoire l'utilisateur de la requête précédente.
        $this->app['auth']->forgetGuards();

        $this->withToken($this->jetonB)->getJson('/api/admin/reports/etudiants-absents')
            ->assertStatus(200)
            ->assertJsonPath('data.etudiants', []);

        $this->app['auth']->forgetGuards();

        $this->withToken($this->jetonA)->getJson('/api/admin/reports/etudiants-absents?semestre=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['semestre']);
    }

    public function test_la_liste_des_absents_s_exporte_en_csv(): void
    {
        $reponse = $this->withToken($this->jetonA)
            ->get('/api/admin/reports/etudiants-absents?format=csv&filiere_id=' . $this->filiere->id);

        $reponse->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $disposition = $reponse->headers->get('Content-Disposition');
        $this->assertStringContainsString('filename=etudiants_absents_', $disposition);
        $this->assertStringContainsString('_export-2026-03-15.csv', $disposition);

        $contenu = $reponse->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
        $this->assertStringContainsString('Étudiant,Matricule,Filière,Absences,Présents,Attendus,Taux', $contenu);
        $this->assertStringContainsString($this->etudiants[3]->matricule, $contenu);
        $this->assertStringContainsString('10/03/2026', $contenu);
        // 1 en-tête + le seul étudiant absent.
        $this->assertCount(2, array_filter(explode("\n", trim($contenu))));
    }

    // ─── excelExport ──────────────────────────────────────────────

    public function test_export_csv_contient_les_presences_et_les_bons_entetes(): void
    {
        $reponse = $this->withToken($this->jetonA)->get('/api/admin/reports/excel/export');

        $reponse->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                // Le nom résume les filtres ; sans filtre, il le dit.
                'attachment; filename=presences_complet_export-2026-03-15.csv'
            );

        $contenu = $reponse->streamedContent();

        $this->assertNotSame('', $contenu, 'Le CSV ne doit pas être vide.');
        // BOM UTF-8, indispensable à Excel.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
        // fputcsv entoure de guillemets tout champ contenant un espace : l'entete
        // « Heure Scan » sort donc quotee, une seule fois.
        $this->assertStringContainsString('Étudiant,Matricule,Filière,Cours,Date,"Heure Scan",Statut,IP', $contenu);

        foreach ([0, 1, 2] as $rang) {
            $this->assertStringContainsString($this->etudiants[$rang]->matricule, $contenu);
        }

        $this->assertStringContainsString($this->filiere->code, $contenu);
        $this->assertStringContainsString($this->ec->intitule, $contenu);
        $this->assertStringContainsString(self::DATE_EVENEMENT, $contenu);
        // Le statut sort en toutes lettres, plus en valeur brute.
        $this->assertStringContainsString('Présent', $contenu);
        $this->assertStringNotContainsString(',valide,', $contenu);

        // 1 en-tête + 3 présences.
        $lignes = array_filter(explode("\n", trim($contenu)));
        $this->assertCount(4, $lignes);
    }

    public function test_export_csv_filtre_par_filiere(): void
    {
        $autre = $this->creerJeuDeDonnees($this->etabA, 'CSV', 'L2', 3, 1, 1);

        $reponse = $this->withToken($this->jetonA)
            ->get('/api/admin/reports/excel/export?filiere_id=' . $autre['filiere']->id);

        $reponse->assertStatus(200);

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString($autre['etudiants'][0]->matricule, $contenu);
        $this->assertStringNotContainsString($this->etudiants[0]->matricule, $contenu);
        $this->assertCount(2, array_filter(explode("\n", trim($contenu))));
    }

    public function test_export_csv_filtre_par_plage_de_dates(): void
    {
        $dedans = $this->withToken($this->jetonA)
            ->get('/api/admin/reports/excel/export?date_debut=2026-03-01&date_fin=2026-03-31');

        $dedans->assertStatus(200);
        $this->assertStringContainsString($this->etudiants[0]->matricule, $dedans->streamedContent());

        $dehors = $this->withToken($this->jetonA)
            ->get('/api/admin/reports/excel/export?date_debut=2026-05-01&date_fin=2026-05-31');

        $dehors->assertStatus(200);

        $contenu = $dehors->streamedContent();
        $this->assertStringNotContainsString($this->etudiants[0]->matricule, $contenu);
        $this->assertCount(1, array_filter(explode("\n", trim($contenu))));
    }

    public function test_export_csv_sans_resultat_ne_contient_que_l_entete(): void
    {
        Presence::query()->forceDelete();

        $reponse = $this->withToken($this->jetonA)->get('/api/admin/reports/excel/export');

        $reponse->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('Matricule', $contenu);
        $this->assertCount(1, array_filter(explode("\n", trim($contenu))));
    }
}
