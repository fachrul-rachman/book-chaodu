<?php

return [
    'hold_ej_from_88' => env('HOLD_EJ_TABLES_FROM_88', true),
    'show_closed_slots' => env('TABLE_LAYOUT_SHOW_CLOSED_SLOTS', false),
    'hold_codes' => array_values(array_unique(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('HOLD_TABLE_CODES', '')),
    )))),
];
