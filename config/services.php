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

    // RF01 (ADR-007). Cliente OAuth del proyecto de Google Cloud de la UES; se verifica con `php artisan google:verificar`.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        'allowed_domain' => env('GOOGLE_ALLOWED_DOMAIN', 'ues.edu.sv'),
        // RF21 (ADR-018): eventos de Calendar con el permiso OAuth del docente, con el mismo cliente. Apagado hasta
        // que el cliente tenga habilitada la Calendar API y registrada la URI de redirección.
        'calendar' => [
            'enabled' => (bool) env('GOOGLE_CALENDAR_ENABLED', false),
            'redirect' => env('GOOGLE_CALENDAR_REDIRECT_URI', '/perfil/google-calendar/callback'),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
