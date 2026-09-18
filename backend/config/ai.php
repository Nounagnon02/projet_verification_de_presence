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

    /*
    |--------------------------------------------------------------------------
    | Réseau et relance
    |--------------------------------------------------------------------------
    |
    | timeout, max_tokens et temperature étaient codés en dur dans chaque
    | provider ; max_retries n'existait que pour Gemini (seul à retenter un
    | 429 ou un 5xx), Groq et OpenRouter abandonnant à la première erreur
    | transitoire. Gemini reçoit le PDF entier et le déchiffre lui-même
    | (pages scannées comprises), d'où un budget de temps et de relances plus
    | large que Groq/OpenRouter, qui ne reçoivent que du texte déjà extrait.
    |
    */

    'providers' => [
        'gemini' => [
            'api_key'     => env('GEMINI_API_KEY'),
            'model'       => env('GEMINI_MODEL', 'gemini-3.6-flash'),
            'timeout'     => (int) env('GEMINI_TIMEOUT', 180),
            'max_retries' => (int) env('GEMINI_MAX_RETRIES', 3),
        ],

        'groq' => [
            'api_key'     => env('GROQ_API_KEY'),
            // « mixtral-8x7b-32768 » a été retiré du catalogue Groq.
            'model'       => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'timeout'     => (int) env('GROQ_TIMEOUT', 120),
            'max_retries' => (int) env('GROQ_MAX_RETRIES', 2),
            'max_tokens'  => (int) env('GROQ_MAX_TOKENS', 4096),
            'temperature' => (float) env('GROQ_TEMPERATURE', 0.1),
        ],

        'openrouter' => [
            'api_key'     => env('OPENROUTER_API_KEY'),
            // Les identifiants « -preview- » sont éphémères par construction.
            'model'       => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'),
            'timeout'     => (int) env('OPENROUTER_TIMEOUT', 120),
            'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 2),
            'max_tokens'  => (int) env('OPENROUTER_MAX_TOKENS', 4096),
            'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.1),
        ],
    ],
];
