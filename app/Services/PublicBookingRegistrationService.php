<?php

namespace App\Services;

use App\Models\AppSetting;

class PublicBookingRegistrationService
{
    private const SETTING_KEY = 'public_booking_closed';

    public function isClosed(): bool
    {
        return AppSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value') === '1';
    }

    public function setClosed(bool $closed): void
    {
        AppSetting::putMany([
            self::SETTING_KEY => $closed ? '1' : '0',
        ]);
    }
}
