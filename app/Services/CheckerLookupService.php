<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\ApprovalIntegration;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

class CheckerLookupService
{
    /**
     * @return Collection<int, Booking>
     */
    public function findBookings(string $input): Collection
    {
        $normalized = $this->normalizeInput($input);

        if ($normalized === '') {
            return new Collection;
        }

        $booking = Booking::query()
            ->with($this->relations())
            ->where('booking_number', strtoupper($normalized))
            ->first();

        if ($booking) {
            return new Collection([$booking]);
        }

        $integration = ApprovalIntegration::query()
            ->where('qr_token_hash', hash('sha256', $normalized))
            ->first();

        if ($integration) {
            $booking = Booking::query()
                ->with($this->relations())
                ->find($integration->booking_id);

            return $booking ? new Collection([$booking]) : new Collection;
        }

        $phone = $this->normalizePhone($normalized);

        return Booking::query()
            ->with($this->relations())
            ->where('status', BookingStatus::Approved)
            ->where(function ($query) use ($normalized, $phone): void {
                $query->whereRaw('LOWER(customer_name) LIKE ?', ['%'.mb_strtolower($normalized).'%']);

                if ($phone !== null) {
                    $query->orWhere('customer_phone', '+'.$phone);
                }
            })
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function normalizeInput(string $input): string
    {
        $value = trim($input);

        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $query = parse_url($value, PHP_URL_QUERY);

            if (is_string($query)) {
                parse_str($query, $params);

                if (is_string($params['token'] ?? null) && trim($params['token']) !== '') {
                    return trim($params['token']);
                }
            }

            $path = parse_url($value, PHP_URL_PATH);

            if (is_string($path)) {
                $lastSegment = trim((string) basename($path));

                if ($lastSegment !== '') {
                    return $lastSegment;
                }
            }
        }

        return $value;
    }

    public function normalizePhone(string $input): ?string
    {
        if (preg_match('/[a-z]/i', $input)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['meal', 'tableSlots', 'incenseSlots', 'checkIn.checker'];
    }
}
