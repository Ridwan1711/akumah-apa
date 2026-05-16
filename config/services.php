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

    'ppdb_sync' => [
        'api_key' => env('PPDB_SYNC_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'wa' => [
        'enabled' => (bool) env('WA_ENABLED', false),
        'url' => env('WA_API_URL', 'https://api-wa.ngedeploy.online/send-message'),
        'key' => env('WA_API_KEY'),
        'fallback_phone' => env('WA_FALLBACK_PHONE', '6289502887544'),
        'allow_fallback' => (bool) env('WA_ALLOW_FALLBACK_RECIPIENT', false),
        'bulk_delay_seconds' => (int) env('WA_BULK_DELAY_SECONDS', 12),
        'queue' => env('WA_QUEUE', 'wa'),
        'timeout_seconds' => (int) env('WA_HTTP_TIMEOUT', 30),
    ],

];
