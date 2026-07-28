<?php

namespace Database\Seeders;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Crée les comptes d'administration initiaux.
     *
     * Les mots de passe ne sont JAMAIS codés en dur : ils proviennent des
     * variables d'environnement SEED_SUPERADMIN_PASSWORD et
     * SEED_ADMIN_PASSWORD. À défaut, un mot de passe aléatoire est généré —
     * le compte existe alors mais devra être réinitialisé via « mot de passe
     * oublié », plutôt que d'exposer un identifiant par défaut connu.
     *
     * Ce seeder tourne à chaque démarrage (start.sh) : `firstOrCreate` ne
     * touche donc PAS au mot de passe d'un compte déjà présent. Changer le
     * mot de passe en production se fait dans l'application, pas ici.
     */
    public function run(): void
    {
        $this->createUser(
            email: 'superadmin@uac.bj',
            attributes: [
                'name'  => 'Super Admin UAC',
                'role'  => 'super_admin',
                'group' => 'admin',
            ],
            passwordEnvKey: 'SEED_SUPERADMIN_PASSWORD',
        );

        // Établissement IFRI (référencé par l'admin faculté ci-dessous).
        $ifri = Etablissement::firstOrCreate(
            ['code' => 'IFRI'],
            [
                'nom'       => 'Institut de Formation et de Recherche en Informatique',
                'email'     => 'contact@ifri.uac.bj',
                'telephone' => '+229 01 23 45 67',
                'adresse'   => 'Abomey-Calavi, Bénin',
                'actif'     => true,
            ]
        );

        $this->createUser(
            email: 'admin@presence.uac.bj',
            attributes: [
                'name'             => 'Administrateur IFRI',
                'role'             => 'faculte_admin',
                'group'            => 'admin',
                'etablissement_id' => $ifri->id,
            ],
            passwordEnvKey: 'SEED_ADMIN_PASSWORD',
        );
    }

    /**
     * Crée le compte s'il n'existe pas, sans jamais réécrire ni logger le
     * mot de passe d'un compte existant.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $email, array $attributes, string $passwordEnvKey): void
    {
        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = (string) env($passwordEnvKey, '');
        $genere = $password === '';

        if ($genere) {
            $password = Str::password(20);
        }

        User::create($attributes + [
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        if ($genere) {
            $this->command?->warn(
                "Compte {$email} créé avec un mot de passe aléatoire "
                . "({$passwordEnvKey} non définie). Utilisez « mot de passe oublié » "
                . "pour en définir un."
            );
        } else {
            $this->command?->info("Compte {$email} créé (mot de passe depuis {$passwordEnvKey}).");
        }
    }
}
