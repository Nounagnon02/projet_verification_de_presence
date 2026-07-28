<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS', '')))) ?: [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:3000',
        'https://presence-uac.vercel.app',
        'https://presence.uac.bj',
    ],

    'allowed_origins_patterns' => [],

    // En-têtes réellement envoyés par la SPA (Authorization, Content-Type,
    // Accept) plus ceux qu'utilise Sanctum. Le mobile passe par des Bearer
    // tokens hors navigateur : il n'est pas soumis au CORS.
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    // Met en cache le pre-flight OPTIONS 24 h pour éviter un aller-retour
    // supplémentaire avant chaque requête cross-origin.
    'max_age' => 86400,

    'supports_credentials' => true,

];
