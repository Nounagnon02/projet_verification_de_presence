<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Ue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ue>
 */
class UeFactory extends Factory
{
    protected $model = Ue::class;

    public function definition(): array
    {
        return [
            'code'           => 'UE-' . Str::upper(Str::random(5)),
            'intitule'       => 'UE ' . $this->faker->words(2, true),
            'filiere_id'     => Filiere::factory(),
            'annee_id'       => AnneeAcademique::factory(),
            'semestre'       => $this->faker->numberBetween(1, 10),
            'volume_horaire' => $this->faker->randomElement([20, 30, 40, 50]),
        ];
    }

    /**
     * UE rattachee a une filiere existante ET a l'annee de cette filiere.
     *
     * Sans cela, filiere_id et annee_id proviennent de deux factories
     * independantes : l'UE se retrouve rattachee a l'annee d'un autre
     * etablissement, et tous les tests de cloisonnement deviennent faux.
     */
    public function pour(Filiere $filiere, AnneeAcademique $annee): static
    {
        return $this->state(fn () => [
            'filiere_id' => $filiere->id,
            'annee_id'   => $annee->id,
        ]);
    }
}
