<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pengaturan ukuran tulisan kertas
    |--------------------------------------------------------------------------
    |
    | Angka "font_scale" adalah pengali ukuran akhir.
    |
    | Rumus sederhananya:
    | ukuran_akhir = ukuran_dasar x font_scale
    |
    | Contoh:
    | - 1.00 = ukuran normal
    | - 0.90 = 10% lebih kecil
    | - 1.10 = 10% lebih besar
    |
    | Kalau tulisan terlalu rapat:
    | - besarkan "line_height"
    |
    | Kalau tulisan Mandarin vertikal terlalu rapat antar kolom:
    | - besarkan "column_gap_scale"
    |
    */
    'prayer' => [
        'vertical' => [
            'font_scale' => 1.00,
            'line_height' => 1.38,
            'column_gap_scale' => 0.72,
        ],
        'rotated' => [
            'font_scale' => 1.00,
        ],
    ],
    'incense' => [
        'vertical' => [
            'font_scale' => 1.00,
            'line_height' => 1.38,
            'column_gap_scale' => 0.72,
        ],
        'horizontal' => [
            'font_scale' => 1.00,
            'line_height' => 1.28,
        ],
    ],
];
