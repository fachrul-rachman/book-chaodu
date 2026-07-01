<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Checker = 'CHECKER';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Checker => 'Checker',
        };
    }
}
