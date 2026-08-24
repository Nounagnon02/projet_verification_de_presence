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
 * Couvre les 7 endpoints :
 *   - GET /admin/reports/presence/{evenementId}/pdf   exportPdf
 *   - GET /admin/reports/department/{filiere}          departmentReport
 *   - GET /admin/reports/semester/{anneeAcademique}    semesterReport
 *   - GET /admin/reports/semester-comparison           semesterComparison
 *   - GET /admin/reports/filiere-stats                 filiereStats
 *   - GET /admin/reports/filtered                      filteredStats
 *   - GET /admin/reports/excel/export                  excelExport
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
     * Liste des 7 URL du contrôleur, pour les tests transverses.
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

        // DÉFAUT CONSTATÉ — ReportController.php:168 : total_etudiants est
        // compté sur toute la base (Etudiant::where('annee_id', ...)), sans
        // filtre d'établissement. L'admin de l'établissement B lit donc
        // l'effectif de l'établissement A. Assertion à inverser (0 attendu)
        // dès que le comptage sera cloisonné.
        $this->assertSame(4, $reponse->json('data.total_etudiants'));
    }

    public function test_le_rapport_par_filiere_n_est_pas_cloisonne(): void
    {
        // DÉFAUT CONSTATÉ — ReportController.php:47 : departmentReport reçoit
        // la filière par route model binding sans appeler
        // authorizeEtablissement(). Un admin de l'établissement B obtient
        // l'intégralité du rapport d'une filière de l'établissement A.
        // Comportement attendu après correction : 404.
        $reponse = $this->withToken($this->jetonB)
            ->getJson('/api/admin/reports/department/' . $this->filiere->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('data.filiere.code', $this->filiere->code)
            ->assertJsonPath('data.total_etudiants', 4);
    }

    public function test_l_export_pdf_n_est_pas_cloisonne(): void
    {
        // DÉFAUT CONSTATÉ — ReportController.php:30 : exportPdf fait un
        // Evenement::findOrFail() sans contrôle d'établissement. Un admin de
        // l'établissement B télécharge la feuille de présence d'un événement
        // de l'établissement A. Comportement attendu après correction : 404.
        $reponse = $this->withToken($this->jetonB)
            ->get('/api/admin/reports/presence/' . $this->evenement->id . '/pdf');

        $reponse->assertStatus(200)->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_la_comparaison_semestrielle_n_est_pas_cloisonnee(): void
    {
        // DÉFAUT CONSTATÉ — ReportController.php:229 : semesterComparison
        // fait un Filiere::findOrFail($filiereId) sur un identifiant fourni
        // par le client, sans contrôle d'établissement.
        // Comportement attendu après correction : 404.
        $reponse = $this->withToken($this->jetonB)->getJson(
            '/api/admin/reports/semester-comparison?annee_id=' . $this->annee->id
            . '&filiere_id=' . $this->filiere->id
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.filiere.code', $this->filiere->code);

        $this->assertSame(75.0, $this->nombre($reponse, 'data.semestres.0.taux'));
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
            ->assertJsonCount(1, 'data.evolution')
            ->assertJsonPath('data.evolution.0.date', self::DATE_EVENEMENT)
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

        $this->assertSame(3, (int) $reponse->json('data.evolution.0.total'));
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

        // DÉFAUT CONSTATÉ — ReportController.php:454-500 et 404-451 :
        // date_debut / date_fin ne sont appliqués ni au bloc stats_par_ue ni au
        // bloc evolution. Les graphiques restent donc peuplés alors que les
        // totaux sont à zéro. Après correction, ces deux tableaux doivent être
        // vides.
        $dehors->assertJsonCount(1, 'data.stats_par_ue')
            ->assertJsonPath('data.stats_par_ue.0.total_presences', 3);
        $this->assertCount(1, $dehors->json('data.evolution'));
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

        // DÉFAUT CONSTATÉ — ReportController.php:516-518 : taux_global compte
        // toutes les présences, y compris les scans suspects, alors que
        // AttendanceRateService (utilisé par les autres rapports) ne retient
        // que le statut « valide ». Le même jeu de données affiche donc 100 %
        // ici et 75 % dans /reports/department. Valeur attendue après
        // harmonisation : 75.
        $this->assertSame(100.0, $this->nombre($reponse, 'data.taux_global'));
    }

    public function test_rapport_filtre_accepte_une_fenetre_d_evolution_personnalisee(): void
    {
        // La présence date du 10/03, « maintenant » est le 15/03 : une fenêtre
        // d'un jour l'exclut, une fenêtre de 30 jours l'inclut.
        $courte = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered?jours=1');

        $courte->assertStatus(200)
            ->assertJsonPath('data.evolution', [])
            ->assertJsonPath('data.total_presences', 3);

        $longue = $this->withToken($this->jetonA)->getJson('/api/admin/reports/filtered?jours=30');

        $longue->assertStatus(200)->assertJsonCount(1, 'data.evolution');
    }

    public function test_rapport_filtre_ne_casse_pas_sur_des_parametres_non_numeriques(): void
    {
        // DÉFAUT CONSTATÉ — ReportController.php:314 : filteredStats ne valide
        // aucun paramètre. Une valeur non numérique est silencieusement
        // convertie en 0 par Request::integer() au lieu de produire un 422.
        // Le test vérifie au moins l'absence de 500.
        $reponse = $this->withToken($this->jetonA)->getJson(
            '/api/admin/reports/filtered?filiere_id=abc&semestre=xyz&trimestre=99&ue_id=%20'
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.total_etudiants', 0)
            ->assertJsonPath('data.stats_par_ue', [])
            ->assertJsonPath('data.evolution', []);

        $this->assertSame(0.0, $this->nombre($reponse, 'data.taux_global'));
    }

    // ─── excelExport ──────────────────────────────────────────────

    public function test_export_csv_contient_les_presences_et_les_bons_entetes(): void
    {
        $reponse = $this->withToken($this->jetonA)->get('/api/admin/reports/excel/export');

        $reponse->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=export_presences_' . self::HORODATAGE . '.csv'
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
        $this->assertStringContainsString('valide', $contenu);

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
