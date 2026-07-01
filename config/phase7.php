<?php

return [
    'storage_disk' => env('APPROVAL_FILE_DISK', env('BOOKING_PRIVATE_DISK', 'local')),
    'qr_size' => env('APPROVAL_QR_SIZE', 360),
    'google' => [
        'service_account_json_path' => env('GOOGLE_SERVICE_ACCOUNT_JSON_PATH'),
        'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
        'base_url' => env('GOOGLE_API_BASE_URL', 'https://www.googleapis.com'),
        'timeout_seconds' => env('GOOGLE_API_TIMEOUT_SECONDS', 20),
        'share_anyone_with_link' => (bool) env('GOOGLE_DRIVE_SHARE_ANYONE_WITH_LINK', true),
    ],
    'notion' => [
        'api_token' => env('NOTION_API_TOKEN'),
        'parent_id' => env('NOTION_PARENT_ID'),
        'parent_type' => env('NOTION_PARENT_TYPE', 'page_id'),
        'version' => env('NOTION_VERSION', '2022-06-28'),
        'base_url' => env('NOTION_BASE_URL', 'https://api.notion.com/v1'),
        'timeout_seconds' => env('NOTION_TIMEOUT_SECONDS', 20),
    ],
    'email' => [
        'timeout_seconds' => env('BREVO_TIMEOUT_SECONDS', 20),
    ],
];
