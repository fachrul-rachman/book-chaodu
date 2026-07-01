<?php

namespace App\Enums;

enum PrayerPaperType: string
{
    case A = 'A';
    case B = 'B';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Kertas A',
            self::B => 'Kertas B',
        };
    }
}
