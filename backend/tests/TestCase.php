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

        $this->guardAgainstProductionDatabase();

        // Désactiver le statement_timeout de Supabase (trop bas pour les tests)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SET statement_timeout TO 0');
        }

        // Exécuter les migrations une seule fois pour tous les tests
        $lockFile = sys_get_temp_dir() . '/uac_migrations_run_lock';
        if (!file_exists($lockFile)) {
            $this->artisan('migrate', ['--force' => true]);
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
        }
    }

    /**
     * Refuse de lancer la suite contre la base applicative.
     *
     * phpunit.xml n'impose aucune base dédiée : sans ce garde, les tests
     * tournent sur la base déclarée dans .env — c'est-à-dire la production —
     * et setUp() y exécute `migrate --force`. Seul DatabaseTransactions
     * empêche les données de test d'y être écrites, ce qui est une
     * protection bien trop mince.
     *
     * Pour lancer les tests :
     *   - copier .env.testing.example vers .env.testing et y pointer une
     *     base dédiée (Laravel charge .env.testing automatiquement) ;
     *   - ou, en dépannage uniquement, exporter ALLOW_TESTS_ON_PROD_DB=true.
     */
    private function guardAgainstProductionDatabase(): void
    {
        if (filter_var(env('ALLOW_TESTS_ON_PROD_DB', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $actuelle = $this->databaseFingerprint(
            config('database.connections.' . config('database.default'))
        );

        if ($actuelle === '') {
            return;
        }

        $envApplicatif = base_path('.env');

        if (is_readable($envApplicatif)
            && $this->databaseFingerprint($this->parseEnvDatabase($envApplicatif)) === $actuelle) {
            $this->fail(
                "Les tests pointent sur la base applicative ({$actuelle}).\n"
                . "Configurez une base de test dédiée dans .env.testing "
                . "(voir .env.testing.example), ou exportez "
                . "ALLOW_TESTS_ON_PROD_DB=true pour passer outre."
            );
        }
    }

    /**
     * Réduit une configuration de connexion à une empreinte comparable.
     *
     * @param  array<string, mixed>|null  $config
     */
    private function databaseFingerprint(?array $config): string
    {
        if (!$config) {
            return '';
        }

        if (!empty($config['url'])) {
            $parts = parse_url($config['url']);
            return strtolower(($parts['host'] ?? '') . '/' . ltrim($parts['path'] ?? '', '/'));
        }

        if (empty($config['host'])) {
            return '';
        }

        return strtolower($config['host'] . '/' . ($config['database'] ?? ''));
    }

    /**
     * Extrait la configuration base de données d'un fichier .env, sans
     * dépendre de l'environnement déjà chargé.
     *
     * @return array<string, mixed>|null
     */
    private function parseEnvDatabase(string $fichier): ?array
    {
        $valeurs = [];

        foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
                continue;
            }

            [$cle, $valeur] = explode('=', $ligne, 2);
            $valeurs[trim($cle)] = trim($valeur, " \t\"'");
        }

        if (!isset($valeurs['DB_URL']) && !isset($valeurs['DB_HOST'])) {
            return null;
        }

        return [
            'url'      => $valeurs['DB_URL']      ?? null,
            'host'     => $valeurs['DB_HOST']     ?? null,
            'database' => $valeurs['DB_DATABASE'] ?? null,
        ];
    }
}
