<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default SMS gateway that will be used by the
    | framework. You may set this to any of the gateways defined in the
    | "gateways" array below.
    |
    */
    'default' => env('SMS_DEFAULT_GATEWAY', 'log'),

    /*
    |--------------------------------------------------------------------------
    | SMS Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure the SMS gateways for your application. You may
    | use multiple gateways and switch between them as needed.
    |
    */
    'gateways' => [
        'log' => [
            'driver' => 'log',
        ],

        'twilio' => [
            'driver' => 'twilio',
            'sid' => env('SMS_TWILIO_SID'),
            'token' => env('SMS_TWILIO_TOKEN'),
            'from' => env('SMS_TWILIO_FROM'),
        ],

        'nexmo' => [
            'driver' => 'nexmo',
            'key' => env('SMS_NEXMO_KEY'),
            'secret' => env('SMS_NEXMO_SECRET'),
            'from' => env('SMS_NEXMO_FROM'),
        ],

        'africastalking' => [
            'driver' => 'africastalking',
            'username' => env('SMS_AT_USERNAME'),
            'api_key' => env('SMS_AT_API_KEY'),
            'from' => env('SMS_AT_FROM'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Queue
    |--------------------------------------------------------------------------
    |
    | If you want to queue SMS messages, set this to true and make sure
    | you have configured your queue driver properly.
    |
    */
    'queue' => env('SMS_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | SMS Queue Connection
    |--------------------------------------------------------------------------
    |
    | The queue connection to use for queued SMS messages.
    |
    */
    'queue_connection' => env('SMS_QUEUE_CONNECTION', 'default'),
];