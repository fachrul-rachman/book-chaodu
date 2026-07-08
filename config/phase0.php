<?php

return [
    'default_users' => [
        'admin' => [
            'name' => env('DEFAULT_ADMIN_NAME', 'Admin Chao Du'),
            'email' => env('DEFAULT_ADMIN_EMAIL', 'admin@x.com'),
            'password' => env('DEFAULT_ADMIN_PASSWORD', 'password'),
        ],
        'checker' => [
            'name' => env('DEFAULT_CHECKER_NAME', 'Checker Chao Du'),
            'email' => env('DEFAULT_CHECKER_EMAIL', 'checker@x.com'),
            'password' => env('DEFAULT_CHECKER_PASSWORD', 'password'),
        ],
        'printer' => [
            'name' => env('DEFAULT_PRINTER_NAME', 'Petugas Print Chao Du'),
            'email' => env('DEFAULT_PRINTER_EMAIL', 'printer@x.com'),
            'password' => env('DEFAULT_PRINTER_PASSWORD', 'password'),
        ],
    ],
];
