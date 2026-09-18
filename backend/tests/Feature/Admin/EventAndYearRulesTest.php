<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Ue;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventAndYearRulesTest extends TestCase
{
    private function token(): string
    {
        return User::factory()->create([
            'email'                   => 'evt-' . Str::random(6) . '@example.test',
            'role'                    => 'super_admin',
            // Le groupe /super-admin exige désormais la 2FA (voir routes/api.php).
            'two_factor_confirmed_at' => now(),
        ])->createToken('t')->plainTextToken;
    }

    public function test_evenement_deduit_filiere_et_annee_de_lec(): void
    {
        // Un EC rattaché à une UE (donc à une filière + année précises).
        $ue = $this->uneUe();
        $ec = Ec::where('ue_id', $ue->id)->first()
            ?? Ec::create(['ue_id' => $ue->id, 'code' => 'EVT' . Str::random(4), 'intitule' => 'Test', 'volume_horaire' => 20, 'statut' => 'planifie']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/evenements', [
                'ec_id'       => $ec->id,
                'date'        => now()->addDay()->toDateString(),
                'heure_debut' => '08:00',
                'heure_fin'   => '10:00',
                // On envoie volontairement des valeurs incohérentes : elles
                // doivent être ignorées au profit de celles déduites de l'EC.
                'filiere_id'  => 99999,
                'annee_id'    => 99999,
            ]);

        $response->assertStatus(201);
        $evenement = Evenement::findOrFail($response->json('data.id'));
        $this->assertSame($ue->filiere_id, $evenement->filiere_id);
        $this->assertSame($ue->annee_id, $evenement->annee_id);
    }

    /**
     * Il reste toujours une année en cours : on en désigne une autre, on ne la
     * désactive pas. Désigner la suivante remplace la précédente.
     */
    public function test_designer_lannee_en_cours_remplace_la_precedente(): void
    {
        $active = $this->anneeActive();
        $autre  = $this->anneeNonActive();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->patchJson("/api/super-admin/annees-academiques/{$autre->id}/en-cours")
            ->assertOk();

        $this->assertFalse($active->fresh()->active);
        $this->assertTrue($autre->fresh()->active);
        $this->assertSame(1, AnneeAcademique::where('active', true)->count());
    }

    public function test_impossible_de_supprimer_lannee_active(): void
    {
        $active = $this->anneeActive();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->deleteJson('/api/super-admin/annees-academiques/' . $active->id)
            ->assertStatus(409);

        $this->assertNotNull($active->fresh());
    }

    public function test_un_evenement_se_cree_toujours_au_statut_planifie(): void
    {
        $ec = $this->unEcDeVolume(20);
        $token = $this->token();
        $donnees = [
            'ec_id'       => $ec->id,
            'date'        => now()->addDay()->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
        ];

        // Créer une séance déjà annulée, déjà terminée ou déjà en cours n'a
        // aucun sens : le refus doit venir du serveur, pas seulement du formulaire.
        foreach (['annule', 'termine', 'en_cours'] as $statut) {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/admin/evenements', $donnees + ['statut' => $statut])
                ->assertStatus(422)
                ->assertJsonValidationErrors('statut');
        }
        $this->assertSame(0, Evenement::where('ec_id', $ec->id)->count());

        // Statut absent : planifié par défaut.
        $id = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/admin/evenements', $donnees)
            ->assertStatus(201)
            ->json('data.id');
        $this->assertSame('planifie', Evenement::findOrFail($id)->statut);
    }

    public function test_une_annulation_reste_possible_en_modification(): void
    {
        $ec = $this->unEcDeVolume(20);
        $id = $this->creerEvenement($ec, '08:00', '10:00')->assertStatus(201)->json('data.id');

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $id, ['statut' => 'annule'])
            ->assertOk();

        $this->assertSame('annule', Evenement::findOrFail($id)->statut);
    }

    /** EC neuf, rattaché à l'UE de test, avec le volume demandé. */
    private function unEcDeVolume(int $heures): Ec
    {
        return Ec::create([
            'ue_id'          => $this->uneUe()->id,
            'code'           => 'VOL' . Str::random(5),
            'intitule'       => 'Cours volume ' . Str::random(4),
            'volume_horaire' => $heures,
            'statut'         => 'planifie',
        ]);
    }

    private function creerEvenement(Ec $ec, string $debut, string $fin, int $joursApres = 1)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/admin/evenements', [
                'ec_id'       => $ec->id,
                'date'        => now()->addDays($joursApres)->toDateString(),
                'heure_debut' => $debut,
                'heure_fin'   => $fin,
            ]);
    }

    public function test_creneau_depassant_le_volume_restant_est_refuse(): void
    {
        $ec = $this->unEcDeVolume(10);

        // 8h consommées, en deux séances : aucune ne dépasse le plafond de durée,
        // ce que ce test ne cherche pas à éprouver.
        $this->creerEvenement($ec, '08:00', '12:00', 1)->assertStatus(201);
        $this->creerEvenement($ec, '14:00', '18:00', 1)->assertStatus(201);

        // 4h demandées pour 2h restantes.
        $this->creerEvenement($ec, '08:00', '12:00', 2)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(2, Evenement::where('ec_id', $ec->id)->count());
    }

    public function test_creneau_egal_au_volume_restant_est_accepte(): void
    {
        // Le cas limite doit passer : refuser à l'égalité empêcherait d'épuiser
        // le volume prévu.
        $ec = $this->unEcDeVolume(10);
        $this->creerEvenement($ec, '08:00', '12:00', 1)->assertStatus(201);
        $this->creerEvenement($ec, '14:00', '18:00', 1)->assertStatus(201);

        // Exactement les 2h qui restent.
        $this->creerEvenement($ec, '08:00', '10:00', 2)->assertStatus(201);

        $this->creerEvenement($ec, '08:00', '09:00', 3)->assertStatus(422);
    }

    public function test_une_seance_annulee_libere_son_creneau(): void
    {
        $ec = $this->unEcDeVolume(4);
        $premier = $this->creerEvenement($ec, '08:00', '12:00', 1)->assertStatus(201)->json('data.id');

        // Volume épuisé.
        $this->creerEvenement($ec, '08:00', '09:00', 2)->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $premier, ['statut' => 'annule'])
            ->assertStatus(200);

        // La séance annulée ne compte plus : le volume est de nouveau disponible.
        $this->creerEvenement($ec, '08:00', '09:00', 3)->assertStatus(201);
    }

    public function test_un_evenement_modifie_ne_se_compte_pas_lui_meme(): void
    {
        $ec = $this->unEcDeVolume(4);
        $id = $this->creerEvenement($ec, '08:00', '10:00', 1)->assertStatus(201)->json('data.id');

        // Rallonger de 2h à 4h tient dans le volume : sans exclure l'événement de
        // son propre décompte, ce serait refusé (2h déjà prises + 4h > 4h).
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $id, ['heure_debut' => '08:00', 'heure_fin' => '12:00'])
            ->assertStatus(200);

        // 5h reste dans le plafond de durée mais dépasse le volume de l'EC : le
        // refus vient bien du volume, pas de la durée.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $id, ['heure_debut' => '08:00', 'heure_fin' => '13:00'])
            ->assertStatus(422);
    }

    public function test_une_seance_ne_peut_pas_depasser_la_duree_maximale(): void
    {
        // Volume largement suffisant : seule la durée de la séance est en cause.
        $ec = $this->unEcDeVolume(40);
        $max = (float) config('presence.seance.duree_max_heures');

        $trop = (int) $max + 1;
        $this->creerEvenement($ec, '08:00', sprintf('%02d:00', 8 + $trop), 1)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Le cas limite passe : refuser à l'égalité interdirait la séance la plus
        // longue que le règlement autorise.
        $this->creerEvenement($ec, '08:00', sprintf('%02d:00', 8 + (int) $max), 2)
            ->assertStatus(201);
    }

    public function test_plusieurs_seances_du_meme_cours_le_meme_jour_sont_possibles(): void
    {
        // Le plafond porte sur la durée d'un créneau, jamais sur leur nombre :
        // découper une journée en plusieurs séances doit rester possible.
        $ec = $this->unEcDeVolume(40);

        $this->creerEvenement($ec, '08:00', '13:00', 1)->assertStatus(201);
        $this->creerEvenement($ec, '14:00', '18:00', 1)->assertStatus(201);
        $this->creerEvenement($ec, '18:00', '20:00', 1)->assertStatus(201);

        $this->assertSame(3, Evenement::where('ec_id', $ec->id)
            ->where('date', now()->addDay()->toDateString())->count());
    }

    public function test_deux_seances_du_meme_cours_ne_peuvent_pas_se_chevaucher(): void
    {
        $ec = $this->unEcDeVolume(40);
        $this->creerEvenement($ec, '08:00', '10:00', 1)->assertStatus(201);

        // Chevauchement partiel, inclusion et créneau identique : tous refusés.
        $this->creerEvenement($ec, '09:00', '11:00', 1)->assertStatus(422);
        $this->creerEvenement($ec, '08:30', '09:30', 1)->assertStatus(422);
        $this->creerEvenement($ec, '08:00', '10:00', 1)->assertStatus(422);

        $this->assertSame(1, Evenement::where('ec_id', $ec->id)
            ->where('date', now()->addDay()->toDateString())->count());
    }

    public function test_deux_seances_qui_se_touchent_ne_se_chevauchent_pas(): void
    {
        // 10h-12h juste après 08h-10h : la borne est stricte des deux côtés,
        // sinon enchaîner deux séances dans la journée deviendrait impossible.
        $ec = $this->unEcDeVolume(40);
        $this->creerEvenement($ec, '08:00', '10:00', 1)->assertStatus(201);
        $this->creerEvenement($ec, '10:00', '12:00', 1)->assertStatus(201);

        // Et le même créneau un autre jour ne gêne évidemment pas.
        $this->creerEvenement($ec, '08:00', '10:00', 2)->assertStatus(201);
    }

    public function test_le_chevauchement_ignore_les_seances_annulees_et_soi_meme(): void
    {
        $ec = $this->unEcDeVolume(40);
        $id = $this->creerEvenement($ec, '08:00', '10:00', 1)->assertStatus(201)->json('data.id');

        // Déplacer l'événement à l'intérieur de sa propre plage : il ne doit pas
        // se voir lui-même comme un conflit.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $id, ['heure_debut' => '08:30', 'heure_fin' => '10:30'])
            ->assertStatus(200);

        // Une séance annulée libère son créneau.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $id, ['statut' => 'annule'])
            ->assertStatus(200);

        $this->creerEvenement($ec, '08:30', '10:30', 1)->assertStatus(201);
    }

    /** Pose directement un événement passé : l'API refuserait de le créer. */
    private function evenementPasse(Ec $ec, int $joursAvant = 3): Evenement
    {
        return Evenement::create([
            'ec_id'       => $ec->id,
            'filiere_id'  => $ec->ue->filiere_id,
            'annee_id'    => $ec->ue->annee_id,
            'date'        => now()->subDays($joursAvant)->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin'   => '10:00',
            'statut'      => 'planifie',
        ]);
    }

    public function test_un_evenement_ne_peut_pas_etre_cree_dans_le_passe(): void
    {
        $ec = $this->unEcDeVolume(20);

        $this->creerEvenement($ec, '08:00', '10:00', -1)
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', "La date d'un événement ne peut pas être antérieure à aujourd'hui.");

        // Aujourd'hui reste permis : c'est le jour même de l'enregistrement.
        $this->creerEvenement($ec, '08:00', '10:00', 0)->assertStatus(201);
    }

    public function test_un_evenement_passe_reste_modifiable_si_sa_date_ne_change_pas(): void
    {
        // Le formulaire renvoie la date avec le reste : un cours d'hier doit
        // pouvoir être clos ou annulé sans que sa date, inchangée, soit refusée.
        $ec = $this->unEcDeVolume(20);
        $passe = $this->evenementPasse($ec->load('ue'));

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $passe->id, [
                'date'        => $passe->date->format('Y-m-d'),
                'heure_debut' => '08:00',
                'heure_fin'   => '10:00',
                'statut'      => 'termine',
            ])
            ->assertStatus(200);

        $this->assertSame('termine', $passe->fresh()->statut);
    }

    public function test_un_evenement_ne_peut_pas_etre_deplace_dans_le_passe(): void
    {
        $ec = $this->unEcDeVolume(20);
        $passe = $this->evenementPasse($ec->load('ue'), 3);

        // Vers une autre date passée : refusé.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $passe->id, [
                'date' => now()->subDays(5)->toDateString(),
            ])
            ->assertStatus(422);

        // Reporté à une date future : accepté.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/admin/evenements/' . $passe->id, [
                'date' => now()->addDays(2)->toDateString(),
            ])
            ->assertStatus(200);
    }
}
