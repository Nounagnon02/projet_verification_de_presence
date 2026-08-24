<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Tests de l'import CSV — CsvImportController.
 *
 * Couvre les trois points d'entrée :
 *   - POST /api/admin/import/csv/courses   (structure UE/EC)
 *   - POST /api/admin/import/csv/schedule  (emploi du temps + conflits)
 *   - GET  /api/admin/import/csv/template/{type}
 *
 * Les fichiers CSV sont fabriqués à la volée. Plusieurs tests constatent un
 * comportement défectueux du contrôleur : ils l'assertent tel quel et le
 * signalent en commentaire, pour que toute correction future fasse échouer le
 * test au lieu de passer inaperçue.
 */
class CsvImportControllerTest extends TestCase
{
    /** En-tête canonique attendu par l'import UE/EC. */
    private const ENTETE_UE_EC = 'code_ue,intitule_ue,filiere_code,niveau,annee_libelle,semestre,volume_horaire_ue,code_ec,intitule_ec,volume_horaire_ec';

    /** En-tête canonique attendu par l'import emploi du temps. */
    private const ENTETE_EDT = 'filiere_code,niveau,annee_libelle,semestre,ue_code,ec_code,jour,heure_debut,heure_fin,salle_code,type_cours';

    private string $sfx;
    private string $jeton;
    private int $etabA;
    private int $etabB;
    private Filiere $filiereA;
    private Filiere $filiereB;
    private AnneeAcademique $annee;
    private Salle $salle;
    private Ue $ue;
    private Ec $ec1;
    private Ec $ec2;
    private string $codeUeDejaPris;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::random(6);

        $this->etabA = DB::table('etablissements')->insertGetId([
            'code'       => 'CA' . $this->sfx,
            'nom'        => 'Faculté A ' . $this->sfx,
            'email'      => strtolower('csv-a-' . $this->sfx . '@test.local'),
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->etabB = DB::table('etablissements')->insertGetId([
            'code'       => 'CB' . $this->sfx,
            'nom'        => 'Faculté B ' . $this->sfx,
            'email'      => strtolower('csv-b-' . $this->sfx . '@test.local'),
            'actif'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->filiereA = Filiere::create([
            'code' => 'FA' . $this->sfx, 'intitule' => 'Filière A', 'niveau' => 'M1',
            'etablissement_id' => $this->etabA,
        ]);

        $this->filiereB = Filiere::create([
            'code' => 'FB' . $this->sfx, 'intitule' => 'Filière B', 'niveau' => 'M1',
            'etablissement_id' => $this->etabB,
        ]);

        // Année dédiée au test (libellé unique) : le contrôleur résout l'année par
        // son libellé, sans exiger qu'elle soit active.
        $this->annee = AnneeAcademique::create([
            'libelle'    => 'CSV-' . $this->sfx,
            'date_debut' => '2030-10-01',
            'date_fin'   => '2031-09-30',
            'active'     => false,
        ]);

        // Super admin : aucun cloisonnement, sauf tests dédiés.
        $this->jeton = User::factory()
            ->create(['email' => 'csv-admin-' . $this->sfx . '@test.local'])
            ->createToken('test')->plainTextToken;

        $this->salle = Salle::create([
            'nom' => 'Salle ' . $this->sfx, 'code' => 'SA' . $this->sfx,
            'actif' => true, 'etablissement_id' => $this->etabA,
        ]);

        // Structure déjà présente en base, utilisée par les imports d'emploi du temps.
        $this->ue = Ue::create([
            'code' => 'EDTUE' . $this->sfx, 'intitule' => 'UE emploi du temps',
            'filiere_id' => $this->filiereA->id, 'annee_id' => $this->annee->id,
            'semestre' => 1, 'volume_horaire' => 60,
        ]);
        $this->ec1 = Ec::create([
            'ue_id' => $this->ue->id, 'code' => 'EDTEC1' . $this->sfx,
            'intitule' => 'EC un', 'volume_horaire' => 30,
        ]);
        $this->ec2 = Ec::create([
            'ue_id' => $this->ue->id, 'code' => 'EDTEC2' . $this->sfx,
            'intitule' => 'EC deux', 'volume_horaire' => 30,
        ]);

        // `ues.code` est unique au niveau de la table : ce code, déjà pris par une
        // autre filière, provoque une violation d'unicité s'il est réimporté.
        $this->codeUeDejaPris = 'DUP' . $this->sfx;
        Ue::create([
            'code' => $this->codeUeDejaPris, 'intitule' => 'UE autre filière',
            'filiere_id' => $this->filiereB->id, 'annee_id' => $this->annee->id,
            'semestre' => 1, 'volume_horaire' => 30,
        ]);
    }

    // ── OUTILS ────────────────────────────────────────────────────

    /**
     * Fabrique un fichier téléversé porteur du contenu voulu.
     */
    private function fichier(string $contenu, string $nom = 'import.csv', ?string $mime = null): UploadedFile
    {
        $fichier = UploadedFile::fake()->create($nom, 1, $mime);
        file_put_contents($fichier->getPathname(), $contenu);

        return $fichier;
    }

    private function importerUeEc(string $contenu, ?string $jeton = null, string $nom = 'ue-ec.csv', ?string $mime = null): TestResponse
    {
        return $this->withToken($jeton ?? $this->jeton)
            ->postJson('/api/admin/import/csv/courses', ['file' => $this->fichier($contenu, $nom, $mime)]);
    }

    private function importerEdt(string $contenu, ?string $jeton = null): TestResponse
    {
        return $this->withToken($jeton ?? $this->jeton)
            ->postJson('/api/admin/import/csv/schedule', ['file' => $this->fichier($contenu, 'edt.csv')]);
    }

    /**
     * Une ligne d'emploi du temps, filière/année/UE du jeu de données par défaut.
     */
    private function ligneEdt(string $codeEc, string $jour, string $debut, string $fin, string $salle = '', string $type = 'CM'): string
    {
        return implode(',', [
            $this->filiereA->code, 'M1', $this->annee->libelle, '1',
            $this->ue->code, $codeEc, $jour, $debut, $fin, $salle, $type,
        ]);
    }

    private function jetonEtudiant(): string
    {
        $etudiant = Etudiant::create([
            'nom'                => 'CSVIMPORT',
            'prenom'             => 'TEST',
            'matricule'          => 'CSV-' . $this->sfx,
            'filiere_id'         => $this->filiereA->id,
            'annee_id'           => $this->annee->id,
            'email'              => strtolower('csv-etu-' . $this->sfx . '@test.local'),
            'identifiant_unique' => 'CSV_' . $this->sfx,
        ]);

        return $etudiant->createToken('mobile-app', ['etudiant'])->plainTextToken;
    }

    // ── IMPORT UE/EC ──────────────────────────────────────────────

    public function test_import_ue_ec_cree_les_ue_et_ec_en_base(): void
    {
        $codeUe = 'NEWUE' . $this->sfx;

        $reponse = $this->importerUeEc(
            self::ENTETE_UE_EC . "\n"
            . "{$codeUe},Informatique Fondamentale,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,NEWEC1{$this->sfx},Algorithmique,30\n"
            . "{$codeUe},Informatique Fondamentale,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,NEWEC2{$this->sfx},Programmation,30\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.errors', []);

        $this->assertDatabaseHas('ues', [
            'code'           => $codeUe,
            'intitule'       => 'Informatique Fondamentale',
            'filiere_id'     => $this->filiereA->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 60,
        ]);

        $ue = Ue::where('code', $codeUe)->firstOrFail();

        $this->assertDatabaseHas('ecs', [
            'ue_id' => $ue->id, 'code' => 'NEWEC1' . $this->sfx,
            'intitule' => 'Algorithmique', 'volume_horaire' => 30,
        ]);
        $this->assertDatabaseHas('ecs', [
            'ue_id' => $ue->id, 'code' => 'NEWEC2' . $this->sfx,
            'intitule' => 'Programmation', 'volume_horaire' => 30,
        ]);
        $this->assertSame(2, Ec::where('ue_id', $ue->id)->count());
    }

    public function test_import_ue_ec_ne_duplique_pas_les_lignes_repetees(): void
    {
        $codeUe = 'NEWUE' . $this->sfx;
        $codeEc = 'NEWEC1' . $this->sfx;
        $ligne = "{$codeUe},Informatique,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,{$codeEc},Algorithmique,30";

        $reponse = $this->importerUeEc(self::ENTETE_UE_EC . "\n" . $ligne . "\n" . $ligne . "\n" . $ligne . "\n");

        // Les trois lignes sont regroupées sur la même UE : un seul succès.
        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.errors', []);

        $this->assertSame(1, Ue::where('code', $codeUe)->count());
        $this->assertSame(1, Ec::where('code', $codeEc)->count());
    }

    public function test_import_ue_ec_avec_en_tetes_invalides_nrecrit_rien(): void
    {
        $uesAvant = Ue::count();
        $ecsAvant = Ec::count();

        $reponse = $this->importerUeEc("colonne_a,colonne_b\nvaleur_a,valeur_b\n");

        // DÉFAUT (CsvImportController.php:93) : aucune validation des en-têtes.
        // La construction de la clé de regroupement lit $row['code_ue'] sans
        // garde ; sur un en-tête inconnu, la clé est absente, l'avertissement PHP
        // devient une ErrorException, et l'utilisateur reçoit un 500 opaque au
        // lieu d'un 422 explicite. Rien n'est écrit, au moins.
        $reponse->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringContainsString('Erreur lors de l\'import', $reponse->json('message'));

        $this->assertSame($uesAvant, Ue::count());
        $this->assertSame($ecsAvant, Ec::count());
    }

    public function test_import_ue_ec_avec_alias_dentete_espace_echoue(): void
    {
        $uesAvant = Ue::count();

        // Ces libellés sont pourtant déclarés dans HEADER_ALIASES.
        $reponse = $this->importerUeEc(
            "Code UE,Intitule UE,Filiere,Annee,Semestre,VH UE,Code EC,Intitule EC,VH EC\n"
            . "ALIAS{$this->sfx},Informatique,{$this->filiereA->code},{$this->annee->libelle},1,60,ALIASEC{$this->sfx},Algorithmique,30\n"
        );

        // DÉFAUT (CsvImportController.php:39 et 53) : la clé 'code ue' est définie
        // deux fois dans HEADER_ALIASES ; la seconde (=> 'ue_code') écrase la
        // première (=> 'code_ue'). Idem pour 'code ec' (=> 'ec_code'). L'import
        // UE/EC ne retrouve donc jamais ses colonnes et casse en 500.
        $reponse->assertStatus(500)->assertJsonPath('success', false);

        $this->assertSame($uesAvant, Ue::count());
        $this->assertDatabaseMissing('ues', ['code' => 'ALIAS' . $this->sfx]);
    }

    public function test_import_ue_ec_signale_une_colonne_requise_absente(): void
    {
        $uesAvant = Ue::count();

        // Colonne annee_libelle absente de l'en-tête.
        $reponse = $this->importerUeEc(
            "code_ue,intitule_ue,filiere_code,niveau,semestre,volume_horaire_ue,code_ec,intitule_ec,volume_horaire_ec\n"
            . "SANSAN{$this->sfx},Informatique,{$this->filiereA->code},M1,1,60,SANSEC{$this->sfx},Algorithmique,30\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.errors.0.line', 2)
            ->assertJsonPath('data.errors.0.error', 'annee_libelle manquant.');

        $this->assertSame($uesAvant, Ue::count());
        $this->assertDatabaseMissing('ues', ['code' => 'SANSAN' . $this->sfx]);
    }

    public function test_import_ue_ec_refuse_un_fichier_vide(): void
    {
        $reponse = $this->importerUeEc('');

        $reponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Le fichier CSV est vide ou invalide.');
    }

    public function test_import_ue_ec_refuse_un_fichier_sans_ligne_de_donnees(): void
    {
        // En-tête seul : rien à importer.
        $reponse = $this->importerUeEc(self::ENTETE_UE_EC . "\n");

        $reponse->assertStatus(422)
            ->assertJsonPath('message', 'Le fichier CSV est vide ou invalide.');
    }

    public function test_import_ue_ec_refuse_un_fichier_qui_nest_pas_un_csv(): void
    {
        $reponse = $this->importerUeEc(
            '%PDF-1.4 contenu binaire',
            nom: 'structure.pdf',
            mime: 'application/pdf',
        );

        $reponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_import_ue_ec_exige_un_fichier(): void
    {
        $reponse = $this->withToken($this->jeton)
            ->postJson('/api/admin/import/csv/courses', []);

        $reponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_import_ue_ec_conserve_les_lignes_valides_malgre_une_derniere_ligne_invalide(): void
    {
        $reponse = $this->importerUeEc(
            self::ENTETE_UE_EC . "\n"
            . "T1{$this->sfx},Un,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,T1EC{$this->sfx},EC un,30\n"
            . "T2{$this->sfx},Deux,{$this->filiereA->code},M1,{$this->annee->libelle},1,40,T2EC{$this->sfx},EC deux,20\n"
            . "T3{$this->sfx},Trois,{$this->filiereA->code},M1,{$this->annee->libelle},1,0,T3EC{$this->sfx},EC trois,10\n"
        );

        // COMPORTEMENT RÉEL : l'import n'est pas atomique malgré le
        // DB::beginTransaction (CsvImportController.php:87). Une ligne rejetée
        // par les contrôles métier est simplement ignorée (`continue`) et le
        // commit final conserve les lignes précédentes.
        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 2)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.errors.0.line', 4);

        $this->assertStringContainsString(
            'volume_horaire_ue invalide',
            $reponse->json('data.errors.0.error')
        );

        $this->assertDatabaseHas('ues', ['code' => 'T1' . $this->sfx]);
        $this->assertDatabaseHas('ues', ['code' => 'T2' . $this->sfx]);
        $this->assertDatabaseMissing('ues', ['code' => 'T3' . $this->sfx]);
    }

    public function test_import_ue_ec_annule_tout_sur_erreur_sql(): void
    {
        $reponse = $this->importerUeEc(
            self::ENTETE_UE_EC . "\n"
            . "OK{$this->sfx},Valide,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,OKEC{$this->sfx},EC valide,30\n"
            . "{$this->codeUeDejaPris},Doublon global,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,KOEC{$this->sfx},EC refusé,30\n"
        );

        // `ues.code` est unique sur toute la table alors que le contrôleur cherche
        // l'UE existante sur (code, filiere_id, annee_id) : un code déjà pris par
        // une autre filière déclenche une violation d'unicité. Là, le rollback
        // s'applique — la première UE, valide, est perdue elle aussi, et le client
        // reçoit un 500 sans indication de ligne.
        $reponse->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringContainsString('Erreur lors de l\'import', $reponse->json('message'));

        $this->assertDatabaseMissing('ues', ['code' => 'OK' . $this->sfx]);
        $this->assertDatabaseMissing('ecs', ['code' => 'OKEC' . $this->sfx]);
    }

    public function test_import_ue_ec_signale_une_filiere_introuvable(): void
    {
        $reponse = $this->importerUeEc(
            self::ENTETE_UE_EC . "\n"
            . "INC{$this->sfx},Informatique,FILIERE-INEXISTANTE,M1,{$this->annee->libelle},1,60,INCEC{$this->sfx},EC,30\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.errors.0.error', "Filière 'FILIERE-INEXISTANTE' introuvable.");

        $this->assertDatabaseMissing('ues', ['code' => 'INC' . $this->sfx]);
    }

    // ── IMPORT EMPLOI DU TEMPS ────────────────────────────────────

    public function test_import_edt_cree_les_creneaux(): void
    {
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '08:00', '10:00', $this->salle->code, 'CM') . "\n"
            . $this->ligneEdt($this->ec2->code, 'Mardi', '10:00', '12:00', $this->salle->code, 'TD') . "\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.success', 2)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.warnings', []);

        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id'         => $this->ec1->id,
            'filiere_id'    => $this->filiereA->id,
            'annee_id'      => $this->annee->id,
            'jour_semaine'  => 1,
            'heure_debut'   => '08:00',
            'heure_fin'     => '10:00',
            'salle_id'      => $this->salle->id,
            'salle_libelle' => $this->salle->nom,
            'type_cours'    => 'CM',
        ]);

        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id'        => $this->ec2->id,
            'jour_semaine' => 2,
            'heure_debut'  => '10:00',
            'type_cours'   => 'TD',
        ]);
    }

    public function test_import_edt_signale_un_conflit_de_salle(): void
    {
        EmploiDuTemps::create([
            'ec_id' => $this->ec2->id, 'filiere_id' => $this->filiereA->id,
            'annee_id' => $this->annee->id, 'jour_semaine' => 1,
            'heure_debut' => '08:00', 'heure_fin' => '10:00',
            'salle_id' => $this->salle->id, 'salle_libelle' => $this->salle->nom,
            'type_cours' => 'CM',
        ]);

        // 09:00-11:00 chevauche 08:00-10:00 dans la même salle.
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '09:00', '11:00', $this->salle->code, 'TD') . "\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.errors.0.line', 2);

        $this->assertStringContainsString('Conflit de salle', $reponse->json('data.errors.0.error'));
        $this->assertStringContainsString('Lundi', $reponse->json('data.errors.0.error'));

        // Le créneau en conflit n'est pas créé.
        $this->assertSame(0, EmploiDuTemps::where('ec_id', $this->ec1->id)->count());
        $this->assertSame(1, EmploiDuTemps::where('salle_id', $this->salle->id)->count());
    }

    public function test_import_edt_signale_le_chevauchement_dun_meme_ec(): void
    {
        // Deux lignes du même fichier, même EC, horaires qui se chevauchent.
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '08:00', '10:00') . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '09:00', '11:00') . "\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.errors.0.line', 3);

        $this->assertStringContainsString('Conflit EC', $reponse->json('data.errors.0.error'));
        $this->assertSame(1, EmploiDuTemps::where('ec_id', $this->ec1->id)->count());
    }

    public function test_import_edt_refuse_a_tort_deux_creneaux_consecutifs(): void
    {
        EmploiDuTemps::create([
            'ec_id' => $this->ec2->id, 'filiere_id' => $this->filiereA->id,
            'annee_id' => $this->annee->id, 'jour_semaine' => 1,
            'heure_debut' => '08:00', 'heure_fin' => '10:00',
            'salle_id' => $this->salle->id, 'salle_libelle' => $this->salle->nom,
            'type_cours' => 'CM',
        ]);

        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '10:00', '12:00', $this->salle->code, 'TD') . "\n"
        );

        // DÉFAUT (CsvImportController.php:383-389) : les bornes du whereBetween
        // sont inclusives, donc un créneau qui commence à l'heure exacte où le
        // précédent finit est vu comme un chevauchement. RoomConflictTest établit
        // pourtant l'inverse pour les événements (10:00-12:00 après 08:00-10:00
        // est autorisé) : les deux modules se contredisent.
        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0);

        $this->assertStringContainsString('Conflit de salle', $reponse->json('data.errors.0.error'));
        $this->assertSame(0, EmploiDuTemps::where('ec_id', $this->ec1->id)->count());
    }

    public function test_import_edt_avertit_quand_la_salle_est_introuvable(): void
    {
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Jeudi', '14:00', '16:00', 'SALLE-INEXISTANTE') . "\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.warnings.0.line', 2);

        $this->assertStringContainsString('introuvable', $reponse->json('data.warnings.0.warning'));

        // Le créneau est tout de même créé, avec le libellé libre.
        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id'         => $this->ec1->id,
            'jour_semaine'  => 4,
            'salle_id'      => null,
            'salle_libelle' => 'SALLE-INEXISTANTE',
        ]);
    }

    public function test_import_edt_refuse_un_jour_et_des_heures_invalides(): void
    {
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Funday', '08:00', '10:00') . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '8h', '10h') . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '14:00', '12:00') . "\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.total', 3);

        $erreurs = $reponse->json('data.errors');
        $this->assertCount(3, $erreurs);
        $this->assertStringContainsString('Jour invalide', $erreurs[0]['error']);
        $this->assertStringContainsString("Format d'heure invalide", $erreurs[1]['error']);
        $this->assertStringContainsString('doit être après', $erreurs[2]['error']);

        $this->assertSame(0, EmploiDuTemps::where('ec_id', $this->ec1->id)->count());
    }

    public function test_import_edt_signale_une_ue_ou_un_ec_introuvable(): void
    {
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . implode(',', [
                $this->filiereA->code, 'M1', $this->annee->libelle, '1',
                'UE-INEXISTANTE', $this->ec1->code, 'Lundi', '08:00', '10:00', '', 'CM',
            ]) . "\n"
            . $this->ligneEdt('EC-INEXISTANT', 'Mardi', '08:00', '10:00') . "\n"
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.errors.0.error', "UE 'UE-INEXISTANTE' introuvable pour la filière/année.")
            ->assertJsonPath(
                'data.errors.1.error',
                "EC 'EC-INEXISTANT' introuvable dans l'UE '{$this->ue->code}'."
            );
    }

    public function test_import_edt_refuse_un_fichier_vide(): void
    {
        $reponse = $this->importerEdt('');

        $reponse->assertStatus(422)
            ->assertJsonPath('message', 'Le fichier CSV est vide ou invalide.');
    }

    // ── MODÈLES CSV ───────────────────────────────────────────────

    public function test_modele_ue_ec_est_un_csv_non_vide_avec_les_bons_en_tetes(): void
    {
        $reponse = $this->withToken($this->jeton)->get('/api/admin/import/csv/template/ue-ec');

        $reponse->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="template_ue-ec.csv"');

        $contenu = $reponse->streamedContent();

        $this->assertNotSame('', trim($contenu));
        $this->assertStringContainsString(
            'code_ue,intitule_ue,filiere_code,niveau,annee_libelle,semestre,volume_horaire_ue,code_ec,intitule_ec,volume_horaire_ec',
            $contenu
        );
        // Le modèle est accompagné de lignes d'exemple exploitables.
        $this->assertStringContainsString('UE-INFO-1', $contenu);
        $this->assertGreaterThanOrEqual(4, count(array_filter(explode("\n", trim($contenu)))));
    }

    public function test_modele_edt_est_un_csv_non_vide_avec_les_bons_en_tetes(): void
    {
        $reponse = $this->withToken($this->jeton)->get('/api/admin/import/csv/template/edt');

        $reponse->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename="template_edt.csv"');

        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString(
            'filiere_code,niveau,annee_libelle,semestre,ue_code,ec_code,jour,heure_debut,heure_fin,salle_code,type_cours',
            $contenu
        );
        $this->assertStringContainsString('Lundi,08:00,10:00', $contenu);
    }

    public function test_modele_de_type_inconnu_est_refuse(): void
    {
        $reponse = $this->withToken($this->jeton)->get('/api/admin/import/csv/template/inconnu');

        // DÉFAUT (CsvImportController.php:462-482) : le type n'est validé qu'à
        // l'intérieur du callback de streaming, donc après l'envoi des en-têtes.
        // Le client reçoit un 200 annonçant un CSV en pièce jointe, et l'abort(404)
        // ne survient qu'au moment où le corps est produit.
        $reponse->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename="template_inconnu.csv"');

        $niveauTampon = ob_get_level();

        try {
            $reponse->streamedContent();
            $this->fail('Un type de modèle inconnu devrait être refusé.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertStringContainsString('Template introuvable', $e->getMessage());
        } finally {
            // streamedContent() ouvre un tampon de sortie qu'il ne referme pas
            // lorsque le callback lève une exception.
            while (ob_get_level() > $niveauTampon) {
                ob_end_clean();
            }
        }
    }

    // ── SÉCURITÉ ET CLOISONNEMENT ─────────────────────────────────

    public function test_les_imports_exigent_un_jeton(): void
    {
        $this->postJson('/api/admin/import/csv/courses', [
            'file' => $this->fichier(self::ENTETE_UE_EC . "\n"),
        ])->assertStatus(401);

        $this->postJson('/api/admin/import/csv/schedule', [
            'file' => $this->fichier(self::ENTETE_EDT . "\n"),
        ])->assertStatus(401);

        $this->getJson('/api/admin/import/csv/template/ue-ec')->assertStatus(401);
    }

    public function test_un_jeton_etudiant_est_refuse_sur_les_imports(): void
    {
        $jeton = $this->jetonEtudiant();

        $this->withToken($jeton)
            ->postJson('/api/admin/import/csv/courses', [
                'file' => $this->fichier(self::ENTETE_UE_EC . "\n"),
            ])->assertStatus(403);

        $this->withToken($jeton)
            ->postJson('/api/admin/import/csv/schedule', [
                'file' => $this->fichier(self::ENTETE_EDT . "\n"),
            ])->assertStatus(403);

        $this->withToken($jeton)
            ->getJson('/api/admin/import/csv/template/ue-ec')
            ->assertStatus(403);
    }

    public function test_un_admin_de_faculte_importe_pour_sa_propre_filiere(): void
    {
        $jeton = User::factory()->faculteAdmin($this->etabA)
            ->create(['email' => 'csv-fa-' . $this->sfx . '@test.local'])
            ->createToken('test')->plainTextToken;

        $reponse = $this->importerUeEc(
            self::ENTETE_UE_EC . "\n"
            . "MINE{$this->sfx},Informatique,{$this->filiereA->code},M1,{$this->annee->libelle},1,60,MINEEC{$this->sfx},EC,30\n",
            $jeton
        );

        $reponse->assertStatus(200)->assertJsonPath('data.success', 1);
        $this->assertDatabaseHas('ues', [
            'code' => 'MINE' . $this->sfx, 'filiere_id' => $this->filiereA->id,
        ]);
    }

    public function test_un_admin_de_faculte_ne_peut_pas_importer_pour_une_autre_faculte(): void
    {
        $jeton = User::factory()->faculteAdmin($this->etabA)
            ->create(['email' => 'csv-fb-' . $this->sfx . '@test.local'])
            ->createToken('test')->plainTextToken;

        $reponse = $this->importerUeEc(
            self::ENTETE_UE_EC . "\n"
            . "OTHER{$this->sfx},Informatique,{$this->filiereB->code},M1,{$this->annee->libelle},1,60,OTHEREC{$this->sfx},EC,30\n",
            $jeton
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath(
                'data.errors.0.error',
                "Filière '{$this->filiereB->code}' non autorisée pour votre établissement."
            );

        $this->assertDatabaseMissing('ues', ['code' => 'OTHER' . $this->sfx]);
    }

    public function test_un_admin_de_faculte_ne_peut_pas_planifier_pour_une_autre_faculte(): void
    {
        $jeton = User::factory()->faculteAdmin($this->etabB)
            ->create(['email' => 'csv-edt-' . $this->sfx . '@test.local'])
            ->createToken('test')->plainTextToken;

        // La filière visée appartient à l'établissement A.
        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '08:00', '10:00', $this->salle->code) . "\n",
            $jeton
        );

        $reponse->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.errors.0.error', "Filière '{$this->filiereA->code}' non autorisée.");

        $this->assertSame(0, EmploiDuTemps::where('ec_id', $this->ec1->id)->count());
    }

    public function test_une_salle_dune_autre_faculte_est_acceptee(): void
    {
        $salleAutreFaculte = Salle::create([
            'nom' => 'Salle B ' . $this->sfx, 'code' => 'SB' . $this->sfx,
            'actif' => true, 'etablissement_id' => $this->etabB,
        ]);

        $jeton = User::factory()->faculteAdmin($this->etabA)
            ->create(['email' => 'csv-salle-' . $this->sfx . '@test.local'])
            ->createToken('test')->plainTextToken;

        $reponse = $this->importerEdt(
            self::ENTETE_EDT . "\n"
            . $this->ligneEdt($this->ec1->code, 'Lundi', '08:00', '10:00', $salleAutreFaculte->code) . "\n",
            $jeton
        );

        // DÉFAUT (CsvImportController.php:366) : la salle est résolue sans filtre
        // d'établissement. Un admin de faculté peut donc rattacher ses créneaux à
        // une salle qui ne lui appartient pas, alors que la filière, elle, est bien
        // cloisonnée.
        $reponse->assertStatus(200)->assertJsonPath('data.success', 1);
        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id'    => $this->ec1->id,
            'salle_id' => $salleAutreFaculte->id,
        ]);
    }
}
