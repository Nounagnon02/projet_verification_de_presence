<?php

// Helper pour créer le fichier de credentials depuis une variable d'environnement (pour Render/Heroku)
$credentialsPath = storage_path('app/google-calendar/service-account-credentials.json');

if (!file_exists($credentialsPath) && !app()->runningInConsole()) {
    $credentialsJson = env('GOOGLE_CALENDAR_CREDENTIALS_JSON');
    if ($credentialsJson) {
        if (!is_dir(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0755, true);
        }
        file_put_contents($credentialsPath, $credentialsJson);
    }
}

return [

    'default_auth_profile' => env('GOOGLE_CALENDAR_AUTH_PROFILE', 'service_account'),

    'auth_profiles' => [

        /*
         * Authenticate using a service account.
         */
        'service_account' => [
            /*
             * Path to the json file containing the credentials.
             */
            'credentials_json' => $credentialsPath,
        ],

        /*
         * Authenticate with actual google user account.
         */
        'oauth' => [
            /*
             * Path to the json file containing the oauth2 credentials.
             */
            'credentials_json' => storage_path('app/google-calendar/oauth-credentials.json'),

            /*
             * Path to the json file containing the oauth2 token.
             */
            'token_json' => storage_path('app/google-calendar/oauth-token.json'),
        ],
    ],

    /*
     *  The id of the Google Calendar that will be used by default.
     */
    'calendar_id' => env('GOOGLE_CALENDAR_ID'),

     /*
     *  The email address of the user account to impersonate.
     */
    'user_to_impersonate' => env('GOOGLE_CALENDAR_IMPERSONATE'),
];
