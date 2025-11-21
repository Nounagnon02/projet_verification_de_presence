<?php

namespace App\Database;

use Illuminate\Database\Connection;
use GuzzleHttp\Client;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar as SQLiteSchemaGrammar;
use Illuminate\Support\Collection;
use PDO;

class TursoConnection extends Connection
{
    protected $client;
    protected $url;
    protected $token;
    protected $fallbackToSqlite = false;

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        // Create a dummy PDO for SQLite fallback
        if (!$pdo) {
            try {
                $pdo = new PDO('sqlite::memory:');
            } catch (\Exception $e) {
                $pdo = null;
            }
        }
        
        parent::__construct($pdo, $database, $tablePrefix, $config);
        
        $baseUrl = $config['url'] ?? env('TURSO_DATABASE_URL', '');
        // Convert libsql:// to https:// for HTTP API
        if (str_starts_with($baseUrl, 'libsql://')) {
            $baseUrl = str_replace('libsql://', 'https://', $baseUrl);
        }
        $this->url = $baseUrl ? rtrim($baseUrl, '/') . '/v2/pipeline' : '';
        $this->token = $config['auth_token'] ?? env('TURSO_AUTH_TOKEN', '');
        
        if (empty($this->url) || empty($this->token)) {
            $this->fallbackToSqlite = true;
        }
        
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
    }

    protected function getDefaultQueryGrammar()
    {
        $grammar = new SQLiteGrammar($this);
        $grammar->setTablePrefix($this->getTablePrefix());
        return $grammar;
    }

    protected function getDefaultSchemaGrammar()
    {
        $grammar = new SQLiteSchemaGrammar($this);
        $grammar->setTablePrefix($this->getTablePrefix());
        return $grammar;
    }

    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new \Illuminate\Database\Schema\SQLiteBuilder($this);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $result = $this->executeQuery($query, $bindings);
        
        // Convertir le format Turso vers le format Laravel attendu
        if (isset($result['rows'])) {
            return collect($result['rows'])->map(function ($row) {
                return (object) $row;
            })->toArray();
        }
        
        return [];
    }

    public function insert($query, $bindings = [])
    {
        return $this->executeQuery($query, $bindings);
    }

    public function update($query, $bindings = [])
    {
        return $this->executeQuery($query, $bindings);
    }

    public function delete($query, $bindings = [])
    {
        return $this->executeQuery($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        return $this->executeQuery($query, $bindings);
    }

    protected function executeQuery($query, $bindings = [])
    {
        // Use SQLite fallback if Turso is not configured
        if ($this->fallbackToSqlite || empty($this->url) || empty($this->token)) {
            return $this->executeSqliteQuery($query, $bindings);
        }

        try {
            $response = $this->client->post($this->url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'requests' => [
                        [
                            'type' => 'execute',
                            'stmt' => [
                                'sql' => $query,
                                'args' => array_values($bindings)
                            ]
                        ]
                    ]
                ],
                'timeout' => 30
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (isset($data['results'][0])) {
                return $data['results'][0];
            }
            
            return [];
        } catch (\Exception $e) {
            error_log('Turso query failed: ' . $e->getMessage());
            // Fallback to SQLite on error
            return $this->executeSqliteQuery($query, $bindings);
        }
    }

    protected function executeSqliteQuery($query, $bindings = [])
    {
        if (!$this->getPdo()) {
            return [];
        }
        
        try {
            $statement = $this->getPdo()->prepare($query);
            $statement->execute($bindings);
            
            if (stripos($query, 'SELECT') === 0) {
                return $statement->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return ['affected_rows' => $statement->rowCount()];
        } catch (\Exception $e) {
            error_log('SQLite query failed: ' . $e->getMessage());
            return [];
        }
    }
}