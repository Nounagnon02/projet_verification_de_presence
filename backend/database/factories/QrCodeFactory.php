<?php

namespace Database\Factories;

use App\Models\Evenement;
use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition(): array
    {
        return [
            'evenement_id' => Evenement::factory(),
            'token'        => (string) Str::uuid(),
            // Le TTL nominal est de 60 s (config/presence.php). Cinq minutes
            // ici, pour qu'un test n'echoue pas sur l'horloge.
            'expire_at'    => Carbon::now()->addMinutes(5),
            'actif'        => true,
        ];
    }

    /** Jeton expire : tout scan doit repondre 410. */
    public function expire(): static
    {
        return $this->state(fn () => ['expire_at' => Carbon::now()->subMinute()]);
    }

    /** Jeton deja consomme : le scan l'invalide des la premiere utilisation. */
    public function consomme(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
