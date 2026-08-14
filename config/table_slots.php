<?php

return [
    'hold_ej_from_88' => env('HOLD_EJ_TABLES_FROM_88', true),
    'show_closed_slots' => env('TABLE_LAYOUT_SHOW_CLOSED_SLOTS', false),
    'background_label' => 'BACKGROUND',
    'extra_columns' => max(0, min(5, (int) env('EXTRA_TABLE_COLUMNS', 0))),
    'extra_rows' => ['A', 'F', 'B', 'G', 'D', 'H'],
    'hold_codes' => array_values(array_unique(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('HOLD_TABLE_CODES', '')),
    )))),
];
