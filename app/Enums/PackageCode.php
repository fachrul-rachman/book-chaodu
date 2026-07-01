<?php

namespace App\Enums;

enum PackageCode: string
{
    case Prayer = 'PRAYER';
    case Incense = 'INCENSE';
    case Combo = 'COMBO';

    public function label(): string
    {
        return match ($this) {
            self::Prayer => 'Sembahyang',
            self::Incense => 'Hio',
            self::Combo => 'Combo',
        };
    }
}
