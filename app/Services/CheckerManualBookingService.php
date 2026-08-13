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
use Illuminate\Validation\ValidationException;

class CheckerManualBookingService
{
    public function __construct(
        private readonly SlotAllocator $slotAllocator,
        private readonly PrayerPaperGenerationService $prayerPapers,
        private readonly PrayerPaperGallerySyncService $paperGallery,
        private readonly TableLayoutGallerySyncService $tableGallery,
        private readonly ApprovalIntegrationService $approvalIntegrations,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload, int $checkerId): Booking
    {
        $existing = Booking::query()->where('idempotency_key', $payload['idempotency_key'])->first();

        if ($existing) {
            return $existing;
        }

        $package = Package::query()
            ->where('code', $payload['package_code'])
            ->where('is_active', true)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages(['package_code' => 'Paket yang dipilih sedang tidak aktif.']);
        }

        try {
            $booking = DB::transaction(function () use ($payload, $checkerId, $package): Booking {
                $booking = Booking::query()->create([
                    'booking_number' => $this->generateBookingNumber(),
                    'idempotency_key' => $payload['idempotency_key'],
                    'package_id' => $package->id,
                    'package_code_snapshot' => $package->code->value,
                    'package_name_snapshot' => $package->name,
                    'package_price_snapshot' => $package->price,
                    'customer_name' => $payload['customer_name'],
                    'customer_phone' => $payload['customer_phone'],
                    'customer_email' => $payload['customer_email'],
                    'attendee_count' => null,
                    'referral_source' => 'CHECKER_MANUAL',
                    'agent_name' => null,
                    'status' => BookingStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => $checkerId,
                    'prayer_paper_status' => PrayerPaperStatus::Pending,
                ]);

                $this->createNames($booking, $package->code, $payload);
                $this->slotAllocator->assignSelectedForPackage(
                    $package->code,
                    $booking->id,
                    isset($payload['table_slot_id']) ? (int) $payload['table_slot_id'] : null,
                    isset($payload['incense_slot_id']) ? (int) $payload['incense_slot_id'] : null,
                );
                $this->prayerPapers->createPendingRows($booking);
                $this->approvalIntegrations->ensureRow($booking);

                return $booking->fresh(['names', 'tableSlots', 'incenseSlots', 'prayerPapers', 'approvalIntegration']) ?? $booking;
            });
        } catch (SlotUnavailableException $exception) {
            $field = str_contains(strtolower($exception->getMessage()), 'meja') ? 'table_slot_id' : 'incense_slot_id';

            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }

        $this->prayerPapers->generateForBooking($booking);
        $this->paperGallery->syncSafely($booking);
        $this->tableGallery->syncSafely($booking);
        $this->approvalIntegrations->runAfterApproval($booking);

        return $booking->fresh(['names', 'tableSlots', 'incenseSlots', 'prayerPapers', 'approvalIntegration']) ?? $booking;
    }

    /** @param array<string, mixed> $payload */
    private function createNames(Booking $booking, PackageCode $packageCode, array $payload): void
    {
        if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
            foreach ($payload['deceased_names'] as $name) {
                if (blank($name['indonesian_name']) && blank($name['mandarin_name'])) {
                    continue;
                }

                $booking->names()->create([
                    'category' => BookingNameCategory::Deceased,
                    'position' => $name['position'],
                    'indonesian_name' => $name['indonesian_name'],
                    'mandarin_name' => $name['mandarin_name'],
                ]);
            }
        }

        if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
            $booking->names()->create([
                'category' => BookingNameCategory::Incense,
                'position' => 1,
                'indonesian_name' => $payload['incense_name']['indonesian_name'],
                'mandarin_name' => $payload['incense_name']['mandarin_name'],
            ]);
        }
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
