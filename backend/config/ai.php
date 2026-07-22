<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration des providers d'analyse IA
    |--------------------------------------------------------------------------
    |
    | Permet de choisir le provider par défaut et de configurer les clés API
    | pour chaque provider supporté.
    |
    | Providers disponibles : gemini, groq, openrouter
    |
    | Utilisation dans .env :
    |   AI_PROVIDER=groq
    |   GEMINI_API_KEY=...
    |   GROQ_API_KEY=...
    |   OPENROUTER_API_KEY=...
    |
    */

    'default' => env('AI_PROVIDER', 'gemini'),

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
        ],
    ],
];
