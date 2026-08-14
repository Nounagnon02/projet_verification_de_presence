<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'group' => 'admin',
            // Rôle explicite. La colonne `role` vaut « faculte_admin » par défaut
            // en base, et un admin de faculté sans établissement est refusé en 403
            // par le middleware de cloisonnement — à raison, il est fail-closed.
            // La factory produisait donc un compte inutilisable, ce qui faisait
            // échouer la quasi-totalité des tests d'administration.
            //
            // Le super administrateur n'est pas filtré : c'est le seul rôle
            // utilisable sans rattachement préalable à une entité. Pour tester le
            // cloisonnement lui-même, utiliser l'état faculteAdmin().
            'role' => 'super_admin',
            'must_change_password' => false,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Administrateur d'une entité donnée, soumis au cloisonnement.
     */
    public function faculteAdmin(int|string $etablissementId): static
    {
        return $this->state(fn (array $attributes) => [
            'role'             => 'faculte_admin',
            'etablissement_id' => $etablissementId,
        ]);
    }

    /**
     * Compte dont le mot de passe temporaire n'a pas encore été changé : tout
     * accès doit être bloqué sauf la route de changement de mot de passe.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
