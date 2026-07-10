<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingExpiryService
{
    public const EXPIRED_REASON = '__AUTO_EXPIRED_UNPAID__';

    public function __construct(
        private readonly SlotAllocator $slotAllocator,
        private readonly VirtualAccountService $virtualAccountService,
    ) {}

    public function expireIfNeeded(Booking $booking): Booking
    {
        $expiresAt = $booking->created_at?->copy()->addHours($this->virtualAccountService->paymentLinkExpiryHours());

        if (
            ! $this->isAwaitingPayment($booking)
            || ! $expiresAt
            || $expiresAt->isFuture()
        ) {
            return $booking;
        }

        return $this->expireBooking($booking) ?? $booking;
    }

    public function expireUnpaidBookings(): int
    {
        $expired = 0;

        Booking::query()
            ->where('status', BookingStatus::Pending)
            ->whereDoesntHave('payment')
            ->where('created_at', '<=', now()->subHours($this->virtualAccountService->paymentLinkExpiryHours()))
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$expired): void {
                foreach ($bookings as $booking) {
                    if ($this->expireBooking($booking)) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    private function expireBooking(Booking $booking): ?Booking
    {
        return DB::transaction(function () use ($booking): ?Booking {
            $locked = Booking::query()
                ->with('payment')
                ->lockForUpdate()
                ->find($booking->id);

            if (! $locked || ! $this->isAwaitingPayment($locked)) {
                return null;
            }

            $locked->forceFill([
                'status' => BookingStatus::Rejected,
                'rejection_reason' => self::EXPIRED_REASON,
            ])->save();

            $this->slotAllocator->releaseByBookingId($locked->id);
            $this->virtualAccountService->releaseByBooking($locked);

            return $locked->fresh(['tableSlots', 'incenseSlots']) ?? $locked;
        });
    }

    public function isAwaitingPayment(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Pending
            && ! $booking->payment()->exists()
            && $booking->rejection_reason !== self::EXPIRED_REASON;
    }

    public function isExpiredBooking(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Rejected
            && $booking->rejection_reason === self::EXPIRED_REASON
            && ! $booking->payment()->exists();
    }
}
