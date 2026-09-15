<?php

namespace Tests\Feature\Schedule;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use App\Services\Schedule\ValidateurCreneau;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Validation académique et détection des conflits.
 *
 * RÉGRESSIONS COUVERTES
 *
 * ImportController::validateEvents se contentait de « exists:ecs,id ». Un EC
 * appartenant à une AUTRE filière était donc accepté, ainsi qu'un semestre
 * étranger au niveau. Aucun conflit n'était cherché côté serveur : l'écran en
 * signalait côté client, et le serveur enregistrait tout.
 *
 * Le conflit de PROMOTION — deux cours simultanés pour la même filière —
 * n'existait dans aucun des deux chemins d'import, alors que c'est la contrainte
 * la plus élémentaire d'un emploi du temps.
 */
class ValidateurCreneauTest extends TestCase
{
    private ValidateurCreneau $v;
    private Filiere $filiere;
    private Filiere $autreFiliere;
    private AnneeAcademique $annee;
    private Ec $ec;
    private Ec $autreEc;
    private Ec $ecAutreFiliere;
    private Salle $salle;
    private int $etablissementId;
    private string $sfx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::random(6);
        $this->v = new ValidateurCreneau();

        $this->annee = AnneeAcademique::create([
            'libelle' => '2088-2089 ' . $this->sfx, 'date_debut' => '2088-09-01',
            'date_fin' => '2089-07-31', 'active' => false,
        ]);

        // Les salles ne sont cherchées que dans l'établissement de la filière :
        // filières et salle partagent donc le même.
        $this->etablissementId = DB::table('etablissements')->insertGetId([
            'code' => 'ET' . $this->sfx, 'nom' => 'Etablissement ' . $this->sfx,
            'email' => 'etab-' . $this->sfx . '@example.test', 'actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Filière de L2 : ses UE doivent être en S3 ou S4.
        $this->filiere = Filiere::create([
            'code' => 'VAL' . $this->sfx, 'intitule' => 'Filière validée', 'niveau' => 'L2',
            'etablissement_id' => $this->etablissementId,
        ]);
        $this->autreFiliere = Filiere::create([
            'code' => 'AUT' . $this->sfx, 'intitule' => 'Autre filière', 'niveau' => 'L2',
            'etablissement_id' => $this->etablissementId,
        ]);

        $ue = $this->ue($this->filiere, 3, 'UE1');
        $this->ec = $this->creerEc($ue, 'EC1', 'Algorithmique avancée');
        $this->autreEc = $this->creerEc($ue, 'EC2', 'Bases de données');

        $ueAutre = $this->ue($this->autreFiliere, 3, 'UEX');
        $this->ecAutreFiliere = $this->creerEc($ueAutre, 'ECX', 'Cours étranger');

        $this->salle = Salle::create([
            'code' => 'S' . $this->sfx, 'nom' => 'Amphi Test ' . $this->sfx,
            'capacite' => 100, 'actif' => true, 'etablissement_id' => $this->etablissementId,
        ]);

        $this->assertTrue($this->v->preparer($this->filiere->id, $this->annee->id)['ok']);
    }

    private function ue(Filiere $f, int $semestre, string $code): Ue
    {
        return Ue::create([
            'code' => $code . $this->sfx, 'intitule' => 'UE ' . $code,
            'filiere_id' => $f->id, 'annee_id' => $this->annee->id,
            'semestre' => $semestre, 'volume_horaire' => 60,
        ]);
    }

    private function creerEc(Ue $ue, string $code, string $intitule): Ec
    {
        return Ec::create([
            'ue_id' => $ue->id, 'code' => $code . $this->sfx,
            'intitule' => $intitule, 'volume_horaire' => 30,
        ]);
    }

    /** @param array<string, mixed> $surcharges */
    private function creneau(array $surcharges = []): array
    {
        return array_merge([
            'ec_libelle'   => 'Algorithmique avancée',
            'ec_code'      => null,
            'jour_semaine' => 1,
            'date'         => null,
            'heure_debut'  => '08:00',
            'heure_fin'    => '10:00',
            // La salle choisie sur l'écran de validation, par son identifiant.
            'salle'        => null,
            'salle_id'     => $this->salle->id,
            'sans_salle'   => false,
            'enseignants'  => [],
            'filiere'      => null,
            'type_seance'  => null,
        ], $surcharges);
    }

    // ── Résolution de l'EC ──────────────────────────────────────────────

    public function test_un_ec_de_la_filiere_est_resolu_par_son_libelle(): void
    {
        $r = $this->v->valider($this->creneau());

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut'], implode(' ', $r['motifs']));
        $this->assertSame($this->ec->id, $r['creneau']['ec_id']);
    }

    public function test_le_code_prime_sur_le_libelle(): void
    {
        $r = $this->v->valider($this->creneau([
            'ec_code' => $this->autreEc->code, 'ec_libelle' => 'Algorithmique avancée',
        ]));

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut']);
        $this->assertSame($this->autreEc->id, $r['creneau']['ec_id']);
    }

    /**
     * LE défaut central : « exists:ecs,id » acceptait un EC de n'importe quelle
     * filière. Le fait qu'un enseignement existe ne le rend pas valide ICI.
     */
    public function test_un_ec_d_une_autre_filiere_est_introuvable(): void
    {
        $r = $this->v->valider($this->creneau(['ec_libelle' => 'Cours étranger']));

        $this->assertSame(ValidateurCreneau::INTROUVABLE, $r['statut']);
        $this->assertStringContainsString("n'existe pas dans la filière", $r['motifs'][0]);
    }

    public function test_un_ec_inexistant_est_introuvable_avec_un_motif_utile(): void
    {
        $r = $this->v->valider($this->creneau(['ec_libelle' => 'Matière fantôme']));

        $this->assertSame(ValidateurCreneau::INTROUVABLE, $r['statut']);
        $this->assertStringContainsString('Matière fantôme', $r['motifs'][0]);
        $this->assertStringContainsString('Créez-le', $r['motifs'][0]);
    }

    /** Les fiches abrègent : « Bases de données » pour un intitulé plus long. */
    public function test_un_libelle_abrege_est_resolu_si_le_candidat_est_unique(): void
    {
        $r = $this->v->valider($this->creneau(['ec_libelle' => 'Bases de données']));

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut']);
        $this->assertSame($this->autreEc->id, $r['creneau']['ec_id']);
    }

    /**
     * Deux candidats ne sont pas un choix à faire : rattacher le créneau au
     * mauvais cours produirait des absences pour les mauvais étudiants.
     */
    public function test_plusieurs_candidats_donnent_ambigu_et_non_un_choix_arbitraire(): void
    {
        // Aucune correspondance EXACTE : « Algorithmique » ne designe aucun EC
        // precisement, mais se retrouve dans deux intitules. C'est cela qui est
        // ambigu. Une correspondance exacte, elle, doit legitimement primer.
        $ue = $this->ue($this->filiere, 4, 'UEA');
        $this->creerEc($ue, 'ECB', 'Algorithmique répartie');

        $this->v->preparer($this->filiere->id, $this->annee->id);

        $r = $this->v->valider($this->creneau(['ec_libelle' => 'Algorithmique']));

        $this->assertSame(ValidateurCreneau::AMBIGU, $r['statut']);
        $this->assertStringContainsString('plusieurs enseignements', $r['motifs'][0]);
    }

    // ── Cohérence semestre / niveau ─────────────────────────────────────

    public function test_un_ec_dont_le_semestre_est_etranger_au_niveau_est_signale(): void
    {
        // Filière de L2 (S3-S4) portant une UE de S7 : incohérent.
        $ue = $this->ue($this->filiere, 7, 'UEM');
        $ecM = $this->creerEc($ue, 'ECM', 'Cours de master');

        $this->v->preparer($this->filiere->id, $this->annee->id);

        $r = $this->v->valider($this->creneau(['ec_code' => $ecM->code, 'ec_libelle' => 'Cours de master']));

        $this->assertSame(ValidateurCreneau::AMBIGU, $r['statut']);
        $this->assertStringContainsString('incompatible avec la filière', implode(' ', $r['motifs']));
    }

    // ── Salle ───────────────────────────────────────────────────────────

    public function test_une_salle_choisie_par_son_identifiant_est_rattachee(): void
    {
        $r = $this->v->valider($this->creneau());

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut'], implode(' ', $r['motifs']));
        $this->assertSame($this->salle->id, $r['creneau']['salle_id']);
        // Le nom recopié est celui de la salle configurée, jamais un nom lu :
        // les séances générées l'affichent.
        $this->assertSame($this->salle->nom, $r['creneau']['salle_libelle']);
    }

    /**
     * « Zone Master A2 » du fichier réel de l'IFRI : un nom lu par l'IA n'est
     * plus conservé en texte libre. Le créneau attend que l'administrateur
     * choisisse une salle, la crée, ou choisisse « Aucune — QR seul ».
     */
    public function test_un_nom_de_salle_sans_choix_laisse_le_creneau_a_completer(): void
    {
        $r = $this->v->valider($this->creneau(['salle' => 'Zone Master A2', 'salle_id' => null]));

        $this->assertSame(ValidateurCreneau::AMBIGU, $r['statut']);
        $this->assertNull($r['creneau']['salle_id']);
        $this->assertNull($r['creneau']['salle_libelle']);
        $this->assertStringContainsString('Salle « Zone Master A2 » à choisir', implode(' ', $r['motifs']));
    }

    /** Un nom reconnu est proposé, mais seul l'identifiant engage. */
    public function test_un_nom_reconnu_est_suggere_sans_etre_retenu(): void
    {
        $r = $this->v->valider($this->creneau(['salle' => strtolower($this->salle->code), 'salle_id' => null]));

        $this->assertSame(ValidateurCreneau::AMBIGU, $r['statut']);
        $this->assertNull($r['creneau']['salle_id']);
        $this->assertStringContainsString("correspond à « {$this->salle->nom} »", implode(' ', $r['motifs']));
    }

    public function test_aucune_salle_est_un_choix_legitime(): void
    {
        $r = $this->v->valider($this->creneau(['salle' => 'Zone Master A2', 'salle_id' => null, 'sans_salle' => true]));

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut'], implode(' ', $r['motifs']));
        $this->assertNull($r['creneau']['salle_id']);
    }

    /** Les salles étaient cherchées dans tout le système, toutes facultés confondues. */
    public function test_une_salle_d_un_autre_etablissement_est_refusee(): void
    {
        $ailleurs = DB::table('etablissements')->insertGetId([
            'code' => 'EX' . $this->sfx, 'nom' => 'Ailleurs ' . $this->sfx,
            'email' => 'ailleurs-' . $this->sfx . '@example.test', 'actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $salleAilleurs = Salle::create([
            'code' => 'X' . $this->sfx, 'nom' => 'Salle ailleurs', 'actif' => true, 'etablissement_id' => $ailleurs,
        ]);

        $this->v->preparer($this->filiere->id, $this->annee->id);

        $r = $this->v->valider($this->creneau(['salle_id' => $salleAilleurs->id]));

        $this->assertSame(ValidateurCreneau::INVALIDE, $r['statut']);
        $this->assertStringContainsString("n'existe pas dans l'établissement", $r['motifs'][0]);
    }

    // ── Doublons ────────────────────────────────────────────────────────

    public function test_un_creneau_present_deux_fois_dans_le_lot_est_un_doublon(): void
    {
        $this->assertSame(ValidateurCreneau::VALIDE, $this->v->valider($this->creneau())['statut']);

        $r = $this->v->valider($this->creneau());

        $this->assertSame(ValidateurCreneau::DOUBLON, $r['statut']);
        $this->assertStringContainsString('deux fois dans le document', $r['motifs'][0]);
    }

    public function test_un_creneau_deja_en_base_est_un_doublon(): void
    {
        EmploiDuTemps::create([
            'ec_id' => $this->ec->id, 'filiere_id' => $this->filiere->id,
            'annee_id' => $this->annee->id, 'jour_semaine' => 1,
            'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        // Les creneaux existants sont precharges par preparer() : une ligne
        // ajoutee apres coup exige une nouvelle preparation. C'est exactement ce
        // que fait le controleur, qui prepare a chaque examen — y compris a la
        // confirmation, pour revalider contre l'etat courant.
        $this->v->preparer($this->filiere->id, $this->annee->id);

        $r = $this->v->valider($this->creneau());

        $this->assertSame(ValidateurCreneau::DOUBLON, $r['statut']);
        $this->assertStringContainsString("existe déjà à l'emploi du temps", $r['motifs'][0]);
    }

    // ── Conflits ────────────────────────────────────────────────────────

    /**
     * La contrainte la plus élémentaire d'un emploi du temps, absente des DEUX
     * chemins d'import : une promotion ne peut pas suivre deux cours à la fois.
     */
    public function test_deux_cours_simultanes_pour_la_meme_promotion_sont_en_conflit(): void
    {
        $this->v->valider($this->creneau());

        $r = $this->v->valider($this->creneau([
            'ec_libelle' => 'Bases de données', 'salle_id' => null,
            'heure_debut' => '09:00', 'heure_fin' => '11:00',
        ]));

        $this->assertSame(ValidateurCreneau::CONFLIT, $r['statut']);
        $this->assertStringContainsString('Conflit de promotion', implode(' ', $r['motifs']));
    }

    public function test_une_salle_occupee_en_base_est_un_conflit(): void
    {
        $autreUe = $this->ue($this->autreFiliere, 3, 'UEY');
        $autreEcY = $this->creerEc($autreUe, 'ECY', 'Cours concurrent');

        EmploiDuTemps::create([
            'ec_id' => $autreEcY->id, 'filiere_id' => $this->autreFiliere->id,
            'annee_id' => $this->annee->id, 'jour_semaine' => 1,
            'heure_debut' => '09:00', 'heure_fin' => '11:00',
            'salle_id' => $this->salle->id,
        ]);

        $this->v->preparer($this->filiere->id, $this->annee->id);

        $r = $this->v->valider($this->creneau());

        $this->assertSame(ValidateurCreneau::CONFLIT, $r['statut']);
        $this->assertStringContainsString('Conflit de salle', implode(' ', $r['motifs']));
    }

    public function test_un_enseignant_attendu_sur_deux_creneaux_simultanes_est_en_conflit(): void
    {
        $this->v->valider($this->creneau(['enseignants' => ['Ratheil HOUNDJI']]));

        $r = $this->v->valider($this->creneau([
            'ec_libelle' => 'Bases de données', 'salle_id' => null,
            'heure_debut' => '09:00', 'heure_fin' => '11:00',
            'enseignants' => ['M. Ratheil HOUNDJI'],
        ]));

        $this->assertSame(ValidateurCreneau::CONFLIT, $r['statut']);
        $this->assertStringContainsString("Conflit d'enseignant", implode(' ', $r['motifs']));
    }

    /**
     * Le pendant : deux créneaux qui se SUIVENT ne se chevauchent pas. Une
     * comparaison trop large refuserait un emploi du temps parfaitement valide.
     */
    public function test_deux_creneaux_consecutifs_ne_sont_pas_en_conflit(): void
    {
        $this->assertSame(ValidateurCreneau::VALIDE, $this->v->valider($this->creneau())['statut']);

        $r = $this->v->valider($this->creneau([
            'ec_libelle' => 'Bases de données',
            'heure_debut' => '10:00', 'heure_fin' => '12:00',
        ]));

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut'], implode(' ', $r['motifs']));
    }

    public function test_le_meme_creneau_un_autre_jour_n_est_pas_en_conflit(): void
    {
        $this->v->valider($this->creneau());

        $r = $this->v->valider($this->creneau(['jour_semaine' => 2]));

        $this->assertSame(ValidateurCreneau::VALIDE, $r['statut'], implode(' ', $r['motifs']));
    }

    // ── Type de séance ──────────────────────────────────────────────────

    public function test_le_type_de_seance_est_ramene_aux_valeurs_du_modele(): void
    {
        foreach ([
            'Travaux pratiques' => 'tp',
            'TD groupe A'       => 'td',
            "Evaluation par l'enseignant" => 'evaluation',
            // Une seule écriture pour le cours magistral, celle de TypeCours.
            'Cours magistral'   => 'cm',
            null                => 'cm',
        ] as $brut => $attendu) {
            $this->v->preparer($this->filiere->id, $this->annee->id);

            $r = $this->v->valider($this->creneau(['type_seance' => $brut ?: null]));

            $this->assertSame($attendu, $r['creneau']['type_cours'], "« {$brut} »");
        }
    }
}
