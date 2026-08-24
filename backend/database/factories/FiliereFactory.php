<?php

namespace Database\Factories;

use App\Models\Etablissement;
use App\Models\Filiere;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Filiere>
 */
class FiliereFactory extends Factory
{
    protected $model = Filiere::class;

    public function definition(): array
    {
        return [
            'code'             => 'FIL-' . Str::upper(Str::random(5)),
            'intitule'         => 'Filière ' . $this->faker->word(),
            'niveau'           => $this->faker->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']),
            'etablissement_id' => Etablissement::factory(),
        ];
    }
}
