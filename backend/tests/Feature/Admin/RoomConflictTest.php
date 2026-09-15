<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Etablissement;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deux événements ne peuvent pas occuper la même salle sur des créneaux qui
 * se chevauchent. Les créneaux consécutifs (10h-12h après 8h-10h) sont, eux,
 * autorisés.
 */
class RoomConflictTest extends TestCase
{
    private string $token;
    private int $ecId;
    private int $autreEcId;
    private int $salleId;
    private string $demain;
    private int $etabId;

    protected function setUp(): void
    {
        parent::setUp();
        $sfx = Str::random(5);

        $this->token = User::factory()->create([
            'email' => 'room-' . $sfx . '@example.test', 'role' => 'super_admin',
        ])->createToken('t')->plainTextToken;

        $annee = $this->anneeActive();
        $filiere = Filiere::create(['code' => 'RC' . $sfx, 'intitule' => 'T', 'niveau' => 'L1']);
        $ue = Ue::create(['code' => 'UE' . $sfx, 'intitule' => 'UE', 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 40]);
        $this->ecId = Ec::create(['ue_id' => $ue->id, 'code' => 'EC' . $sfx, 'intitule' => 'EC', 'volume_horaire' => 40])->id;
        // Second EC, d'une AUTRE filière : deux séances simultanées dans des
        // salles différentes ne sont légitimes qu'entre publics distincts. Un
        // même cours ne se dédouble pas à la même heure (chevauchement), et une
        // promotion ne suit pas deux cours à la fois (conflit de promotion).
        $autreFiliere = Filiere::create(['code' => 'RD' . $sfx, 'intitule' => 'T bis', 'niveau' => 'L1']);
        $autreUe = Ue::create(['code' => 'UF' . $sfx, 'intitule' => 'UE bis', 'filiere_id' => $autreFiliere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 40]);
        $this->autreEcId = Ec::create(['ue_id' => $autreUe->id, 'code' => 'EC2' . $sfx, 'intitule' => 'EC bis', 'volume_horaire' => 40])->id;
        $etab = $this->uneEntite()->id;
        $this->salleId = Salle::create(['nom' => 'Salle ' . $sfx, 'code' => 'S' . $sfx, 'actif' => true, 'etablissement_id' => $etab])->id;
        $this->etabId = $etab;
        $this->demain = today()->addDay()->format('Y-m-d');
    }

    private function creer(array $o = [])
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/admin/evenements', array_merge([
                'ec_id' => $this->ecId, 'date' => $this->demain,
                'heure_debut' => '08:00', 'heure_fin' => '10:00',
                'salle_id' => $this->salleId,
            ], $o));
    }

    public function test_chevauchement_meme_salle_refuse(): void
    {
        $this->creer()->assertStatus(201);
        // 09:00-11:00 chevauche 08:00-10:00 dans la même salle.
        $this->creer(['heure_debut' => '09:00', 'heure_fin' => '11:00'])->assertStatus(422);
    }

    public function test_creneaux_consecutifs_autorises(): void
    {
        $this->creer()->assertStatus(201);
        // 10:00-12:00 juste après 08:00-10:00 : pas de chevauchement.
        $this->creer(['heure_debut' => '10:00', 'heure_fin' => '12:00'])->assertStatus(201);
    }

    public function test_autre_salle_autorisee(): void
    {
        $this->creer()->assertStatus(201);
        $autreSalle = Salle::create(['nom' => 'Autre', 'code' => 'A' . Str::random(4), 'actif' => true, 'etablissement_id' => $this->etabId])->id;

        // Autre COURS dans l'autre salle : c'est bien le conflit de salle que ce
        // test éprouve. Réutiliser le même cours ferait échouer sur la règle de
        // chevauchement, sans rien dire des salles.
        $this->creer(['salle_id' => $autreSalle, 'ec_id' => $this->autreEcId])->assertStatus(201);
    }

    private function modifier(int $id, array $donnees)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/admin/evenements/' . $id, $donnees);
    }

    /** Deux séances déjà en conflit dans la même salle, posées sans passer par l'API. */
    private function conflitHerite(): array
    {
        $commun = [
            'filiere_id' => Ec::find($this->ecId)->ue->filiere_id,
            'annee_id'   => Ec::find($this->ecId)->ue->annee_id,
            'date'       => $this->demain, 'heure_debut' => '08:00', 'heure_fin' => '10:00',
            'salle_id'   => $this->salleId, 'statut' => 'planifie',
        ];

        return [
            Evenement::create($commun + ['ec_id' => $this->ecId]),
            Evenement::create($commun + ['ec_id' => $this->autreEcId]),
        ];
    }

    public function test_le_nom_de_la_salle_choisie_est_recopie(): void
    {
        // Les écrans affichent evenements.salle : il doit porter le nom de la
        // salle configurée, quel que soit le texte éventuellement envoyé.
        $id = $this->creer(['salle' => 'Nom fantaisiste'])->assertStatus(201)->json('data.id');

        $this->assertSame(Salle::find($this->salleId)->nom, Evenement::find($id)->salle);
    }

    public function test_retirer_la_salle_efface_le_nom_recopie(): void
    {
        $id = $this->creer()->assertStatus(201)->json('data.id');

        $this->modifier($id, ['salle_id' => null])->assertStatus(200);

        $e = Evenement::find($id);
        $this->assertNull($e->salle_id);
        $this->assertNull($e->salle, 'le nom de l\'ancienne salle ne doit pas survivre');
    }

    public function test_une_seance_jamais_rattachee_garde_son_nom_saisi(): void
    {
        // Séance issue d'un ancien import : un nom saisi, aucune salle
        // configurée. L'enregistrer sans choisir de salle ne doit rien effacer.
        $id = $this->creer(['salle_id' => null, 'salle' => 'Labo historique'])->assertStatus(201)->json('data.id');

        $this->modifier($id, ['salle_id' => null, 'statut' => 'termine'])->assertStatus(200);

        $this->assertSame('Labo historique', Evenement::find($id)->salle);
    }

    public function test_une_seance_en_conflit_herite_reste_modifiable_si_son_creneau_ne_bouge_pas(): void
    {
        // Le conflit existait avant la règle (données de démonstration, reprise
        // des salles saisies) : clore la séance ne doit pas être refusé.
        [$a] = $this->conflitHerite();

        $this->modifier($a->id, [
            'date' => $this->demain, 'heure_debut' => '08:00', 'heure_fin' => '10:00',
            'salle_id' => $this->salleId, 'statut' => 'termine',
        ])->assertStatus(200);
    }

    public function test_deplacer_une_seance_vers_un_creneau_occupe_reste_refuse(): void
    {
        $this->creer()->assertStatus(201);
        $id = $this->creer(['ec_id' => $this->autreEcId, 'heure_debut' => '10:00', 'heure_fin' => '12:00'])
            ->assertStatus(201)->json('data.id');

        $this->modifier($id, ['heure_debut' => '09:00', 'heure_fin' => '11:00'])->assertStatus(422);
    }

    public function test_reactiver_une_seance_annulee_sur_un_creneau_occupe_est_refuse(): void
    {
        // Une séance annulée libère sa salle. La réactiver la réoccupe : elle
        // doit donc repasser le contrôle, même sans changer d'horaire.
        // Une séance naît planifiée : l'annulation se fait en modification.
        $annulee = $this->creer([])->assertStatus(201)->json('data.id');
        $this->modifier($annulee, ['statut' => 'annule'])->assertOk();
        $this->creer(['ec_id' => $this->autreEcId])->assertStatus(201);

        $this->modifier($annulee, ['statut' => 'planifie'])->assertStatus(422);
    }
}
