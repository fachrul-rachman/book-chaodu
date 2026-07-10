<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingRejectionService
{
    public function __construct(
        private readonly SlotAllocator $slotAllocator,
        private readonly VirtualAccountService $virtualAccountService,
    ) {}

    public function reject(Booking $booking, string $reason, int $adminId): Booking
    {
        DB::transaction(function () use ($booking, $reason, $adminId): void {
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $currentStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if ($currentStatus !== BookingStatus::Pending) {
                return;
            }

            if (! $booking->payment()->exists()) {
                return;
            }

            $booking->forceFill([
                'status' => BookingStatus::Rejected,
                'rejection_reason' => trim($reason),
                'rejected_at' => now(),
                'rejected_by' => $adminId,
                'approved_at' => null,
                'approved_by' => null,
            ])->save();

            $this->slotAllocator->releaseByBookingId($booking->id);
            $this->virtualAccountService->releaseByBooking($booking);
        });

        return $booking->fresh(['tableSlots', 'incenseSlots']) ?? $booking;
    }
}
