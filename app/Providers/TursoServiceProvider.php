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
                try {
                    $pdo = new \PDO('sqlite::memory:');
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return new TursoConnection($pdo, $config['database'] ?? '', $config['prefix'] ?? '', $config);
                } catch (\Exception $e) {
                    // Fallback vers SQLite fichier si mémoire échoue
                    $pdo = new \PDO('sqlite:/tmp/fallback.sqlite');
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return new TursoConnection($pdo, $config['database'] ?? '', $config['prefix'] ?? '', $config);
                }
            });
        });
    }

    public function boot()
    {
        //
    }
}