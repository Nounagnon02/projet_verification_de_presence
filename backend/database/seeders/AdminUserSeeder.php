<?php

namespace Database\Seeders;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin UAC
        User::firstOrCreate(
            ['email' => 'superadmin@uac.bj'],
            [
                'name'       => 'Super Admin UAC',
                'role'       => 'super_admin',
                'group'      => 'admin',
                'password'   => Hash::make('superadmin123'),
            ]
        );

        // Créer l'établissement IFRI s'il n'existe pas
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

        // Admin Faculté (rattaché à IFRI)
        User::firstOrCreate(
            ['email' => 'admin@presence.uac.bj'],
            [
                'name'             => 'Administrateur IFRI',
                'role'             => 'faculte_admin',
                'group'            => 'admin',
                'etablissement_id' => $ifri->id,
                'password'         => Hash::make('admin123'),
            ]
        );

        $this->command->info('Comptes créés :');
        $this->command->info('  Super Admin : superadmin@uac.bj / superadmin123');
        $this->command->info('  Admin IFRI : admin@presence.uac.bj / admin123');
    }
}
