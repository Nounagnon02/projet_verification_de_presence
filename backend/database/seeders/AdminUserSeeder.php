<?php

namespace Database\Seeders;

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

        // Admin Faculté (pour les tests)
        User::firstOrCreate(
            ['email' => 'admin@presence.uac.bj'],
            [
                'name'       => 'Administrateur Faculté',
                'role'       => 'faculte_admin',
                'group'      => 'admin',
                'password'   => Hash::make('admin123'),
            ]
        );

        $this->command->info('Comptes créés :');
        $this->command->info('  Super Admin : superadmin@uac.bj / superadmin123');
        $this->command->info('  Admin Faculté : admin@presence.uac.bj / admin123');
    }
}
