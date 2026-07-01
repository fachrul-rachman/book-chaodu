<?php

return [
    'enabled' => filter_var(env('PRAYER_PAPER_ENABLED', true), FILTER_VALIDATE_BOOL),
    'storage_disk' => env('PRAYER_PAPER_STORAGE_DISK', 'r2'),
    'templates' => [
        'A' => [
            'width' => 900,
            'height' => 1400,
            'background' => '#fff7e6',
            'border' => '#d7c4a3',
            'title' => 'Kertas Doa',
            'title_x' => 450,
            'title_y' => 150,
            'names' => [
                ['x' => 450, 'y' => 620, 'font_size' => 74],
                ['x' => 450, 'y' => 760, 'font_size' => 74],
            ],
            'footer_x' => 450,
            'footer_y' => 1270,
            'footer' => 'File final dibuat otomatis',
        ],
        'B' => [
            'width' => 900,
            'height' => 1400,
            'background' => '#fffaf0',
            'border' => '#cba970',
            'title' => 'Kertas Hio',
            'title_x' => 450,
            'title_y' => 180,
            'names' => [
                ['x' => 450, 'y' => 700, 'font_size' => 86],
            ],
            'footer_x' => 450,
            'footer_y' => 1270,
            'footer' => 'File final dibuat otomatis',
        ],
    ],
];
