<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseQuery extends Command
{
    protected $signature = 'db:query {query?}';
    protected $description = 'Execute SQL queries on the database';

    public function handle()
    {
        $query = $this->argument('query');

        if (!$query) {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $this->info('Available tables:');
            foreach ($tables as $table) {
                $count = DB::table($table->name)->count();
                $this->line("- {$table->name} ({$count} records)");
            }
            return;
        }

        try {
            $results = DB::select($query);
            $this->table(
                array_keys((array) $results[0] ?? []),
                array_map(fn($row) => (array) $row, $results)
            );
        } catch (\Exception $e) {
            $this->error('Query failed: ' . $e->getMessage());
        }
    }

    
}
