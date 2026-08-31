<?php

namespace Tests\Feature\Schedule;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Import d'emploi du temps : vérification puis persistance.
 *
 * RÉGRESSIONS COUVERTES
 *
 * ImportController::validateEvents validait et enregistrait dans le même appel,
 * hors transaction, avec « exists:ecs,id » pour seul contrôle de cohérence. Un
 * fichier partiellement faux laissait donc ses premières lignes en base, et un
 * EC d'une autre filière passait sans un mot.
 */
class ImportSecuriseTest extends TestCase
{
    private string $token;
    private string $sfx;
    private Filiere $filiere;
    private AnneeAcademique $annee;
    private Ec $ec1;
    private Ec $ec2;
    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::random(6);

        $etab = DB::table('etablissements')->insertGetId([
            'code' => 'IM' . $this->sfx, 'nom' => 'Etab ' . $this->sfx,
            'email' => 'im-' . $this->sfx . '@example.test', 'actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'email' => 'imp-' . $this->sfx . '@example.test', 'role' => 'admin',
        ]);
        $this->token = $admin->createToken('test')->plainTextToken;

        $this->annee = AnneeAcademique::create([
            'libelle' => '2091-2092 ' . $this->sfx, 'date_debut' => '2091-09-01',
            'date_fin' => '2092-07-31', 'active' => false,
        ]);

        $this->filiere = Filiere::create([
            'code' => 'IMP' . $this->sfx, 'intitule' => 'Filière import', 'niveau' => 'L2',
            'etablissement_id' => $etab,
        ]);

        $ue = Ue::create([
            'code' => 'UEI' . $this->sfx, 'intitule' => 'UE import',
            'filiere_id' => $this->filiere->id, 'annee_id' => $this->annee->id,
            'semestre' => 3, 'volume_horaire' => 60,
        ]);

        $this->ec1 = Ec::create([
            'ue_id' => $ue->id, 'code' => 'E1' . $this->sfx,
            'intitule' => 'Algorithmique avancée', 'volume_horaire' => 30,
        ]);
        $this->ec2 = Ec::create([
            'ue_id' => $ue->id, 'code' => 'E2' . $this->sfx,
            'intitule' => 'Bases de données', 'volume_horaire' => 30,
        ]);

        $this->salle = Salle::create([
            'code' => 'SL' . $this->sfx, 'nom' => 'Salle import ' . $this->sfx,
            'capacite' => 80, 'actif' => true, 'etablissement_id' => $etab,
        ]);
    }

    /** @param list<array<string, mixed>> $creneaux */
    private function verifier(array $creneaux)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/admin/import/schedule/verifier', [
                'creneaux' => $creneaux,
                'filiere_id' => $this->filiere->id,
                'annee_id' => $this->annee->id,
            ]);
    }

    /** @param list<array<string, mixed>> $creneaux */
    private function confirmer(array $creneaux, bool $ignorer = false)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/admin/import/schedule/confirmer', [
                'creneaux' => $creneaux,
                'filiere_id' => $this->filiere->id,
                'annee_id' => $this->annee->id,
                'ignorer_les_refuses' => $ignorer,
            ]);
    }

    /** Un créneau hebdomadaire tel que l'extraction le rend désormais. */
    private function creneauValide(array $surcharges = []): array
    {
        return array_merge([
            'ec' => 'Algorithmique avancée', 'jour' => 'Lundi',
            'heure_debut' => '8h', 'heure_fin' => '10h',
            'salle' => $this->salle->code,
        ], $surcharges);
    }

    // ── Vérification : aucun effet de bord ──────────────────────────────

    public function test_la_verification_n_enregistre_rien(): void
    {
        $avant = EmploiDuTemps::count();

        $this->verifier([$this->creneauValide()])
            ->assertOk()
            ->assertJsonPath('data.valides', 1);

        $this->assertSame($avant, EmploiDuTemps::count(), 'La vérification ne doit rien écrire.');
    }

    public function test_la_verification_detaille_chaque_ligne_avec_son_motif(): void
    {
        $reponse = $this->verifier([
            $this->creneauValide(),
            ['ec' => 'Matière fantôme', 'jour' => 'Mardi', 'heure_debut' => '8h', 'heure_fin' => '10h'],
            ['ec' => 'Sans jour', 'heure_debut' => '8h', 'heure_fin' => '10h'],
        ])->assertOk();

        $lignes = $reponse->json('data.lignes');

        $this->assertCount(3, $lignes);
        $this->assertSame('valide', $lignes[0]['statut']);
        $this->assertSame('introuvable', $lignes[1]['statut']);
        $this->assertStringContainsString('Matière fantôme', $lignes[1]['motifs'][0]);
        $this->assertSame('invalide', $lignes[2]['statut']);
        $this->assertSame('jour', $lignes[2]['champ']);
    }

    // ── Persistance : tout ou rien ──────────────────────────────────────

    public function test_un_lot_entierement_valide_est_enregistre(): void
    {
        $this->confirmer([
            $this->creneauValide(),
            $this->creneauValide([
                'ec' => 'Bases de données', 'jour' => 'Mardi',
                'heure_debut' => '14h', 'heure_fin' => '16h',
            ]),
        ])->assertCreated()->assertJsonPath('data.enregistres', 2);

        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id' => $this->ec1->id, 'jour_semaine' => 1, 'heure_debut' => '08:00:00',
        ]);
        $this->assertDatabaseHas('emploi_du_temps', [
            'ec_id' => $this->ec2->id, 'jour_semaine' => 2, 'heure_debut' => '14:00:00',
        ]);
    }

    /**
     * LE défaut central de l'ancien chemin : la boucle créait les événements un
     * par un, hors transaction. Une erreur au dixième laissait les neuf premiers.
     */
    public function test_un_lot_partiellement_faux_n_ecrit_rien(): void
    {
        $avant = EmploiDuTemps::count();

        $this->confirmer([
            $this->creneauValide(),
            ['ec' => 'Matière fantôme', 'jour' => 'Mardi', 'heure_debut' => '8h', 'heure_fin' => '10h'],
        ])->assertStatus(422);

        $this->assertSame($avant, EmploiDuTemps::count(), 'Aucune ligne ne doit être écrite.');
        $this->assertDatabaseMissing('emploi_du_temps', ['ec_id' => $this->ec1->id]);
    }

    public function test_le_refus_dit_combien_de_lignes_posent_probleme(): void
    {
        $reponse = $this->confirmer([
            $this->creneauValide(),
            ['ec' => 'Fantôme A', 'jour' => 'Mardi', 'heure_debut' => '8h', 'heure_fin' => '10h'],
            ['ec' => 'Fantôme B', 'jour' => 'Mercredi', 'heure_debut' => '8h', 'heure_fin' => '10h'],
        ])->assertStatus(422);

        $this->assertStringContainsString('2 créneau(x) sur 3', $reponse->json('message'));
        $this->assertStringContainsString("Rien n'a été écrit", $reponse->json('message'));
    }

    /**
     * L'échappatoire doit être EXPLICITE : ignorer des lignes est une décision de
     * l'administrateur, jamais un comportement par défaut.
     */
    public function test_on_peut_demander_explicitement_a_ignorer_les_lignes_refusees(): void
    {
        $this->confirmer([
            $this->creneauValide(),
            ['ec' => 'Matière fantôme', 'jour' => 'Mardi', 'heure_debut' => '8h', 'heure_fin' => '10h'],
        ], ignorer: true)
            ->assertCreated()
            ->assertJsonPath('data.enregistres', 1)
            ->assertJsonPath('data.refuses', 1);

        $this->assertDatabaseHas('emploi_du_temps', ['ec_id' => $this->ec1->id]);
    }

    public function test_un_lot_sans_aucune_ligne_valide_est_refuse_meme_en_ignorant(): void
    {
        $this->confirmer([
            ['ec' => 'Fantôme', 'jour' => 'Lundi', 'heure_debut' => '8h', 'heure_fin' => '10h'],
        ], ignorer: true)->assertStatus(422);

        $this->assertSame(0, EmploiDuTemps::where('filiere_id', $this->filiere->id)->count());
    }

    // ── La confirmation revalide ────────────────────────────────────────

    /**
     * Le rapport rendu au client n'est pas une autorisation : entre la
     * vérification et la confirmation, un autre administrateur peut avoir occupé
     * la salle.
     */
    public function test_la_confirmation_revalide_contre_l_etat_courant_de_la_base(): void
    {
        $creneau = $this->creneauValide();

        $this->verifier([$creneau])->assertOk()->assertJsonPath('data.valides', 1);

        // Quelqu'un occupe la salle entre-temps.
        $autreFiliere = Filiere::create([
            'code' => 'CNC' . $this->sfx, 'intitule' => 'Concurrente', 'niveau' => 'L2',
        ]);
        $autreUe = Ue::create([
            'code' => 'UEC' . $this->sfx, 'intitule' => 'UE', 'filiere_id' => $autreFiliere->id,
            'annee_id' => $this->annee->id, 'semestre' => 3, 'volume_horaire' => 30,
        ]);
        $autreEc = Ec::create([
            'ue_id' => $autreUe->id, 'code' => 'ECC' . $this->sfx,
            'intitule' => 'Concurrent', 'volume_horaire' => 30,
        ]);
        EmploiDuTemps::create([
            'ec_id' => $autreEc->id, 'filiere_id' => $autreFiliere->id,
            'annee_id' => $this->annee->id, 'jour_semaine' => 1,
            'heure_debut' => '09:00', 'heure_fin' => '11:00', 'salle_id' => $this->salle->id,
        ]);

        $this->confirmer([$creneau])->assertStatus(422);

        $this->assertDatabaseMissing('emploi_du_temps', ['ec_id' => $this->ec1->id]);
    }

    // ── Cloisonnement ───────────────────────────────────────────────────

    public function test_un_admin_de_faculte_ne_peut_pas_importer_pour_une_autre_faculte(): void
    {
        $autreEtab = DB::table('etablissements')->insertGetId([
            'code' => 'AE' . $this->sfx, 'nom' => 'Autre ' . $this->sfx,
            'email' => 'ae-' . $this->sfx . '@example.test', 'actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'email' => 'fac-' . $this->sfx . '@example.test',
            'role' => 'faculte_admin', 'etablissement_id' => $autreEtab,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('t')->plainTextToken)
            ->postJson('/api/admin/import/schedule/confirmer', [
                'creneaux' => [$this->creneauValide()],
                'filiere_id' => $this->filiere->id,
                'annee_id' => $this->annee->id,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('emploi_du_temps', ['ec_id' => $this->ec1->id]);
    }

    // ── Conflits internes au document ───────────────────────────────────

    public function test_un_document_qui_se_contredit_est_refuse(): void
    {
        $reponse = $this->confirmer([
            $this->creneauValide(),
            // Même promotion, créneau chevauchant, cours différent.
            $this->creneauValide([
                'ec' => 'Bases de données', 'heure_debut' => '9h', 'heure_fin' => '11h',
                'salle' => null,
            ]),
        ])->assertStatus(422);

        $lignes = $reponse->json('data.lignes');
        $this->assertSame('conflit', $lignes[1]['statut']);
        $this->assertStringContainsString('Conflit de promotion', implode(' ', $lignes[1]['motifs']));
    }

    public function test_un_creneau_en_double_dans_le_document_est_signale(): void
    {
        $reponse = $this->verifier([
            $this->creneauValide(),
            $this->creneauValide(),
        ])->assertOk();

        $this->assertSame('doublon', $reponse->json('data.lignes.1.statut'));
    }

    /**
     * Le nombre de requêtes doit être CONSTANT, pas proportionnel au lot.
     *
     * Chaque créneau déclenchait trois requêtes — doublon, conflit de salle,
     * conflit de promotion — en plus des résolutions. Mesuré à 3,08 requêtes par
     * créneau sur un lot de 48, soit 148 allers-retours. Sur une base hébergée à
     * ~20 ms de latence, un emploi du temps de faculté y passait plusieurs
     * secondes.
     *
     * Les données de référence et les créneaux existants sont désormais
     * préchargés une fois, et les conflits calculés en mémoire.
     */
    public function test_le_nombre_de_requetes_ne_croit_pas_avec_la_taille_du_lot(): void
    {
        $jours = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $creneaux = [];

        foreach (range(1, 6) as $j) {
            foreach ([['08:00', '10:00'], ['10:15', '12:15'], ['14:00', '16:00'], ['16:15', '18:15']] as [$d, $f]) {
                $creneaux[] = [
                    'ec' => 'Algorithmique avancée', 'jour' => $jours[$j],
                    'heure_debut' => $d, 'heure_fin' => $f, 'salle' => $this->salle->code,
                ];
            }
        }

        $this->assertCount(24, $creneaux);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->verifier($creneaux)->assertOk();

        $requetes = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Généreux : l'authentification et le cloisonnement comptent aussi. Le
        // point est qu'on reste dans un ordre de grandeur CONSTANT, très loin des
        // 3 requêtes par créneau d'avant.
        $this->assertLessThan(
            30,
            $requetes,
            "24 créneaux ont coûté {$requetes} requêtes : le préchargement ne fonctionne plus."
        );
    }
}
