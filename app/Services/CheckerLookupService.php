<?php

namespace App\Services;

use App\Models\ApprovalIntegration;
use App\Models\Booking;

class CheckerLookupService
{
    public function findBooking(string $input): ?Booking
    {
        $normalized = $this->normalizeInput($input);

        if ($normalized === '') {
            return null;
        }

        $booking = Booking::query()
            ->with(['meal', 'tableSlots', 'incenseSlots', 'checkIn.checker'])
            ->where('booking_number', strtoupper($normalized))
            ->first();

        if ($booking) {
            return $booking;
        }

        $integration = ApprovalIntegration::query()
            ->where('qr_token_hash', hash('sha256', $normalized))
            ->first();

        if (! $integration) {
            return null;
        }

        return Booking::query()
            ->with(['meal', 'tableSlots', 'incenseSlots', 'checkIn.checker'])
            ->find($integration->booking_id);
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
}
