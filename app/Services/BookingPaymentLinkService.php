<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;

class BookingPaymentLinkService
{
    public function __construct(
        private readonly VirtualAccountService $virtualAccountService,
    ) {}

    public function paymentUrl(Booking $booking): string
    {
        return route('public.booking.payment.show', [
            'bookingNumber' => $booking->booking_number,
            'token' => $this->tokenFor($booking),
        ]);
    }

    public function hasValidToken(Booking $booking, ?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($this->tokenFor($booking), $token);
    }

    public function expiresAt(Booking $booking)
    {
        return $booking->created_at?->copy()->addHours($this->virtualAccountService->paymentLinkExpiryHours());
    }

    public function isExpired(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Expired) {
            return true;
        }

        $expiresAt = $this->expiresAt($booking);

        return $expiresAt ? $expiresAt->isPast() : false;
    }

    private function tokenFor(Booking $booking): string
    {
        return hash_hmac(
            'sha256',
            $booking->booking_number.'|'.$booking->idempotency_key,
            (string) config('app.key'),
        );
    }
}
