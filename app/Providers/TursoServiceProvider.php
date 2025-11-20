<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\DatabaseManager;
use App\Database\TursoConnection;

class TursoServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('turso', function ($config, $name) {
                $pdo = new \PDO('sqlite::memory:');
                return new TursoConnection($pdo, $config['database'] ?? '', $config['prefix'] ?? '', $config);
            });
        });
    }

    public function boot()
    {
        //
    }
}