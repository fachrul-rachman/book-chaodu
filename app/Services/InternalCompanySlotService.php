<?php

namespace App\Services;

use App\Models\Booking;

class InternalCompanySlotService
{
    /**
     * @return array<int, string>
     */
    public function tableCodes(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            config('internal_company.table_codes', []),
        )));
    }

    /**
     * @return array<int, int>
     */
    public function incenseNumbers(): array
    {
        return array_values(array_map(
            static fn (mixed $value): int => (int) $value,
            config('internal_company.incense_numbers', []),
        ));
    }

    public function sourceValue(): string
    {
        return (string) config('internal_company.source_value', 'INTERNAL_PERUSAHAAN');
    }

    public function sourceLabel(): string
    {
        return (string) config('internal_company.source_label', 'Internal Perusahaan');
    }

    public function isInternalBooking(?Booking $booking): bool
    {
        return $booking?->referral_source === $this->sourceValue();
    }
}
