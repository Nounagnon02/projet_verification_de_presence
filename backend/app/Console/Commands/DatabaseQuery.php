<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseQuery extends Command
{
    protected $signature = 'db:query {query?}';
    protected $description = 'Execute read-only SQL queries for diagnostics. Refuses destructive operations.';

    public function handle()
    {
        // Interdire en production
        if (app()->environment('production')) {
            $this->error('Cette commande est désactivée en environnement de production.');
            return 1;
        }

        $query = $this->argument('query');

        if (!$query) {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $this->info('Tables disponibles :');
            foreach ($tables as $table) {
                $count = DB::table($table->name)->count();
                $this->line("- {$table->name} ({$count} enregistrements)");
            }
            return 0;
        }

        // Bloquer les requêtes destructives
        $normalized = trim(mb_strtoupper($query));
        $forbidden = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'TRUNCATE', 'CREATE', 'REPLACE'];

        foreach ($forbidden as $keyword) {
            if (str_starts_with($normalized, $keyword)) {
                $this->error("Requête '{$keyword}' interdite. Seules les requêtes SELECT sont autorisées.");
                return 1;
            }
        }

        if (!str_starts_with($normalized, 'SELECT')) {
            $this->error('Seules les requêtes SELECT sont autorisées.');
            return 1;
        }

        try {
            $results = DB::select($query);

            if (empty($results)) {
                $this->info('Aucun résultat.');
                return 0;
            }

            $this->table(
                array_keys((array) $results[0]),
                array_map(fn($row) => (array) $row, $results)
            );

            $this->info(count($results) . ' résultat(s).');
        } catch (\Exception $e) {
            $this->error('Échec de la requête : ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
