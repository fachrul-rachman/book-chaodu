<?php

namespace App\Services;

use App\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VegetarianCapacityService
{
    private const POSTGRES_LOCK_KEY = 2026081185;

    public function capacity(): int
    {
        return max(0, (int) config('phase3.vegetarian_capacity', 85));
    }

    public function used(): int
    {
        return (int) DB::table('booking_meals as meals')
            ->join('bookings', 'bookings.id', '=', 'meals.booking_id')
            ->whereIn('bookings.status', [
                BookingStatus::AwaitingPayment->value,
                BookingStatus::Pending->value,
                BookingStatus::Approved->value,
            ])
            ->sum('meals.vegetarian_quantity');
    }

    public function remaining(): int
    {
        return max(0, $this->capacity() - $this->used());
    }

    public function remainingForIdempotencyKey(?string $idempotencyKey): int
    {
        $remaining = $this->remaining();

        if (blank($idempotencyKey)) {
            return $remaining;
        }

        $existingQuantity = (int) DB::table('booking_meals as meals')
            ->join('bookings', 'bookings.id', '=', 'meals.booking_id')
            ->where('bookings.idempotency_key', $idempotencyKey)
            ->whereIn('bookings.status', [
                BookingStatus::AwaitingPayment->value,
                BookingStatus::Pending->value,
                BookingStatus::Approved->value,
            ])
            ->value('meals.vegetarian_quantity');

        return $remaining + $existingQuantity;
    }

    public function lockForReservation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(cast(? as bigint))', [self::POSTGRES_LOCK_KEY]);
        }
    }

    public function ensureReservationFits(int $requestedQuantity): void
    {
        if ($requestedQuantity <= 0) {
            return;
        }

        $used = $this->used();

        if ($used <= $this->capacity()) {
            return;
        }

        $remainingBeforeReservation = max(
            0,
            $this->capacity() - ($used - $requestedQuantity),
        );

        throw ValidationException::withMessages([
            'vegetarian_quantity' => $this->unavailableMessage($remainingBeforeReservation),
        ]);
    }

    public function unavailableMessage(int $remaining): string
    {
        if ($remaining <= 0) {
            return 'Kuota vegetarian sudah penuh.';
        }

        return "Kuota vegetarian tersisa {$remaining} porsi.";
    }
}
