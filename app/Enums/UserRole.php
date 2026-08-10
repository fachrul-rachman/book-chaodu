<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Checker = 'CHECKER';
    case Printer = 'PRINTER';
    case ContentTeam = 'CONTENT_TEAM';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Checker => 'Checker',
            self::Printer => 'Petugas Print',
            self::ContentTeam => 'Team Content',
        };
    }
}
