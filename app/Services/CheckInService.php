<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function checkIn(Booking $booking, User $checker): CheckIn
    {
        return DB::transaction(function () use ($booking, $checker): CheckIn {
            $booking = Booking::query()
                ->with('checkIn.checker')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($booking->status !== BookingStatus::Approved) {
                throw ValidationException::withMessages([
                    'kode' => 'Booking ini belum bisa check-in.',
                ]);
            }

            $existing = $booking->checkIn;

            if ($existing) {
                return $existing->fresh('checker') ?? $existing;
            }

            $checkIn = CheckIn::query()->create([
                'booking_id' => $booking->id,
                'checked_in_by' => $checker->id,
                'checked_in_at' => now(),
            ]);

            return $checkIn->fresh('checker') ?? $checkIn;
        });
    }
}
