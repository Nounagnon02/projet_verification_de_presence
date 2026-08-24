<?php

namespace Database\Factories;

use App\Models\Ec;
use App\Models\Ue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ec>
 */
class EcFactory extends Factory
{
    protected $model = Ec::class;

    public function definition(): array
    {
        return [
            'ue_id'          => Ue::factory(),
            'code'           => 'EC-' . Str::upper(Str::random(5)),
            'intitule'       => 'EC ' . $this->faker->words(2, true),
            'volume_horaire' => $this->faker->randomElement([10, 15, 20, 25]),
        ];
    }
}
