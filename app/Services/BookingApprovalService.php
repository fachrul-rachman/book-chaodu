<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingApprovalService
{
    public function __construct(
        private readonly SlotAllocator $slotAllocator,
        private readonly ApprovalIntegrationService $approvalIntegrationService,
        private readonly BookingDiscordNotificationService $bookingDiscordNotificationService,
    ) {}

    public function approve(Booking $booking, int $adminId): Booking
    {
        $approvedNow = false;

        DB::transaction(function () use ($booking, $adminId, &$approvedNow): void {
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $currentStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if ($currentStatus === BookingStatus::Approved) {
                return;
            }

            if ($currentStatus === BookingStatus::Rejected) {
                return;
            }

            $booking->forceFill([
                'status' => BookingStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $adminId,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ])->save();

            $this->slotAllocator->assignByBookingId($booking->id);
            $this->approvalIntegrationService->ensureRow($booking);
            $approvedNow = true;
        });

        $booking = $booking->fresh(['tableSlots', 'incenseSlots', 'approvalIntegration']) ?? $booking;
        $this->approvalIntegrationService->runAfterApproval($booking);

        if ($approvedNow) {
            $this->bookingDiscordNotificationService->notifyAgentApproved($booking);
        }

        return $booking->fresh(['tableSlots', 'incenseSlots', 'approvalIntegration']) ?? $booking;
    }
}
