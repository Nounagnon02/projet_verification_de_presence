<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Etablissement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnneeAcademique>
 */
class AnneeAcademiqueFactory extends Factory
{
    protected $model = AnneeAcademique::class;

    /**
     * Compteur d'annees produites, pour que deux appels successifs donnent deux
     * libelles distincts (« 2025-2026 » puis « 2026-2027 »).
     */
    private static int $rang = 0;

    public function definition(): array
    {
        $debut = 2025 + (self::$rang++ % 20);

        return [
            'libelle'          => $debut . '-' . ($debut + 1),
            'date_debut'       => $debut . '-10-01',
            'date_fin'         => ($debut + 1) . '-09-30',
            // Volontairement inactive par defaut : une seule annee doit etre
            // active a la fois, et c'est au test de designer laquelle.
            'active'           => false,
            'etablissement_id' => Etablissement::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['active' => true]);
    }
}
