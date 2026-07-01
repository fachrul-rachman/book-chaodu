<?php

return [
    'private_upload_disk' => env('BOOKING_PRIVATE_DISK', 'local'),
    'max_attendee_count' => (int) env('BOOKING_MAX_ATTENDEE_COUNT', 20),
    'upload_max_mb' => (int) env('BOOKING_UPLOAD_MAX_MB', 5),
    'submit_rate_limit_max_attempts' => (int) env('BOOKING_SUBMIT_RATE_LIMIT_MAX_ATTEMPTS', 6),
    'submit_rate_limit_decay_seconds' => (int) env('BOOKING_SUBMIT_RATE_LIMIT_DECAY_SECONDS', 60),
    'virtual_account_hold_minutes' => (int) env('VIRTUAL_ACCOUNT_HOLD_MINUTES', 60),
    'virtual_account_rate_limit_max_attempts' => (int) env('VIRTUAL_ACCOUNT_RATE_LIMIT_MAX_ATTEMPTS', 30),
    'virtual_account_rate_limit_decay_seconds' => (int) env('VIRTUAL_ACCOUNT_RATE_LIMIT_DECAY_SECONDS', 60),
    'captcha' => [
        'enabled' => (bool) env('CAPTCHA_ENABLED', false),
        'site_key' => env('CAPTCHA_SITE_KEY'),
        'secret_key' => env('CAPTCHA_SECRET_KEY'),
        'verify_url' => env('CAPTCHA_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],
];
