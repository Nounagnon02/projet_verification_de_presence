<?php

namespace Database\Factories;

use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Presence>
 */
class PresenceFactory extends Factory
{
    protected $model = Presence::class;

    public function definition(): array
    {
        return [
            'etudiant_id'        => Etudiant::factory(),
            'evenement_id'       => Evenement::factory(),
            'heure_scan'         => Carbon::now(),
            'device_fingerprint' => 'empreinte-' . Str::lower(Str::random(8)),
            'ip_address'         => $this->faker->ipv4(),
            'statut'             => 'valide',
        ];
    }

    /**
     * Presence en attente de validation manuelle.
     *
     * C'est le statut produit par la detection d'appareil partage : deux
     * etudiants, meme empreinte, meme evenement. C'est aussi ce que la file
     * d'attente de /attendance/queue donne a arbitrer.
     */
    public function suspecte(): static
    {
        return $this->state(fn () => ['statut' => 'suspect']);
    }

    public function rejetee(string $motif = 'Appareil partagé confirmé'): static
    {
        return $this->state(fn () => [
            'statut'           => 'rejete',
            'validation_motif' => $motif,
        ]);
    }

    /** Deux presences partageant une empreinte : le cas d'appareil partage. */
    public function surAppareil(string $empreinte): static
    {
        return $this->state(fn () => ['device_fingerprint' => $empreinte]);
    }
}
