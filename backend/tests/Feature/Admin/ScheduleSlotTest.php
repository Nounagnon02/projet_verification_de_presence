<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\EmploiDuTemps;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Préremplissage du formulaire d'événement depuis l'emploi du temps.
 *
 * Vérifie l'endpoint de suggestion de créneaux, son cloisonnement par entité,
 * et surtout que l'endpoint et la commande de génération automatique produisent
 * les mêmes valeurs — c'est le seul vrai risque de cette fonctionnalité, les
 * deux chemins pouvant sinon dériver l'un de l'autre.
 */
class ScheduleSlotTest extends TestCase
{
    private User $admin;
    private string $token;
    private Ec $ec;
    private AnneeAcademique $annee;
    private Filiere $filiere;

    /** Un lundi, pour raisonner sur un jour de semaine connu. */
    private Carbon $lundi;

    protected function setUp(): void
    {
        parent::setUp();

        $sfx = Str::random(5);

        // Rôle explicite : la factory produit par défaut un « faculte_admin »
        // sans établissement, que le middleware de cloisonnement rejette en 403
        // (fail-closed). Un super administrateur n'est pas filtré.
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test-token')->plainTextToken;

        $this->filiere = Filiere::create([
            'code'     => 'EDT-' . $sfx,
            'intitule' => 'Filière Emploi du temps',
            'niveau'   => 'M1',
        ]);

        $this->annee = AnneeAcademique::create([
            'libelle'    => '2097-2098 ' . $sfx,
            'date_debut' => '2097-10-01',
            'date_fin'   => '2098-09-30',
            'active'     => false,
        ]);

        $ue = Ue::create([
            'code'           => 'UE-' . $sfx,
            'intitule'       => 'UE Emploi du temps',
            'filiere_id'     => $this->filiere->id,
            'annee_id'       => $this->annee->id,
            'semestre'       => 1,
            'volume_horaire' => 40,
        ]);

        $this->ec = Ec::create([
            'ue_id'          => $ue->id,
            'code'           => 'EC-' . $sfx,
            'intitule'       => 'EC Emploi du temps',
            'volume_horaire' => 40,
        ]);

        $this->lundi = Carbon::parse('next monday')->startOfDay();

        // Deux créneaux le lundi : un cours le matin, un TD l'après-midi. Le
        // formulaire doit pouvoir proposer les deux.
        EmploiDuTemps::create([
            'ec_id'         => $this->ec->id,
            'filiere_id'    => $this->filiere->id,
            'annee_id'      => $this->annee->id,
            'jour_semaine'  => 1,
            'heure_debut'   => '08:00:00',
            'heure_fin'     => '10:00:00',
            'salle_libelle' => 'Amphi A',
            'type_cours'    => 'cours',
        ]);

        EmploiDuTemps::create([
            'ec_id'         => $this->ec->id,
            'filiere_id'    => $this->filiere->id,
            'annee_id'      => $this->annee->id,
            'jour_semaine'  => 1,
            'heure_debut'   => '14:00:00',
            'heure_fin'     => '16:00:00',
            'salle_libelle' => 'Salle TD 3',
            'type_cours'    => 'td',
        ]);
    }

    /** L'email est obligatoire et unique sur les entités. */
    private function creerEntite(string $prefixe): Etablissement
    {
        $sfx = Str::random(6);

        return Etablissement::create([
            'nom'   => "Entité {$prefixe} {$sfx}",
            'code'  => "{$prefixe}-{$sfx}",
            'email' => strtolower("{$prefixe}.{$sfx}@test.local"),
        ]);
    }

    private function demanderCreneaux(string $date, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . ($token ?? $this->token))
            ->getJson('/api/admin/evenements/creneaux-emploi-du-temps?ec_id=' . $this->ec->id . '&date=' . $date);
    }

    public function test_authentification_requise(): void
    {
        $this->getJson('/api/admin/evenements/creneaux-emploi-du-temps?ec_id=' . $this->ec->id
            . '&date=' . $this->lundi->format('Y-m-d'))
            ->assertStatus(401);
    }

    public function test_renvoie_les_deux_creneaux_du_lundi(): void
    {
        $reponse = $this->demanderCreneaux($this->lundi->format('Y-m-d'));

        $reponse->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.heure_debut', '08:00')
            ->assertJsonPath('data.0.heure_fin', '10:00')
            ->assertJsonPath('data.0.salle', 'Amphi A')
            ->assertJsonPath('data.0.type_cours', 'cours')
            ->assertJsonPath('data.1.heure_debut', '14:00')
            ->assertJsonPath('data.1.type_cours', 'td');
    }

    public function test_aucun_creneau_un_autre_jour(): void
    {
        // Le mardi, aucun créneau n'est déclaré pour cet EC.
        $mardi = $this->lundi->copy()->addDay();

        $this->demanderCreneaux($mardi->format('Y-m-d'))
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_date_invalide_rejetee(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/admin/evenements/creneaux-emploi-du-temps?ec_id=' . $this->ec->id . '&date=pas-une-date')
            ->assertStatus(422);
    }

    public function test_cloisonnement_par_entite(): void
    {
        // La filière appartient à une entité, l'intrus administre une autre.
        $this->filiere->update(['etablissement_id' => $this->creerEntite('AE')->id]);

        $intrus = User::factory()->create([
            'role'             => 'faculte_admin',
            'etablissement_id' => $this->creerEntite('EI')->id,
        ]);

        $this->demanderCreneaux(
            $this->lundi->format('Y-m-d'),
            $intrus->createToken('intrus')->plainTextToken
        )->assertStatus(404);
    }

    /**
     * Garantie anti-dérive : le créneau proposé au formulaire doit correspondre
     * exactement à l'événement que la commande planifiée aurait créé.
     */
    public function test_endpoint_et_commande_concordent(): void
    {
        $date = $this->lundi->format('Y-m-d');

        $this->artisan('events:generate-from-schedule', ['--date' => $date, '--days' => 1])
            ->assertSuccessful();

        $evenements = Evenement::where('ec_id', $this->ec->id)
            ->where('date', $date)
            ->orderBy('heure_debut')
            ->get();

        $this->assertCount(2, $evenements, 'La commande doit créer les deux séances du lundi.');

        $creneaux = $this->demanderCreneaux($date)->json('data');

        foreach ($evenements as $i => $evenement) {
            $this->assertSame(
                substr((string) $evenement->heure_debut, 0, 5),
                $creneaux[$i]['heure_debut'],
                'Les heures de début proposées et générées doivent coïncider.'
            );
            $this->assertSame(
                substr((string) $evenement->heure_fin, 0, 5),
                $creneaux[$i]['heure_fin'],
                'Les heures de fin proposées et générées doivent coïncider.'
            );
            $this->assertSame(
                $evenement->salle_id,
                $creneaux[$i]['salle_id'],
                'La salle proposée et celle générée doivent coïncider.'
            );
        }
    }
}
