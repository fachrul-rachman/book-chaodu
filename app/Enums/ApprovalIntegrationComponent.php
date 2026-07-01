<?php

namespace App\Enums;

enum ApprovalIntegrationComponent: string
{
    case Qr = 'qr';
    case Drive = 'drive';
    case Notion = 'notion';
    case ApprovalEmail = 'approval_email';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'QR',
            self::Drive => 'Google Drive',
            self::Notion => 'Notion',
            self::ApprovalEmail => 'Email approval',
        };
    }
}
