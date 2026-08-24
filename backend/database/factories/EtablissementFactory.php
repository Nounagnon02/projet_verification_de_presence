<?php

namespace Database\Factories;

use App\Models\Etablissement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Etablissement>
 */
class EtablissementFactory extends Factory
{
    protected $model = Etablissement::class;

    public function definition(): array
    {
        // « code » et « email » sont uniques en base : les suffixer aleatoirement
        // evite qu'une suite creant plusieurs etablissements ne se heurte a la
        // contrainte au deuxieme appel.
        $sfx = Str::upper(Str::random(5));

        return [
            'code'      => 'ETAB-' . $sfx,
            'nom'       => 'Faculté ' . $sfx,
            'email'     => 'etab-' . Str::lower($sfx) . '@uac.test',
            'telephone' => '+229' . $this->faker->numerify('########'),
            'adresse'   => $this->faker->streetAddress(),
            'actif'     => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
