<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'base_url' => env('BREVO_BASE_URL', 'https://api.brevo.com/v3'),
    ],

    'two_ocr' => [
        'api_key' => env('TWO_OCR_API_KEY'),
        'base_url' => env('TWO_OCR_BASE_URL', 'https://backend.scandocflow.com'),
        'credentials' => array_values(array_filter([
            [
                'label' => 'utama',
                'api_key' => env('TWO_OCR_API_KEY'),
                'base_url' => env('TWO_OCR_BASE_URL', 'https://backend.scandocflow.com'),
            ],
            [
                'label' => 'cadangan_2',
                'api_key' => env('TWO_OCR_API_KEY_2'),
                'base_url' => env('TWO_OCR_BASE_URL_2', env('TWO_OCR_BASE_URL', 'https://backend.scandocflow.com')),
            ],
            [
                'label' => 'cadangan_3',
                'api_key' => env('TWO_OCR_API_KEY_3'),
                'base_url' => env('TWO_OCR_BASE_URL_3', env('TWO_OCR_BASE_URL', 'https://backend.scandocflow.com')),
            ],
        ], static fn (array $credential): bool => trim((string) ($credential['api_key'] ?? '')) !== '')),
    ],

    'discord' => [
        'general_booking_webhook_url' => env('DISCORD_GENERAL_BOOKING_WEBHOOK_URL'),
        'agent_booking_webhook_url' => env('DISCORD_AGENT_BOOKING_WEBHOOK_URL'),
        'username' => env('DISCORD_WEBHOOK_USERNAME', env('APP_NAME')),
        'timeout_seconds' => env('DISCORD_WEBHOOK_TIMEOUT_SECONDS', 5),
        'retry_times' => env('DISCORD_WEBHOOK_RETRY_TIMES', 1),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
