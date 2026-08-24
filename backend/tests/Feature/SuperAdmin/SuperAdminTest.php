<?php

namespace Tests\Feature\SuperAdmin;

use App\Mail\WelcomeFaculteAdmin;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Super administration UAC (/api/super-admin) — première couverture.
 *
 * Trois contrôleurs sont concernés : le tableau de bord global, le CRUD des
 * facultés (avec création automatique de l'administrateur et envoi de ses
 * identifiants) et l'import CSV en masse.
 *
 * Les assertions portent sur le contenu des réponses et sur l'état de la base,
 * jamais sur le seul code HTTP. Les compteurs globaux sont comparés à un
 * relevé pris avant le scénario : la base de test n'est pas forcément vide.
 *
 * Les tests marqués « DÉFAUT constaté » figent le comportement réel du code
 * aujourd'hui, qui n'est pas le comportement souhaitable ; ils sont détaillés
 * dans le rapport de mission et devront être mis à jour à la correction.
 */
class SuperAdminTest extends TestCase
{
    private string $sfx;
    private string $jetonSuperAdmin;
    private string $jetonFaculteAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::random(6);

        $this->jetonSuperAdmin = User::factory()->create([
            'email' => "sa-{$this->sfx}@example.test",
            'role'  => 'super_admin',
        ])->createToken('t')->plainTextToken;

        // Administrateur d'une faculté tierce : il ne doit franchir aucune
        // route /super-admin.
        $faculteTierce = $this->creerEtablissement('TIERS');
        $this->jetonFaculteAdmin = User::factory()
            ->faculteAdmin($faculteTierce->id)
            ->create(['email' => "fa-{$this->sfx}@example.test"])
            ->createToken('t')->plainTextToken;
    }

    // ── Fabriques ────────────────────────────────────────────────

    private function creerEtablissement(string $prefixe): Etablissement
    {
        $cle = Str::random(6);

        return Etablissement::create([
            'code'  => strtoupper(substr($prefixe, 0, 6)) . $cle,
            'nom'   => "Faculté {$prefixe} {$cle}",
            'email' => strtolower("fac-{$cle}@example.test"),
        ]);
    }

    private function creerAdminFaculte(Etablissement $etablissement): User
    {
        return User::factory()->faculteAdmin($etablissement->id)->create([
            'email' => 'admin-' . Str::random(8) . '@example.test',
        ]);
    }

    private function creerFiliere(Etablissement $etablissement): Filiere
    {
        return Filiere::create([
            'code'             => 'FIL' . Str::random(6),
            'intitule'         => 'Filière de test',
            'niveau'           => 'L1',
            'etablissement_id' => $etablissement->id,
        ]);
    }

    private function creerEtudiant(Filiere $filiere): Etudiant
    {
        $cle = Str::random(8);

        return Etudiant::create([
            'nom'                => 'SUPERADMIN',
            'prenom'             => 'Test',
            'matricule'          => 'SA-' . $cle,
            'filiere_id'         => $filiere->id,
            'annee_id'           => $this->anneeActive()->id,
            'email'              => strtolower("etu-{$cle}@example.test"),
            'identifiant_unique' => 'SA_' . $cle,
        ]);
    }

    private function creerEvenement(Filiere $filiere, string $date): Evenement
    {
        $cle   = Str::random(6);
        $annee = $this->anneeActive();

        $ue = Ue::create([
            'code'           => 'UE' . $cle,
            'intitule'       => 'UE de test',
            'filiere_id'     => $filiere->id,
            'annee_id'       => $annee->id,
            'semestre'       => 1,
            'volume_horaire' => 20,
        ]);

        $ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC' . $cle,
            'intitule'       => 'EC de test',
            'volume_horaire' => 20,
        ]);

        return Evenement::create([
            'ec_id'       => $ec->id,
            'filiere_id'  => $filiere->id,
            'annee_id'    => $annee->id,
            'date'        => $date,
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'Amphi',
            'statut'      => 'termine',
        ]);
    }

    private function creerPresence(Etudiant $etudiant, Evenement $evenement): Presence
    {
        return Presence::create([
            'etudiant_id'        => $etudiant->id,
            'evenement_id'       => $evenement->id,
            'heure_scan'         => Carbon::parse($evenement->date->format('Y-m-d') . ' 08:05:00'),
            'device_fingerprint' => 'empreinte-' . Str::random(8),
            'statut'             => 'valide',
        ]);
    }

    /**
     * Fichier CSV téléversable — même procédé que les tests d'import admin :
     * la taille déclarée sert seulement à créer le fichier, dont le contenu
     * est ensuite réécrit.
     */
    private function fichierCsv(string $contenu): UploadedFile
    {
        $fichier = UploadedFile::fake()->create('facultes.csv', strlen($contenu));
        file_put_contents($fichier->getPathname(), $contenu);

        return $fichier;
    }

    // ── Tableau de bord global ───────────────────────────────────

    public function test_le_tableau_de_bord_agrege_les_donnees_de_toutes_les_facultes(): void
    {
        // Relevé de référence : la base de test peut déjà contenir des données.
        $refFacultes  = Etablissement::count();
        $refEtudiants = Etudiant::count();
        $refPresences = Presence::count();
        $refCoursJour = Evenement::whereDate('date', today())->count();

        // Faculté A : 2 filières, 1 admin, 2 étudiants, 1 cours aujourd'hui, 1 présence.
        $facA = $this->creerEtablissement('AGREGA');
        $this->creerAdminFaculte($facA);
        $filiereA = $this->creerFiliere($facA);
        $this->creerFiliere($facA);
        $etudiantA = $this->creerEtudiant($filiereA);
        $this->creerEtudiant($filiereA);
        $this->creerPresence($etudiantA, $this->creerEvenement($filiereA, today()->format('Y-m-d')));

        // Faculté B : 1 filière, 2 admins, 1 étudiant, 1 cours d'hier, 1 présence.
        $facB = $this->creerEtablissement('AGREGB');
        $this->creerAdminFaculte($facB);
        $this->creerAdminFaculte($facB);
        $filiereB = $this->creerFiliere($facB);
        $etudiantB = $this->creerEtudiant($filiereB);
        $this->creerPresence($etudiantB, $this->creerEvenement($filiereB, today()->subDay()->format('Y-m-d')));

        $reponse = $this->withToken($this->jetonSuperAdmin)->getJson('/api/super-admin/dashboard');

        $reponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_facultes', $refFacultes + 2)
            ->assertJsonPath('data.total_etudiants', $refEtudiants + 3)
            ->assertJsonPath('data.total_presences', $refPresences + 2)
            ->assertJsonPath('data.cours_aujourdhui', $refCoursJour + 1);

        $facultes = collect($reponse->json('data.facultes'));

        $ligneA = $facultes->firstWhere('id', $facA->id);
        $this->assertNotNull($ligneA, 'La faculté A doit figurer dans le détail par faculté.');
        $this->assertSame($facA->code, $ligneA['code']);
        $this->assertSame($facA->nom, $ligneA['nom']);
        $this->assertSame(2, $ligneA['filieres_count']);
        $this->assertSame(1, $ligneA['admins_count']);
        $this->assertTrue($ligneA['actif']);

        $ligneB = $facultes->firstWhere('id', $facB->id);
        $this->assertNotNull($ligneB, 'La faculté B doit figurer dans le détail par faculté.');
        $this->assertSame(1, $ligneB['filieres_count']);
        $this->assertSame(2, $ligneB['admins_count']);
    }

    // ── CRUD des facultés ────────────────────────────────────────

    public function test_la_creation_dune_faculte_cree_son_admin_et_lui_envoie_ses_identifiants(): void
    {
        Mail::fake();

        $code  = 'CRE' . Str::random(6);
        $email = strtolower("creation-{$this->sfx}@uac.test");

        $reponse = $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements', [
                'code'      => $code,
                'nom'       => 'Faculté créée par test',
                'email'     => $email,
                'telephone' => '+22990000000',
                'adresse'   => 'Abomey-Calavi',
            ]);

        $reponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Faculté créée avec succès. Les identifiants ont été envoyés par email.')
            ->assertJsonPath('data.etablissement.code', $code)
            ->assertJsonPath('data.etablissement.nom', 'Faculté créée par test')
            ->assertJsonPath('data.etablissement.email', $email)
            ->assertJsonPath('data.admin.email', $email);

        $etablissementId = (int) $reponse->json('data.etablissement.id');
        $motDePasse      = $reponse->json('data.admin.password');
        $this->assertSame(12, strlen($motDePasse), 'Le mot de passe temporaire fait 12 caractères.');

        $this->assertDatabaseHas('etablissements', [
            'id'    => $etablissementId,
            'code'  => $code,
            'email' => $email,
        ]);

        // L'administrateur de faculté existe et le mot de passe communiqué ouvre le compte.
        $admin = User::where('email', $email)->firstOrFail();
        $this->assertSame('faculte_admin', $admin->role);
        $this->assertSame($etablissementId, (int) $admin->etablissement_id);
        $this->assertTrue($admin->must_change_password, 'Le mot de passe temporaire doit être changé.');
        $this->assertTrue(Hash::check($motDePasse, $admin->password));

        // Le contrôleur appelle Mail::send : le mail part immédiatement, il
        // n'est pas mis en file malgré le trait Queueable du mailable.
        Mail::assertSent(
            WelcomeFaculteAdmin::class,
            fn (WelcomeFaculteAdmin $mail) => $mail->hasTo($email)
                && $mail->password === $motDePasse
                && (int) $mail->etablissement->id === $etablissementId
                && $mail->admin->email === $email
        );
        Mail::assertNotQueued(WelcomeFaculteAdmin::class);
    }

    public function test_la_creation_refuse_les_champs_manquants_et_les_doublons(): void
    {
        Mail::fake();

        $existante   = $this->creerEtablissement('DOUBLON');
        $comptesAvant = User::count();

        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['code', 'nom', 'email']);

        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements', [
                'code'  => $existante->code,
                'nom'   => 'Tentative de doublon',
                'email' => $existante->email,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['code', 'email']);

        // Aucun compte ni mail parasite n'a été produit par les refus.
        $this->assertSame($comptesAvant, User::count());
        Mail::assertNothingSent();
    }

    public function test_la_liste_et_le_detail_exposent_les_compteurs_de_la_faculte(): void
    {
        $faculte  = $this->creerEtablissement('DETAIL');
        $this->creerAdminFaculte($faculte);
        $filiere  = $this->creerFiliere($faculte);
        $etudiant = $this->creerEtudiant($filiere);
        $this->creerEtudiant($filiere);
        $this->creerPresence($etudiant, $this->creerEvenement($filiere, today()->format('Y-m-d')));

        // Faculté voisine : ses données ne doivent pas gonfler les compteurs.
        $voisine        = $this->creerEtablissement('VOISIN');
        $filiereVoisine = $this->creerFiliere($voisine);
        $etudiantVoisin = $this->creerEtudiant($filiereVoisine);
        $this->creerPresence($etudiantVoisin, $this->creerEvenement($filiereVoisine, today()->format('Y-m-d')));

        $liste = $this->withToken($this->jetonSuperAdmin)->getJson('/api/super-admin/etablissements');
        $liste->assertStatus(200)->assertJsonPath('success', true);

        $ligne = collect($liste->json('data'))->firstWhere('id', $faculte->id);
        $this->assertNotNull($ligne, 'La faculté créée doit apparaître dans la liste.');
        $this->assertSame(1, $ligne['filieres_count']);
        $this->assertSame(1, $ligne['users_count'], 'users_count ne compte que les admins de faculté.');

        $this->withToken($this->jetonSuperAdmin)
            ->getJson("/api/super-admin/etablissements/{$faculte->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $faculte->id)
            ->assertJsonPath('data.code', $faculte->code)
            ->assertJsonPath('data.filieres_count', 1)
            ->assertJsonPath('data.users_count', 1)
            ->assertJsonPath('data.total_etudiants', 2)
            ->assertJsonPath('data.total_presences', 1);

        // Identifiant inconnu : la route existe, c'est la ressource qui manque.
        $this->withToken($this->jetonSuperAdmin)
            ->getJson('/api/super-admin/etablissements/99999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Ressource non trouvée.');
    }

    public function test_la_mise_a_jour_modifie_la_faculte_et_synchronise_lemail_de_ladmin(): void
    {
        $faculte = $this->creerEtablissement('MAJ');
        $admin   = $this->creerAdminFaculte($faculte);

        $nouvelEmail = strtolower("maj-{$this->sfx}@uac.test");

        $this->withToken($this->jetonSuperAdmin)
            ->putJson("/api/super-admin/etablissements/{$faculte->id}", [
                'nom'       => 'Faculté rebaptisée',
                'email'     => $nouvelEmail,
                'telephone' => '+22991000000',
                'actif'     => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Faculté mise à jour.')
            ->assertJsonPath('data.nom', 'Faculté rebaptisée')
            ->assertJsonPath('data.email', $nouvelEmail)
            ->assertJsonPath('data.telephone', '+22991000000')
            ->assertJsonPath('data.actif', false)
            ->assertJsonPath('data.code', $faculte->code);

        $this->assertDatabaseHas('etablissements', [
            'id'    => $faculte->id,
            'nom'   => 'Faculté rebaptisée',
            'email' => $nouvelEmail,
            'actif' => false,
        ]);

        // L'email de l'administrateur suit celui de la faculté.
        $this->assertSame($nouvelEmail, $admin->fresh()->email);

        // Le code d'une autre faculté reste refusé.
        $autre = $this->creerEtablissement('MAJBIS');
        $this->withToken($this->jetonSuperAdmin)
            ->putJson("/api/super-admin/etablissements/{$faculte->id}", ['code' => $autre->code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertSame($faculte->code, $faculte->fresh()->code);
    }

    public function test_la_suppression_retire_la_faculte_mais_laisse_son_admin_orphelin(): void
    {
        $faculte = $this->creerEtablissement('SUPPR');
        $admin   = $this->creerAdminFaculte($faculte);
        $filiere = $this->creerFiliere($faculte);

        $this->withToken($this->jetonSuperAdmin)
            ->deleteJson("/api/super-admin/etablissements/{$faculte->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Faculté supprimée.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('etablissements', ['id' => $faculte->id]);

        // DÉFAUT constaté : les clés étrangères sont en nullOnDelete et le
        // contrôleur ne fait aucun ménage. Le compte administrateur survit,
        // toujours actif mais rattaché à rien, et la filière devient orpheline.
        $adminSurvivant = $admin->fresh();
        $this->assertNotNull($adminSurvivant, 'Le compte admin survit à la suppression de sa faculté.');
        $this->assertNull($adminSurvivant->etablissement_id);
        $this->assertSame('faculte_admin', $adminSurvivant->role);
        $this->assertNull($filiere->fresh()->etablissement_id);
    }

    public function test_les_stats_dune_faculte_ne_comptent_que_cette_faculte(): void
    {
        $cible    = $this->creerEtablissement('STATS');
        $filiere1 = $this->creerFiliere($cible);
        $filiere2 = $this->creerFiliere($cible);
        $etudiant1 = $this->creerEtudiant($filiere1);
        $etudiant2 = $this->creerEtudiant($filiere2);
        $this->creerPresence($etudiant1, $this->creerEvenement($filiere1, today()->format('Y-m-d')));
        $this->creerPresence($etudiant2, $this->creerEvenement($filiere2, today()->subDays(3)->format('Y-m-d')));

        // Faculté voisine volontairement plus fournie en étudiants.
        $voisine        = $this->creerEtablissement('VOISIN');
        $filiereVoisine = $this->creerFiliere($voisine);
        foreach (range(1, 3) as $ignore) {
            $this->creerEtudiant($filiereVoisine);
        }
        $this->creerEvenement($filiereVoisine, today()->format('Y-m-d'));

        $this->withToken($this->jetonSuperAdmin)
            ->getJson("/api/super-admin/etablissements/{$cible->id}/stats")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_filieres', 2)
            ->assertJsonPath('data.total_etudiants', 2)
            ->assertJsonPath('data.total_presences', 2)
            ->assertJsonPath('data.cours_aujourdhui', 1);

        $this->withToken($this->jetonSuperAdmin)
            ->getJson("/api/super-admin/etablissements/{$voisine->id}/stats")
            ->assertStatus(200)
            ->assertJsonPath('data.total_filieres', 1)
            ->assertJsonPath('data.total_etudiants', 3)
            ->assertJsonPath('data.total_presences', 0)
            ->assertJsonPath('data.cours_aujourdhui', 1);
    }

    public function test_le_renvoi_des_identifiants_remplace_le_mot_de_passe(): void
    {
        Mail::fake();

        // Création via l'API pour connaître le mot de passe initial.
        $email    = strtolower("resend-{$this->sfx}@uac.test");
        $creation = $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements', [
                'code'  => 'RSD' . Str::random(6),
                'nom'   => 'Faculté renvoi',
                'email' => $email,
            ])->assertStatus(201);

        $etablissementId  = (int) $creation->json('data.etablissement.id');
        $ancienMotDePasse = $creation->json('data.admin.password');

        $admin      = User::where('email', $email)->firstOrFail();
        $jetonAdmin = $admin->createToken('appareil-existant')->plainTextToken;

        $reponse = $this->withToken($this->jetonSuperAdmin)
            ->postJson("/api/super-admin/etablissements/{$etablissementId}/resend-credentials");

        $reponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Nouveaux identifiants envoyés par email.')
            ->assertJsonPath('data.email', $email);

        // Le contrôleur régénère le mot de passe et le renvoie en clair dans la
        // réponse HTTP (voir rapport : à réserver à l'email).
        $nouveauMotDePasse = $reponse->json('data.password');
        $this->assertSame(12, strlen($nouveauMotDePasse));
        $this->assertNotSame($ancienMotDePasse, $nouveauMotDePasse);

        $admin->refresh();
        $this->assertTrue(Hash::check($nouveauMotDePasse, $admin->password), 'Le nouveau mot de passe ouvre le compte.');
        $this->assertFalse(Hash::check($ancienMotDePasse, $admin->password), 'L\'ancien mot de passe est invalidé.');
        $this->assertTrue($admin->must_change_password);

        Mail::assertSent(
            WelcomeFaculteAdmin::class,
            fn (WelcomeFaculteAdmin $mail) => $mail->hasTo($email) && $mail->password === $nouveauMotDePasse
        );

        // Le renvoi d'identifiants révoque les jetons déjà émis : une session
        // ouverte avec l'ancien mot de passe ne survit pas à la réinitialisation.
        // Le renvoi d'identifiants révoque les jetons déjà émis : une session
        // ouverte avec l'ancien mot de passe ne survit pas à la réinitialisation.
        //
        // L'assertion porte sur la base plutôt que sur un appel HTTP : dans une
        // même méthode de test, l'en-tête Authorization posé par withToken() et
        // l'utilisateur résolu par le garde restent en place d'une requête à
        // l'autre, si bien qu'une sonde HTTP observait le super admin — un 200
        // sans aucun rapport avec le compte dont on vérifie la révocation.
        $this->assertSame(0, $admin->tokens()->count(), 'Les jetons de l\'admin sont révoqués.');
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => (string) $admin->id,
        ]);
    }

    public function test_le_renvoi_des_identifiants_sans_admin_renvoie_404(): void
    {
        Mail::fake();

        $faculte = $this->creerEtablissement('SANSAD');

        $this->withToken($this->jetonSuperAdmin)
            ->postJson("/api/super-admin/etablissements/{$faculte->id}/resend-credentials")
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Aucun admin trouvé pour cette faculté.');

        Mail::assertNothingSent();
    }

    public function test_recreer_une_faculte_supprimee_echoue_a_cause_de_lancien_admin(): void
    {
        Mail::fake();

        $code  = 'CYC' . Str::random(6);
        $email = strtolower("cycle-{$this->sfx}@uac.test");
        $corps = ['code' => $code, 'nom' => 'Faculté recréée', 'email' => $email];

        $creation = $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements', $corps)
            ->assertStatus(201);

        $this->withToken($this->jetonSuperAdmin)
            ->deleteJson('/api/super-admin/etablissements/' . $creation->json('data.etablissement.id'))
            ->assertStatus(200);

        // DÉFAUT constaté : l'unicité n'est vérifiée que sur la table
        // etablissements. Le compte admin de la faculté supprimée existe
        // toujours avec cet email, donc la recréation casse sur la contrainte
        // d'unicité de users.email — erreur serveur brute, et la faculté vient
        // d'être insérée avant l'échec (aucune transaction).
        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements', $corps)
            ->assertStatus(500);
    }

    // ── Import CSV en masse ──────────────────────────────────────

    public function test_limport_en_masse_cree_les_facultes_et_leurs_admins(): void
    {
        Mail::fake();

        $code1 = 'IM1' . Str::random(6);
        $code2 = 'IM2' . Str::random(6);
        $mail1 = strtolower("im1-{$this->sfx}@uac.test");
        $mail2 = strtolower("im2-{$this->sfx}@uac.test");

        $csv = "code,nom,email,telephone,adresse\n"
            . "{$code1},Faculté importée 1,{$mail1},+22990000001,Abomey-Calavi\n"
            . "{$code2},Faculté importée 2,{$mail2},+22990000002,Cotonou";

        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements/import', ['file' => $this->fichierCsv($csv)])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '2 faculté(s) créée(s).')
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.errors', []);

        foreach ([[$code1, $mail1, 'Faculté importée 1', '+22990000001'], [$code2, $mail2, 'Faculté importée 2', '+22990000002']] as [$code, $mail, $nom, $tel]) {
            $this->assertDatabaseHas('etablissements', [
                'code'      => $code,
                'nom'       => $nom,
                'email'     => $mail,
                'telephone' => $tel,
            ]);

            $admin = User::where('email', $mail)->firstOrFail();
            $this->assertSame('faculte_admin', $admin->role);
            $this->assertTrue($admin->must_change_password);
            $this->assertSame(
                Etablissement::where('code', $code)->value('id'),
                $admin->etablissement_id
            );
        }

        Mail::assertSent(WelcomeFaculteAdmin::class, 2);
    }

    public function test_limport_signale_les_doublons_ligne_par_ligne_sans_bloquer_les_autres(): void
    {
        Mail::fake();

        $existante = $this->creerEtablissement('IMPDUP');
        $facultesAvant = Etablissement::count();

        $codeValide = 'IMV' . Str::random(6);
        $mailValide = strtolower("imv-{$this->sfx}@uac.test");

        $csv = "code,nom,email,telephone,adresse\n"
            . "{$existante->code},Code déjà pris,autre-{$this->sfx}@uac.test,,\n"
            . "AUTRE{$this->sfx},Email déjà pris,{$existante->email},,\n"
            . "{$codeValide},Faculté valide,{$mailValide},+22990000003,Calavi";

        $reponse = $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements/import', ['file' => $this->fichierCsv($csv)]);

        $reponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '1 faculté(s) créée(s). 2 erreur(s).')
            ->assertJsonPath('data.created', 1);

        // Le rapport d'erreurs cite la ligne et la cause exacte. La
        // numérotation part du premier enregistrement (« Ligne 1 » = 2e ligne
        // du fichier) : voir rapport.
        $this->assertSame([
            "Ligne 1 : Le code '{$existante->code}' existe déjà.",
            "Ligne 2 : L'email '{$existante->email}' existe déjà.",
        ], $reponse->json('data.errors'));

        // Seule la ligne valide a été insérée.
        $this->assertSame($facultesAvant + 1, Etablissement::count());
        $this->assertDatabaseHas('etablissements', ['code' => $codeValide, 'email' => $mailValide]);
        $this->assertDatabaseMissing('etablissements', ['code' => 'AUTRE' . $this->sfx]);
        $this->assertDatabaseMissing('users', ['email' => strtolower("autre-{$this->sfx}@uac.test")]);
        Mail::assertSent(WelcomeFaculteAdmin::class, 1);
    }

    public function test_limport_nest_pas_transactionnel_une_ligne_incomplete_interrompt_tout(): void
    {
        Mail::fake();

        $codeAvant = 'ITR' . Str::random(6);
        $mailAvant = strtolower("itr-{$this->sfx}@uac.test");
        $codeApres = 'ITZ' . Str::random(6);

        // La 2e ligne n'a que deux colonnes pour cinq en-têtes.
        $csv = "code,nom,email,telephone,adresse\n"
            . "{$codeAvant},Faculté complète,{$mailAvant},+22990000004,Calavi\n"
            . "{$codeApres},Faculté tronquée";

        // DÉFAUT constaté : array_combine lève une ValueError, qui n'est pas
        // une \Exception et échappe donc au catch de la boucle. L'import
        // s'arrête sur une erreur serveur, sans rapport d'erreurs...
        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements/import', ['file' => $this->fichierCsv($csv)])
            ->assertStatus(500);

        // ...et les lignes déjà traitées restent en base : aucune transaction
        // n'englobe l'import.
        $this->assertDatabaseHas('etablissements', ['code' => $codeAvant, 'email' => $mailAvant]);
        $this->assertDatabaseHas('users', ['email' => $mailAvant, 'role' => 'faculte_admin']);
        $this->assertDatabaseMissing('etablissements', ['code' => $codeApres]);
    }

    public function test_limport_refuse_un_fichier_absent_vide_ou_mal_entete(): void
    {
        Mail::fake();

        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements/import', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['file']);

        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements/import', [
                'file' => $this->fichierCsv('code,nom,email'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Le fichier CSV doit contenir un en-tête et au moins une ligne.');

        $this->withToken($this->jetonSuperAdmin)
            ->postJson('/api/super-admin/etablissements/import', [
                'file' => $this->fichierCsv("code,nom\nIMPX{$this->sfx},Sans colonne email"),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "La colonne 'email' est requise dans le CSV.");

        $this->assertDatabaseMissing('etablissements', ['code' => 'IMPX' . $this->sfx]);
        Mail::assertNothingSent();
    }

    // ── Sécurité : toutes les routes du préfixe ──────────────────

    /**
     * Énumère les routes réellement déclarées sous /api/super-admin, pour
     * qu'aucune n'échappe aux tests de sécurité quand le préfixe s'enrichit.
     *
     * @return array<int, array{0: string, 1: string}>  [méthode, URI]
     */
    private function routesSuperAdmin(int $etablissementId): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/super-admin')) {
                continue;
            }

            $methodes = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
            $routes[] = [
                $methodes[0],
                '/' . preg_replace('/\{[^}]+\}/', (string) $etablissementId, $route->uri()),
            ];
        }

        return $routes;
    }

    public function test_toutes_les_routes_super_admin_sont_refusees_a_un_admin_de_faculte(): void
    {
        $faculte = $this->creerEtablissement('SECU');
        $routes  = $this->routesSuperAdmin($faculte->id);
        $uris    = array_column($routes, 1);

        // Garde-fou : une énumération vide ferait passer le test sans rien vérifier.
        $this->assertGreaterThanOrEqual(9, count($routes), 'Le préfixe /super-admin doit exposer au moins 9 routes.');
        $this->assertContains('/api/super-admin/dashboard', $uris);
        $this->assertContains('/api/super-admin/etablissements', $uris);
        $this->assertContains('/api/super-admin/etablissements/import', $uris);
        $this->assertContains("/api/super-admin/etablissements/{$faculte->id}", $uris);
        $this->assertContains("/api/super-admin/etablissements/{$faculte->id}/stats", $uris);
        $this->assertContains("/api/super-admin/etablissements/{$faculte->id}/resend-credentials", $uris);

        foreach ($routes as [$methode, $uri]) {
            $reponse = $this->json($methode, $uri, [], ['Authorization' => 'Bearer ' . $this->jetonFaculteAdmin]);

            $this->assertSame(403, $reponse->status(), "{$methode} {$uri} doit être refusé à un admin de faculté.");
            $this->assertFalse($reponse->json('success'), "{$methode} {$uri} : réponse d'erreur attendue.");
            $this->assertStringContainsString(
                'super_admin',
                (string) $reponse->json('message'),
                "{$methode} {$uri} : le message doit nommer le rôle requis."
            );
        }

        // Le DELETE de la boucle n'a rien détruit.
        $this->assertNotNull($faculte->fresh(), 'Aucune route n\'a agi malgré le refus.');
    }

    public function test_toutes_les_routes_super_admin_exigent_un_jeton(): void
    {
        $faculte = $this->creerEtablissement('SECUB');

        foreach ($this->routesSuperAdmin($faculte->id) as [$methode, $uri]) {
            $reponse = $this->json($methode, $uri);

            $this->assertSame(401, $reponse->status(), "{$methode} {$uri} doit répondre 401 sans jeton.");
            $this->assertFalse($reponse->json('success'), "{$methode} {$uri} : réponse d'erreur attendue.");
            $this->assertSame('Non authentifié.', $reponse->json('message'), "{$methode} {$uri} : message d'authentification attendu.");
        }

        $this->assertNotNull($faculte->fresh());
    }

    public function test_toutes_les_routes_super_admin_sont_refusees_a_un_jeton_etudiant(): void
    {
        $faculte = $this->creerEtablissement('SECUC');
        $jeton   = $this->creerEtudiant($this->creerFiliere($faculte))
            ->createToken('mobile-app')->plainTextToken;

        foreach ($this->routesSuperAdmin($faculte->id) as [$methode, $uri]) {
            $reponse = $this->json($methode, $uri, [], ['Authorization' => 'Bearer ' . $jeton]);

            $this->assertSame(403, $reponse->status(), "{$methode} {$uri} doit être refusé à un jeton étudiant.");
            $this->assertFalse($reponse->json('success'), "{$methode} {$uri} : réponse d'erreur attendue.");
        }

        $this->assertNotNull($faculte->fresh());
    }

    public function test_un_super_admin_au_mot_de_passe_temporaire_accede_quand_meme(): void
    {
        $jeton = User::factory()->create([
            'email'                => "temp-{$this->sfx}@example.test",
            'role'                 => 'super_admin',
            'must_change_password' => true,
        ])->createToken('t')->plainTextToken;

        // DÉFAUT constaté : le groupe /super-admin ne porte ni « password.changed »
        // ni « throttle:api », contrairement au groupe /admin. Un mot de passe
        // temporaire reste donc pleinement utilisable sur ces routes.
        $this->json('GET', '/api/super-admin/dashboard', [], ['Authorization' => 'Bearer ' . $jeton])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
