<?php

namespace Database\Seeders;

use App\Services\BadgeService;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BadgeService::createDefaultBadges();
    }
}
