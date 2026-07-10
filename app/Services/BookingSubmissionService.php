<?php

namespace App\Services;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Package;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingSubmissionService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly SlotAllocator $slotAllocator,
        private readonly PrayerPaperGenerationService $prayerPaperGenerationService,
        private readonly VirtualAccountService $virtualAccountService,
        private readonly BookingDiscordNotificationService $bookingDiscordNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(array $payload): Booking
    {
        $existing = Booking::query()
            ->where('idempotency_key', $payload['idempotency_key'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $package = Package::query()
            ->where('code', $payload['package_code'])
            ->where('is_active', true)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'package_code' => 'Paket yang dipilih sudah tidak tersedia.',
            ]);
        }

        if (! $this->availabilityService->isPackageAvailable($package->code)) {
            throw ValidationException::withMessages([
                'package_code' => $this->availabilityService->unavailableReason($package->code)
                    ?? 'Paket yang dipilih sudah tidak tersedia.',
            ]);
        }

        $nameImagePaths = $this->storeNameImages($package->code, $payload);
        $proofPath = $this->storeProof(
            $payload['proof'],
            $payload['idempotency_key'],
        );

        $booking = null;

        try {
            $booking = DB::transaction(function () use ($payload, $package, $proofPath, $nameImagePaths): Booking {
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
                    'attendee_count' => $payload['attendee_count'],
                    'referral_source' => $payload['referral_source'],
                    'agent_name' => $payload['agent_name'],
                    'status' => BookingStatus::Pending,
                    'prayer_paper_status' => PrayerPaperStatus::Pending,
                ]);

                $this->createNames($booking->id, $package->code, $payload, $nameImagePaths);

                $booking->meal()->create([
                    'vegetarian_quantity' => $payload['vegetarian_quantity'],
                    'non_vegetarian_quantity' => $payload['non_vegetarian_quantity'],
                ]);

                $paymentIdentity = $this->virtualAccountService->paymentIdentity();
                $packageAccount = ! empty($payload['use_manual_virtual_account'])
                    ? $this->virtualAccountService->useManualAccountForBooking(
                        $booking,
                        (string) $payload['idempotency_key'],
                        $package->code,
                        (string) $payload['manual_virtual_account_number'],
                    )
                    : $this->virtualAccountService->assignToBooking(
                        $booking,
                        (string) $payload['idempotency_key'],
                        $package->code,
                    );

                $booking->payment()->create([
                    'expected_amount' => $package->price,
                    'sender_name' => $payload['sender_name'],
                    'transferred_amount' => $package->price,
                    'transfer_date' => $payload['transfer_date'],
                    'proof_path' => $proofPath,
                    'virtual_account_bank_name' => $paymentIdentity['bank_name'],
                    'virtual_account_number' => $packageAccount->account_number,
                    'virtual_account_holder' => $paymentIdentity['bank_account_holder'],
                ]);

                $this->slotAllocator->reserveForPackage($package->code, $booking->id);
                $this->prayerPaperGenerationService->createPendingRows($booking);

                return $booking->fresh(['meal', 'payment', 'names', 'prayerPapers']) ?? $booking;
            });
        } catch (QueryException $exception) {
            if ($this->isIdempotencyConflict($exception)) {
                $this->cleanupFiles($proofPath, $nameImagePaths);

                return Booking::query()
                    ->where('idempotency_key', $payload['idempotency_key'])
                    ->firstOrFail();
            }

            $this->cleanupFiles($proofPath, $nameImagePaths);

            throw $exception;
        } catch (SlotUnavailableException $exception) {
            $this->cleanupFiles($proofPath, $nameImagePaths);

            throw ValidationException::withMessages([
                'package_code' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            $this->cleanupFiles($proofPath, $nameImagePaths);

            throw $exception;
        }

        $this->prayerPaperGenerationService->generateForBooking($booking);
        $this->bookingDiscordNotificationService->notifySubmitted($booking);

        return $booking->fresh(['meal', 'payment', 'names', 'prayerPapers']) ?? $booking;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string>>  $nameImagePaths
     */
    private function createNames(int $bookingId, PackageCode $packageCode, array $payload, array $nameImagePaths): void
    {
        if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
            foreach ($payload['deceased_names'] as $index => $name) {
                if ($this->blank($name['indonesian_name']) && $this->blank($name['mandarin_name'])) {
                    continue;
                }

                $this->createNameRow(
                    $bookingId,
                    BookingNameCategory::Deceased->value,
                    $index + 1,
                    $name['indonesian_name'],
                    $name['mandarin_name'],
                    $nameImagePaths[BookingNameCategory::Deceased->value][$index + 1] ?? null,
                );
            }
        }

        if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
            $this->createNameRow(
                $bookingId,
                BookingNameCategory::Incense->value,
                1,
                $payload['incense_name']['indonesian_name'],
                $payload['incense_name']['mandarin_name'],
                $nameImagePaths[BookingNameCategory::Incense->value][1] ?? null,
            );
        }
    }

    private function createNameRow(
        int $bookingId,
        string $category,
        int $position,
        ?string $indonesianName,
        ?string $mandarinName,
        ?string $sourceImagePath,
    ): void {
        DB::table('booking_names')->insert([
            'booking_id' => $bookingId,
            'category' => $category,
            'position' => $position,
            'indonesian_name' => $this->nullableTrim($indonesianName),
            'mandarin_name' => $this->nullableTrim($mandarinName),
            'mandarin_source_image_path' => $sourceImagePath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    private function storeNameImages(PackageCode $packageCode, array $payload): array
    {
        $paths = [
            BookingNameCategory::Deceased->value => [],
            BookingNameCategory::Incense->value => [],
        ];

        if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
            foreach ($payload['deceased_names'] as $index => $name) {
                if (! ($name['source_image'] ?? null) instanceof UploadedFile) {
                    continue;
                }

                $position = $index + 1;
                $paths[BookingNameCategory::Deceased->value][$position] = $this->storeNameImage(
                    $name['source_image'],
                    $payload['idempotency_key'],
                    BookingNameCategory::Deceased->value,
                    $position,
                );
            }
        }

        if (
            in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)
            && ($payload['incense_name']['source_image'] ?? null) instanceof UploadedFile
        ) {
            $paths[BookingNameCategory::Incense->value][1] = $this->storeNameImage(
                $payload['incense_name']['source_image'],
                $payload['idempotency_key'],
                BookingNameCategory::Incense->value,
                1,
            );
        }

        return $paths;
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

    private function storeNameImage(
        UploadedFile $sourceImage,
        string $idempotencyKey,
        string $category,
        int $position,
    ): string {
        $extension = strtolower($sourceImage->getClientOriginalExtension()) ?: $sourceImage->extension() ?: 'bin';
        $path = sprintf(
            'booking-files/%s/nama-%s-%d.%s',
            trim($idempotencyKey),
            Str::lower($category),
            $position,
            $extension,
        );

        Storage::disk((string) config('phase4.private_upload_disk'))->putFileAs(
            dirname($path),
            $sourceImage,
            basename($path),
        );

        return $path;
    }

    /**
     * @param  array<string, array<int, string>>  $nameImagePaths
     */
    private function cleanupFiles(string $proofPath, array $nameImagePaths): void
    {
        Storage::disk((string) config('phase3.private_upload_disk'))->delete($proofPath);

        foreach ($nameImagePaths as $group) {
            foreach ($group as $path) {
                Storage::disk((string) config('phase4.private_upload_disk'))->delete($path);
            }
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

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'idempotency_key')
            || str_contains($message, 'bookings_idempotency_key_unique');
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function blank(?string $value): bool
    {
        return $this->nullableTrim($value) === null;
    }
}
