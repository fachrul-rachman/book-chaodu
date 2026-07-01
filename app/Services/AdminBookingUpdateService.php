<?php

namespace App\Services;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingName;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBookingUpdateService
{
    public function __construct(
        private readonly SlotAllocator $slotAllocator,
        private readonly PrayerPaperGenerationService $prayerPaperGenerationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Booking $booking, array $payload, int $adminId): Booking
    {
        try {
            $regeneratePrayerPaper = DB::transaction(function () use ($booking, $payload, $adminId): bool {
                $booking = Booking::query()
                    ->with(['package', 'names', 'meal', 'payment', 'tableSlots', 'incenseSlots'])
                    ->lockForUpdate()
                    ->findOrFail($booking->id);

                if (BookingStatus::from((string) $booking->getRawOriginal('status')) !== BookingStatus::Pending) {
                    throw ValidationException::withMessages([
                        'booking' => 'Booking ini sudah tidak bisa diubah.',
                    ]);
                }

                $booking->fill([
                    'customer_name' => $payload['customer_name'],
                    'customer_phone' => $payload['customer_phone'],
                    'customer_email' => $payload['customer_email'],
                    'attendee_count' => $payload['attendee_count'],
                    'referral_source' => $payload['referral_source'],
                    'agent_name' => $payload['agent_name'],
                ]);

                $booking->save();

                $booking->meal()->updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'vegetarian_quantity' => $payload['vegetarian_quantity'],
                        'non_vegetarian_quantity' => $payload['non_vegetarian_quantity'],
                    ],
                );

                $booking->payment()->updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'expected_amount' => $booking->package_price_snapshot,
                        'sender_name' => $payload['sender_name'],
                        'transferred_amount' => $payload['transferred_amount'],
                        'transfer_date' => $payload['transfer_date'],
                        'updated_by' => $adminId,
                    ],
                );

                $regeneratePrayerPaper = $this->syncNames($booking, $payload, $adminId);

                if (! empty($payload['replace_table_slot_id'])) {
                    $this->slotAllocator->replaceTableSlot($booking->id, (int) $payload['replace_table_slot_id']);
                }

                if (! empty($payload['replace_incense_slot_id'])) {
                    $this->slotAllocator->replaceIncenseSlot($booking->id, (int) $payload['replace_incense_slot_id']);
                }

                return $regeneratePrayerPaper;
            });
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages([
                'slot' => $exception->getMessage(),
            ]);
        }

        $booking = $booking->fresh(['names', 'meal', 'payment', 'tableSlots', 'incenseSlots', 'prayerPapers']) ?? $booking;

        if ($regeneratePrayerPaper) {
            $this->prayerPaperGenerationService->generateForBooking($booking);
            $booking = $booking->fresh(['names', 'meal', 'payment', 'tableSlots', 'incenseSlots', 'prayerPapers']) ?? $booking;
        }

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncNames(Booking $booking, array $payload, int $adminId): bool
    {
        $changed = false;

        foreach ($payload['deceased_names'] ?? [] as $namePayload) {
            $changed = $this->upsertName(
                $booking,
                BookingNameCategory::Deceased,
                (int) $namePayload['position'],
                $namePayload['indonesian_name'] ?? null,
                $namePayload['mandarin_name'] ?? null,
                $adminId,
            ) || $changed;
        }

        $deceasedPositions = [];

        foreach ($payload['deceased_names'] ?? [] as $namePayload) {
            $deceasedPositions[] = (int) ($namePayload['position'] ?? 0);
        }

        $existingDeceasedNames = $booking->names
            ->where('category', BookingNameCategory::Deceased);

        foreach ($existingDeceasedNames as $name) {
            if (in_array($name->position, $deceasedPositions, true)) {
                continue;
            }

            $name->delete();
            $changed = true;
        }

        $incensePayload = $payload['incense_name'] ?? null;
        $hasIncenseName = is_array($incensePayload)
            && (filled($incensePayload['indonesian_name'] ?? null) || filled($incensePayload['mandarin_name'] ?? null));

        if ($hasIncenseName) {
            $changed = $this->upsertName(
                $booking,
                BookingNameCategory::Incense,
                1,
                $incensePayload['indonesian_name'] ?? null,
                $incensePayload['mandarin_name'] ?? null,
                $adminId,
            ) || $changed;
        } else {
            $incenseName = $booking->names
                ->first(fn (BookingName $name): bool => $name->category === BookingNameCategory::Incense);

            if ($incenseName) {
                $incenseName->delete();
                $changed = true;
            }
        }

        return $changed;
    }

    private function upsertName(
        Booking $booking,
        BookingNameCategory $category,
        int $position,
        ?string $indonesianName,
        ?string $mandarinName,
        int $adminId,
    ): bool {
        $existing = $booking->names->first(
            fn (BookingName $name): bool => $name->category === $category && $name->position === $position,
        );

        $nextIndonesianName = $this->nullableTrim($indonesianName);
        $nextMandarinName = $this->nullableTrim($mandarinName);

        if ($nextIndonesianName === null && $nextMandarinName === null) {
            if ($existing) {
                $existing->delete();

                return true;
            }

            return false;
        }

        if (! $existing) {
            $booking->names()->create([
                'category' => $category,
                'position' => $position,
                'indonesian_name' => $nextIndonesianName,
                'mandarin_name' => $nextMandarinName,
                'updated_by' => $adminId,
            ]);

            return true;
        }

        $hasChanged = $existing->indonesian_name !== $nextIndonesianName
            || $existing->mandarin_name !== $nextMandarinName;

        $existing->forceFill([
            'indonesian_name' => $nextIndonesianName,
            'mandarin_name' => $nextMandarinName,
            'updated_by' => $adminId,
        ])->save();

        return $hasChanged;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
