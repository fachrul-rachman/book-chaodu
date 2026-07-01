<?php

return [
    'private_upload_disk' => env('BOOKING_PRIVATE_DISK', 'local'),
    'ocr_upload_max_mb' => (int) env('OCR_UPLOAD_MAX_MB', 5),
    'ocr_timeout_seconds' => (int) env('OCR_TIMEOUT_SECONDS', 15),
    'ocr_retry_times' => (int) env('OCR_RETRY_TIMES', 1),
    'ocr_endpoint' => env('TWO_OCR_ENDPOINT', '/v1/api/documents/extract'),
    'ocr_type' => env('TWO_OCR_TYPE', 'ocr'),
    'ocr_lang' => env('TWO_OCR_LANG', 'chi'),
    'ocr_retain' => filter_var(env('TWO_OCR_RETAIN', false), FILTER_VALIDATE_BOOL),
    'ocr_rate_limit_max_attempts' => (int) env('OCR_RATE_LIMIT_MAX_ATTEMPTS', 10),
    'ocr_rate_limit_decay_seconds' => (int) env('OCR_RATE_LIMIT_DECAY_SECONDS', 60),
    'preview' => [
        'prayer' => [
            'title' => 'Kertas Doa',
            'top_label' => 'Contoh doa',
            'bottom_label' => 'Periksa kembali tulisan nama.',
        ],
        'incense' => [
            'title' => 'Kertas Hio',
            'top_label' => 'Contoh hio',
            'bottom_label' => 'Periksa kembali tulisan nama.',
        ],
    ],
];
