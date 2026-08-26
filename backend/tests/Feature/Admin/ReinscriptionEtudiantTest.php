<?php

namespace Tests\Feature\Admin;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Réinscription d'un étudiant supprimé.
 *
 * Régression : la suppression d'un étudiant est douce — et doit l'être, car les
 * présences portent etudiant_id et une suppression franche effacerait
 * l'historique. Mais l'index unique du matricule compte les lignes supprimées,
 * et aucune route n'expose les étudiants supprimés.
 *
 * L'administrateur était donc dans une impasse complète : l'étudiant avait
 * disparu de tous les écrans, et le réinscrire était refusé par une ligne qu'il
 * ne pouvait ni voir, ni restaurer, ni supprimer pour de bon. Le message
 * « Ce matricule existe déjà » désignait un étudiant introuvable.
 *
 * L'écran annonçait de surcroît « Cette action est irréversible », ce qui était
 * faux : la ligne subsistait.
 */
class ReinscriptionEtudiantTest extends TestCase
{
    private string $token;
    private Filiere $filiere;
    private AnneeAcademique $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $suffixe = Str::random(6);

        $admin = User::factory()->create([
            'email' => 'reinscr-' . $suffixe . '@example.test',
            'role'  => 'admin',
        ]);
        $this->token = $admin->createToken('test')->plainTextToken;

        // Une seule année active à la fois : le contrôleur impose l'année active
        // à l'inscription, on désactive donc les autres.
        AnneeAcademique::where('active', true)->update(['active' => false]);

        $this->annee = AnneeAcademique::create([
            'libelle' => '2085-2086 ' . $suffixe, 'date_debut' => '2085-09-01',
            'date_fin' => '2086-07-31', 'active' => true,
        ]);

        $this->filiere = Filiere::create([
            'code' => 'REI' . $suffixe, 'intitule' => 'Réinscription', 'niveau' => 'L1',
        ]);
    }

    /** @return array<string, mixed> */
    private function charge(string $matricule, string $email): array
    {
        return [
            'nom'        => 'Padonou',
            'prenom'     => 'Cédric',
            'matricule'  => $matricule,
            'email'      => $email,
            'filiere_id' => $this->filiere->id,
        ];
    }

    private function inscrire(array $charge)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/admin/students', $charge);
    }

    private function supprimer(string $id)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/admin/students/' . $id);
    }

    public function test_un_etudiant_supprime_peut_etre_reinscrit_avec_son_matricule(): void
    {
        $suffixe = Str::random(5);
        $matricule = 'M-' . $suffixe;

        $id = $this->inscrire($this->charge($matricule, 'a' . $suffixe . '@etu.test'))
            ->assertCreated()->json('data.id');

        $this->supprimer($id)->assertOk();

        // L'étudiant a bien disparu des écrans.
        $this->assertNull(Etudiant::find($id));
        $this->assertNotNull(Etudiant::withTrashed()->find($id));

        $this->inscrire($this->charge($matricule, 'a' . $suffixe . '@etu.test'))
            ->assertCreated();

        $this->assertNotNull(Etudiant::find($id), 'La ligne d\'origine doit être restaurée, non dupliquée.');
    }

    public function test_la_reinscription_restaure_la_ligne_au_lieu_d_en_creer_une(): void
    {
        $suffixe = Str::random(5);
        $matricule = 'M-' . $suffixe;

        $id = $this->inscrire($this->charge($matricule, 'b' . $suffixe . '@etu.test'))
            ->assertCreated()->json('data.id');

        $this->supprimer($id);
        $this->inscrire($this->charge($matricule, 'b' . $suffixe . '@etu.test'))->assertCreated();

        // Une seule ligne pour ce matricule : c'est ce qui rend son historique
        // de présence à l'étudiant, puisque les présences pointent sur cet id.
        $this->assertSame(
            1,
            Etudiant::withTrashed()->where('matricule', $matricule)->count(),
            'Un doublon aurait orphelinisé tout l\'historique de présence.'
        );
    }

    public function test_la_reinscription_prend_les_nouvelles_informations(): void
    {
        $suffixe = Str::random(5);
        $matricule = 'M-' . $suffixe;

        $id = $this->inscrire($this->charge($matricule, 'c' . $suffixe . '@etu.test'))
            ->assertCreated()->json('data.id');
        $this->supprimer($id);

        $autre = Filiere::create([
            'code' => 'REJ' . $suffixe, 'intitule' => 'Autre filière', 'niveau' => 'L2',
        ]);

        $charge = $this->charge($matricule, 'nouveau' . $suffixe . '@etu.test');
        $charge['nom'] = 'Adjovi';
        $charge['filiere_id'] = $autre->id;

        $this->inscrire($charge)->assertCreated();

        $etudiant = Etudiant::find($id);
        $this->assertSame('ADJOVI', $etudiant->nom);
        $this->assertSame($autre->id, $etudiant->filiere_id);
        $this->assertSame('nouveau' . $suffixe . '@etu.test', $etudiant->email);
    }

    public function test_un_matricule_deja_pris_par_un_etudiant_vivant_reste_refuse(): void
    {
        $suffixe = Str::random(5);
        $matricule = 'M-' . $suffixe;

        $this->inscrire($this->charge($matricule, 'd' . $suffixe . '@etu.test'))->assertCreated();

        // Aucune suppression : le refus doit subsister, sinon on écraserait
        // l'étudiant en place.
        $this->inscrire($this->charge($matricule, 'e' . $suffixe . '@etu.test'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('matricule');
    }

    public function test_un_email_retenu_par_un_autre_etudiant_supprime_donne_un_message_clair(): void
    {
        $suffixe = Str::random(5);

        // Premier étudiant, supprimé : il garde son email dans l'index unique.
        $id = $this->inscrire($this->charge('M-A' . $suffixe, 'occupe' . $suffixe . '@etu.test'))
            ->assertCreated()->json('data.id');
        $this->supprimer($id);

        // Un AUTRE matricule réclame cet email : le matricule ne permet pas de
        // retrouver la ligne, l'index unique se déclenche. On doit répondre par
        // un message exploitable, non par une erreur serveur.
        $this->inscrire($this->charge('M-B' . $suffixe, 'occupe' . $suffixe . '@etu.test'))
            ->assertStatus(409);
    }
}
