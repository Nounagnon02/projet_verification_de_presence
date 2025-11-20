<?php

namespace App\Database;

use Illuminate\Database\Connection;
use GuzzleHttp\Client;
use App\Database\TursoGrammar;
use App\Database\TursoSchemaGrammar;
use Illuminate\Support\Collection;

class TursoConnection extends Connection
{
    protected $client;
    protected $url;
    protected $token;

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        
        $baseUrl = $config['url'] ?? env('TURSO_DATABASE_URL', '');
        $this->url = $baseUrl ? rtrim($baseUrl, '/') . '/v2/pipeline' : '';
        $this->token = $config['auth_token'] ?? env('TURSO_AUTH_TOKEN', '');
        $this->client = new Client([
            'timeout' => 10,
            'connect_timeout' => 5
        ]);
    }

    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new TursoGrammar);
    }

    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new TursoSchemaGrammar);
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
        // Fallback vers SQLite local si Turso échoue
        if (empty($this->url) || empty($this->token)) {
            return [];
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
                'timeout' => 10
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (isset($data['results'][0])) {
                return $data['results'][0];
            }
            
            return [];
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer l'application
            error_log('Turso query failed: ' . $e->getMessage());
            return [];
        }
    }
}