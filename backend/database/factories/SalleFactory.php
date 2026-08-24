<?php

namespace Database\Factories;

use App\Models\Etablissement;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Salle>
 */
class SalleFactory extends Factory
{
    protected $model = Salle::class;

    public function definition(): array
    {
        return [
            'etablissement_id' => Etablissement::factory(),
            'nom'              => 'Salle ' . Str::upper(Str::random(3)),
            'code'             => 'SALLE-' . Str::upper(Str::random(5)),
            // Coordonnees du campus d'Abomey-Calavi, pour que les tests de
            // georeperage travaillent sur des distances realistes.
            'latitude'         => 6.3608,
            'longitude'        => 2.4354,
            'rayon_geofence_m' => 50,
            'hors_reseau'      => false,
            'actif'            => true,
        ];
    }

    /** Salle sans GPS : le scan doit passer en « non_config », pas echouer. */
    public function sansGps(): static
    {
        return $this->state(fn () => ['latitude' => null, 'longitude' => null]);
    }

    /** Salle exigeant un reseau Wi-Fi precis. */
    public function avecWifi(string $ssid = 'UAC-WIFI', string $bssid = '00:11:22:33:44:55'): static
    {
        return $this->state(fn () => [
            'ssid_attendu'  => $ssid,
            'bssid_attendu' => $bssid,
            'hors_reseau'   => false,
        ]);
    }

    /** Salle en mode hors-reseau : aucun controle Wi-Fi. */
    public function horsReseau(): static
    {
        return $this->state(fn () => ['hors_reseau' => true]);
    }

    /** Salle inactive : le scan retombe en mode basique (QR seul). */
    public function inactive(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
