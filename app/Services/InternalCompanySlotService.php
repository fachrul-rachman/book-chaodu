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

    public function isInternalTableCode(?string $code): bool
    {
        return is_string($code) && in_array($code, $this->tableCodes(), true);
    }

    public function isInternalIncenseNumber(mixed $number): bool
    {
        return in_array((int) $number, $this->incenseNumbers(), true);
    }

    public function isInternalBooking(?Booking $booking): bool
    {
        return $booking?->referral_source === $this->sourceValue();
    }

    public function tableReportNumber(string $code): string
    {
        return 'INTERNAL-'.$code;
    }

    public function incenseReportNumber(int $number): string
    {
        return 'INTERNAL-HIO-'.$number;
    }

    public function tablePackageName(): string
    {
        return 'Sembahyang Internal';
    }

    public function incensePackageName(): string
    {
        return 'Hio Internal';
    }

    /**
     * @return array<int, array{
     *     booking_number:string,
     *     customer_name:string,
     *     customer_phone:string,
     *     package_code:string,
     *     package_name:string,
     *     attendee_count:int,
     *     vegetarian_quantity:int,
     *     non_vegetarian_quantity:int,
     *     table_number:string,
     *     incense_number:string,
     *     amount:float,
     *     referral_source:string,
     *     virtual_account_number:string|null,
     *     agent_name:string|null,
     *     booking_date:string|null,
     *     approval_date:string|null
     * }>
     */
    public function reportRows(): array
    {
        $rows = [];

        foreach ($this->tableCodes() as $code) {
            $rows[] = [
                'booking_number' => $this->tableReportNumber($code),
                'customer_name' => $this->sourceLabel(),
                'customer_phone' => '-',
                'package_code' => 'PRAYER',
                'package_name' => $this->tablePackageName(),
                'attendee_count' => 0,
                'vegetarian_quantity' => 0,
                'non_vegetarian_quantity' => 0,
                'table_number' => $code,
                'incense_number' => '',
                'amount' => 0.0,
                'referral_source' => $this->sourceLabel(),
                'virtual_account_number' => null,
                'agent_name' => null,
                'booking_date' => null,
                'approval_date' => null,
            ];
        }

        foreach ($this->incenseNumbers() as $number) {
            $rows[] = [
                'booking_number' => $this->incenseReportNumber($number),
                'customer_name' => $this->sourceLabel(),
                'customer_phone' => '-',
                'package_code' => 'INCENSE',
                'package_name' => $this->incensePackageName(),
                'attendee_count' => 0,
                'vegetarian_quantity' => 0,
                'non_vegetarian_quantity' => 0,
                'table_number' => '',
                'incense_number' => (string) $number,
                'amount' => 0.0,
                'referral_source' => $this->sourceLabel(),
                'virtual_account_number' => null,
                'agent_name' => null,
                'booking_date' => null,
                'approval_date' => null,
            ];
        }

        return $rows;
    }
}
