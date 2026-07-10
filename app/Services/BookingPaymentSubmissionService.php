<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BookingPaymentSubmissionService
{
    public function __construct(
        private readonly BookingDiscordNotificationService $bookingDiscordNotificationService,
        private readonly BookingExpiryService $bookingExpiryService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(Booking $booking, array $payload): Booking
    {
        if ($booking->status === BookingStatus::Pending && $booking->payment()->exists()) {
            return $booking->fresh(['payment', 'meal', 'names', 'prayerPapers']) ?? $booking;
        }

        if (! $this->bookingExpiryService->isAwaitingPayment($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'Booking ini sudah tidak bisa dibayar lagi.',
            ]);
        }

        $proofPath = $this->storeProof($payload['proof'], $booking->idempotency_key);

        try {
            $booking = DB::transaction(function () use ($booking, $payload, $proofPath): Booking {
                $lockedBooking = Booking::query()
                    ->lockForUpdate()
                    ->findOrFail($booking->id);

                if ($lockedBooking->status === BookingStatus::Pending && $lockedBooking->payment()->exists()) {
                    Storage::disk((string) config('phase3.private_upload_disk'))->delete($proofPath);

                    return $lockedBooking->fresh(['payment', 'meal', 'names', 'prayerPapers']) ?? $lockedBooking;
                }

                if (! $this->bookingExpiryService->isAwaitingPayment($lockedBooking)) {
                    throw ValidationException::withMessages([
                        'booking' => 'Booking ini sudah tidak bisa dibayar lagi.',
                    ]);
                }

                $lockedBooking->payment()->updateOrCreate(
                    ['booking_id' => $lockedBooking->id],
                    [
                        'expected_amount' => $lockedBooking->package_price_snapshot,
                        'sender_name' => $payload['sender_name'],
                        'transferred_amount' => $lockedBooking->package_price_snapshot,
                        'transfer_date' => $payload['transfer_date'],
                        'proof_path' => $proofPath,
                        'virtual_account_bank_name' => $payload['virtual_account_bank_name'],
                        'virtual_account_number' => $payload['virtual_account_number'],
                        'virtual_account_holder' => $payload['virtual_account_holder'],
                    ],
                );

                $lockedBooking->forceFill([
                    'status' => BookingStatus::Pending,
                ])->save();

                return $lockedBooking->fresh(['payment', 'meal', 'names', 'prayerPapers']) ?? $lockedBooking;
            });
        } catch (\Throwable $throwable) {
            Storage::disk((string) config('phase3.private_upload_disk'))->delete($proofPath);

            throw $throwable;
        }

        $this->bookingDiscordNotificationService->notifySubmitted($booking);

        return $booking;
    }

    private function storeProof(UploadedFile $proof, string $idempotencyKey): string
    {
        $extension = strtolower($proof->getClientOriginalExtension()) ?: $proof->extension() ?: 'bin';
        $path = 'booking-files/'.trim($idempotencyKey).'/bukti-transfer.'.$extension;

        Storage::disk((string) config('phase3.private_upload_disk'))->putFileAs(
            dirname($path),
            $proof,
            basename($path),
        );

        return $path;
    }
}
