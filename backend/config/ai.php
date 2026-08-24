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

    /*
    |--------------------------------------------------------------------------
    | Modèles
    |--------------------------------------------------------------------------
    |
    | Le nom du modèle était codé en dur dans chaque provider. Conséquence
    | constatée le 2026-08-24 : « gemini-2.0-flash » a été retiré par Google, qui
    | répond 404 « no longer available ». TOUTE analyse de document échouait donc,
    | sans qu'aucun test ne le voie — le seul test d'import simule le provider.
    |
    | Un modèle a une durée de vie de quelques mois : le nom appartient à la
    | configuration, pas au code.
    |
    */

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model'   => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            // « mixtral-8x7b-32768 » a été retiré du catalogue Groq.
            'model'   => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            // Les identifiants « -preview- » sont éphémères par construction.
            'model'   => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'),
        ],
    ],
];
