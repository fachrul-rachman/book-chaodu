<?php

namespace App\Services;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InternalCompanyBookingService
{
    public function __construct(
        private readonly SlotAllocator $slotAllocator,
        private readonly PrayerPaperGenerationService $prayerPaperGenerationService,
        private readonly InternalCompanySlotService $internalCompanySlotService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, int $adminId): Booking
    {
        $package = Package::query()
            ->where('code', PackageCode::Combo)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'booking' => 'Paket internal belum tersedia.',
            ]);
        }

        try {
            $booking = DB::transaction(function () use ($payload, $package, $adminId): Booking {
                $booking = Booking::query()->create([
                    'booking_number' => $this->generateBookingNumber(),
                    'idempotency_key' => 'internal-company-'.Str::uuid(),
                    'package_id' => $package->id,
                    'package_code_snapshot' => $package->code->value,
                    'package_name_snapshot' => $package->name,
                    'package_price_snapshot' => $package->price ?? '0',
                    'customer_name' => $payload['customer_name'],
                    'customer_phone' => $payload['customer_phone'],
                    'customer_email' => $payload['customer_email'],
                    'attendee_count' => $payload['attendee_count'],
                    'referral_source' => $this->internalCompanySlotService->sourceValue(),
                    'agent_name' => null,
                    'status' => BookingStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => $adminId,
                    'prayer_paper_status' => PrayerPaperStatus::Pending,
                ]);

                $this->createNames($booking, $payload);

                $booking->meal()->create([
                    'vegetarian_quantity' => $payload['vegetarian_quantity'],
                    'non_vegetarian_quantity' => $payload['non_vegetarian_quantity'],
                ]);

                $this->slotAllocator->assignInternalCompanySlots($booking->id);
                $this->prayerPaperGenerationService->createPendingRows($booking);

                return $booking->fresh(['names', 'meal', 'tableSlots', 'incenseSlots', 'prayerPapers']) ?? $booking;
            });
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages([
                'booking' => $exception->getMessage(),
            ]);
        }

        $this->prayerPaperGenerationService->generateForBooking($booking);

        return $booking->fresh(['names', 'meal', 'tableSlots', 'incenseSlots', 'prayerPapers']) ?? $booking;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createNames(Booking $booking, array $payload): void
    {
        foreach ($payload['deceased_names'] as $name) {
            if (blank($name['indonesian_name'] ?? null) && blank($name['mandarin_name'] ?? null)) {
                continue;
            }

            $booking->names()->create([
                'category' => BookingNameCategory::Deceased,
                'position' => $name['position'],
                'indonesian_name' => $name['indonesian_name'],
                'mandarin_name' => $name['mandarin_name'],
            ]);
        }

        $booking->names()->create([
            'category' => BookingNameCategory::Incense,
            'position' => 1,
            'indonesian_name' => $payload['incense_name']['indonesian_name'],
            'mandarin_name' => $payload['incense_name']['mandarin_name'],
        ]);
    }

    private function generateBookingNumber(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = collect(range(1, 8))
                ->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');

            $bookingNumber = 'CD-'.$suffix;
        } while (Booking::query()->where('booking_number', $bookingNumber)->exists());

        return $bookingNumber;
    }
}
