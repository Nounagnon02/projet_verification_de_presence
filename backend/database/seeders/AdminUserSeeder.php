<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@presence.uac.bj'],
            [
                'name'     => 'Administrateur',
                'group'    => 'admin',
                'password' => Hash::make('admin123'),
            ]
        );

        $this->command->info('Compte admin créé : admin@presence.uac.bj / admin123');
    }
}
