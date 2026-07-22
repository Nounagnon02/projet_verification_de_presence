<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Désactiver le statement_timeout de Supabase (trop bas pour les tests)
        DB::statement('SET statement_timeout TO 0');

        // Exécuter les migrations une seule fois pour tous les tests
        $lockFile = sys_get_temp_dir() . '/uac_migrations_run_lock';
        if (!file_exists($lockFile)) {
            $this->artisan('migrate', ['--force' => true]);
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
        }
    }
}
