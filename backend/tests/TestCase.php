<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /*
    |--------------------------------------------------------------------------
    | Données de référence
    |--------------------------------------------------------------------------
    |
    | Plusieurs tests ont besoin d'une année académique ou d'une filière sans que
    | ce soit l'objet de leur vérification. Ils les récupéraient auparavant avec
    | un firstOrFail() en supposant la base semée, ce qui les rendait dépendants
    | d'un état extérieur : sur une base fraîche, ils échouaient tous.
    |
    | Ces accesseurs récupèrent l'enregistrement s'il existe et le créent sinon.
    | Les tests fonctionnent donc sur une base vide comme sur une base semée, et
    | sans jamais provoquer de conflit d'unicité — ce que ferait un semis
    | systématique, les tests créant par ailleurs leurs propres libellés.
    |
    */

    /**
     * Jeton d'authentification d'un étudiant, capacité « etudiant ».
     *
     * Le scan de présence exige désormais ce jeton (voir
     * App\Services\Presence\DemandeDeScan) : il a remplacé le couple
     * identifiant_unique + scan_challenge posté dans le corps de la requête,
     * qu'un camarade de promotion pouvait reconstituer sans authentification.
     */
    protected function jetonDeScan(\App\Models\Etudiant $etudiant): string
    {
        // Le guard Sanctum mémorise le premier utilisateur qu'il résout et le
        // ressert tant qu'on ne l'a pas oublié — même si la requête suivante
        // porte un jeton Bearer différent. Un scan qui enchaîne plusieurs
        // étudiants dans le même test authentifiait donc TOUS ses appels
        // comme le premier. Même précaution que ScansRefusesTest::enTantQue().
        $this->app['auth']->forgetGuards();

        return $etudiant->createToken('test', ['etudiant'])->plainTextToken;
    }

    protected function anneeActive(): \App\Models\AnneeAcademique
    {
        return \App\Models\AnneeAcademique::where('active', true)->first()
            ?? \App\Models\AnneeAcademique::create([
                'libelle'    => '2025-2026',
                'date_debut' => '2025-10-01',
                'date_fin'   => '2026-09-30',
                'active'     => true,
            ]);
    }

    protected function anneeNonActive(): \App\Models\AnneeAcademique
    {
        return \App\Models\AnneeAcademique::where('active', false)->first()
            ?? \App\Models\AnneeAcademique::create([
                'libelle'    => '2024-2025',
                'date_debut' => '2024-10-01',
                'date_fin'   => '2025-09-30',
                'active'     => false,
            ]);
    }

    protected function uneFiliere(): \App\Models\Filiere
    {
        return \App\Models\Filiere::first()
            ?? \App\Models\Filiere::create([
                'code'     => 'REF-' . \Illuminate\Support\Str::random(5),
                'intitule' => 'Filière de référence',
                'niveau'   => 'L1',
            ]);
    }

    protected function uneEntite(): \App\Models\Etablissement
    {
        $sfx = \Illuminate\Support\Str::random(6);

        return \App\Models\Etablissement::first()
            ?? \App\Models\Etablissement::create([
                'code'  => 'REF-' . $sfx,
                'nom'   => 'Entité de référence',
                // L'email est obligatoire et unique sur cette table.
                'email' => strtolower("ref.{$sfx}@test.local"),
            ]);
    }

    protected function uneUe(): \App\Models\Ue
    {
        $existante = \App\Models\Ue::whereNotNull('filiere_id')->whereNotNull('annee_id')->first();

        if ($existante) {
            return $existante;
        }

        $sfx = \Illuminate\Support\Str::random(5);

        return \App\Models\Ue::create([
            'code'           => 'UE-REF-' . $sfx,
            'intitule'       => 'UE de référence',
            'filiere_id'     => $this->uneFiliere()->id,
            'annee_id'       => $this->anneeActive()->id,
            'semestre'       => 1,
            'volume_horaire' => 30,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProductionDatabase();

        // Désactiver le statement_timeout de Supabase (trop bas pour les tests)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SET statement_timeout TO 0');
        }
    }

    /**
     * Migrations de la base de test, AVANT l'ouverture de la transaction du test.
     *
     * Elles s'exécutaient dans setUp(), donc dans la transaction qu'ouvre
     * DatabaseTransactions. PostgreSQL annule aussi le DDL avec la transaction :
     * la migration disparaissait à la fin du premier test, pendant que le verrou,
     * lui, restait. Les tests suivants tombaient sur des tables absentes — la
     * migration du fuseau horaire n'avait jamais atteint la base de test.
     */
    protected function setUpTraits()
    {
        $this->guardAgainstProductionDatabase();

        // Une seule fois pour toute la suite, mais le verrou est indexé sur
        // l'empreinte du répertoire de migrations : ajouter, renommer ou
        // supprimer une migration change le nom du verrou, donc relance la
        // migration. Un verrou à nom fixe laissait la base sur un schéma périmé.
        $migrations = glob(database_path('migrations/*.php')) ?: [];
        sort($migrations);
        $empreinte = substr(md5(implode('|', array_map('basename', $migrations))), 0, 12);

        $lockFile = sys_get_temp_dir() . '/uac_migrations_' . $empreinte . '.lock';
        if (!file_exists($lockFile)) {
            $this->artisan('migrate', ['--force' => true]);
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
        }

        return parent::setUpTraits();
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

    /**
     * Déclare les périodes des semestres impairs et pairs d'un établissement
     * (null : les filières sans établissement). Sans elles, la génération ne
     * crée aucune séance.
     */
    protected function ouvrirSemestres(\App\Models\AnneeAcademique $annee, ?int $etablissementId = null, string $du = '2000-01-01', string $au = '2099-12-31'): void
    {
        foreach (\App\Models\PeriodeSemestre::PARITES as $parite) {
            \App\Models\PeriodeSemestre::updateOrCreate(
                ['etablissement_id' => $etablissementId, 'annee_id' => $annee->id, 'parite' => $parite],
                ['date_debut' => $du, 'date_fin' => $au]
            );
        }
    }
}
