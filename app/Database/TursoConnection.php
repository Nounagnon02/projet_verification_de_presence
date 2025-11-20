<?php

namespace App\Database;

use Illuminate\Database\Connection;
use GuzzleHttp\Client;
use App\Database\TursoGrammar;
use App\Database\TursoSchemaGrammar;

class TursoConnection extends Connection
{
    protected $client;
    protected $url;
    protected $token;

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        
        $this->url = rtrim($config['url'] ?? env('TURSO_DATABASE_URL', ''), '/') . '/v2/pipeline';
        $this->token = $config['auth_token'] ?? env('TURSO_AUTH_TOKEN', '');
        $this->client = new Client();
    }

    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new TursoGrammar);
    }

    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new TursoSchemaGrammar);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $result = $this->executeQuery($query, $bindings);
        return $result['rows'] ?? [];
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
                                'args' => $bindings
                            ]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['results'][0] ?? [];
        } catch (\Exception $e) {
            throw new \Exception('Turso query failed: ' . $e->getMessage());
        }
    }
}