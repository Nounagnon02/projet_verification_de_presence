<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\IdentifiantService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Etudiant>
 */
class EtudiantFactory extends Factory
{
    protected $model = Etudiant::class;

    public function definition(): array
    {
        $sfx = Str::upper(Str::random(5));

        return [
            'nom'             => 'NOM' . $sfx,
            'prenom'          => 'Prenom' . $sfx,
            'matricule'       => 'MAT-' . $sfx,
            'email'           => 'etudiant-' . Str::lower($sfx) . '@uac.test',
            'filiere_id'      => Filiere::factory(),
            'annee_id'        => AnneeAcademique::factory(),
            'est_responsable' => false,
        ];
    }

    /**
     * L'identifiant unique est deterministe (CDC 7.1.3) et depend du code de la
     * filiere et du libelle de l'annee. Il ne peut donc etre calcule qu'une fois
     * les deux rattachements connus, d'ou ce configure() plutot qu'une valeur
     * dans definition().
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Etudiant $etudiant) {
            if (empty($etudiant->identifiant_unique) && $etudiant->filiere_id && $etudiant->annee_id) {
                $etudiant->identifiant_unique = IdentifiantService::generate(
                    $etudiant->nom,
                    $etudiant->prenom,
                    $etudiant->matricule,
                    $etudiant->filiere_id,
                    $etudiant->annee_id,
                );
            }
        });
    }

    /** Etudiant rattache a une filiere et une annee existantes. */
    public function pour(Filiere $filiere, AnneeAcademique $annee): static
    {
        return $this->state(fn () => [
            'filiere_id' => $filiere->id,
            'annee_id'   => $annee->id,
        ]);
    }

    /** Delegue : seul habilite a afficher le QR du cours dans l'application. */
    public function delegue(): static
    {
        return $this->state(fn () => ['est_responsable' => true]);
    }
}
