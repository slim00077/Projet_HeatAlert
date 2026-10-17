<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Optional: with a token the map also offers Mapbox street/satellite layers (OpenStreetMap is always available).
    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
    ],

    'open_meteo' => [
        'url' => env('OPEN_METEO_URL', 'https://api.open-meteo.com/v1/forecast'),
        // Path to a CA bundle when PHP has no curl.cainfo configured (common on Windows).
        'ca_bundle' => env('HTTP_CA_BUNDLE'),
    ],

];
