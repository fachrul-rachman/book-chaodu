<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\Package;
use App\Models\PrayerPaper;
use App\Services\PrayerPaperGallerySyncService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase5.storage_disk', 'prayer-paper-gallery-source');
    config()->set('gallery.storage_disk', 'prayer-paper-gallery-target');
    Storage::fake('prayer-paper-gallery-source');
    Storage::fake('prayer-paper-gallery-target');
});

function approvedPrayerPaperBooking(string $number = 'CD-PAPER-GALLERY'): Booking
{
    $package = Package::query()->firstOrCreate(
        ['code' => PackageCode::Combo],
        [
            'name' => 'Combo',
            'price' => 0,
            'meal_quota' => 4,
            'requires_table' => true,
            'requires_incense' => true,
        ],
    );

    return Booking::query()->create([
        'booking_number' => $number,
        'idempotency_key' => 'paper-gallery-'.$number,
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Combo->value,
        'package_name_snapshot' => 'Combo',
        'package_price_snapshot' => 0,
        'customer_name' => 'Budi',
        'customer_phone' => '+628123456789',
        'customer_email' => 'budi@example.com',
        'attendee_count' => 4,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
    ]);
}

function readyPrayerPaper(Booking $booking, PrayerPaperType $type, int $sequence = 1, int $version = 1): PrayerPaper
{
    $path = "prayer-papers/{$booking->booking_number}/{$type->value}-{$sequence}-v{$version}.png";
    $image = UploadedFile::fake()->image("{$type->value}.png", 900, 1400);
    Storage::disk('prayer-paper-gallery-source')->put($path, $image->getContent());

    return PrayerPaper::query()->create([
        'booking_id' => $booking->id,
        'type' => $type,
        'sequence' => $sequence,
        'file_path' => $path,
        'version' => $version,
        'status' => PrayerPaperStatus::Ready,
        'generated_at' => now(),
    ]);
}

it('copies ready prayer and incense papers into an approved customer gallery idempotently', function () {
    $booking = approvedPrayerPaperBooking();
    $prayer = readyPrayerPaper($booking, PrayerPaperType::A);
    $incense = readyPrayerPaper($booking, PrayerPaperType::B);

    $service = app(PrayerPaperGallerySyncService::class);
    $service->syncForBooking($booking);
    $service->syncForBooking($booking);

    $media = GalleryMedia::query()->where('booking_id', $booking->id)->orderBy('sort_order')->get();

    expect($media)->toHaveCount(2)
        ->and($media->pluck('source_prayer_paper_id')->all())->toBe([$prayer->id, $incense->id])
        ->and($media->pluck('caption')->all())->toBe(['Kertas Doa', 'Kertas Hio'])
        ->and($media->every(fn (GalleryMedia $item): bool => $item->status === GalleryMediaStatus::Ready))->toBeTrue();

    foreach ($media as $item) {
        Storage::disk('prayer-paper-gallery-target')->assertExists($item->original_path);
    }
});

it('replaces the gallery copy after a prayer paper is regenerated', function () {
    $booking = approvedPrayerPaperBooking('CD-PAPER-UPDATE');
    $paper = readyPrayerPaper($booking, PrayerPaperType::A);
    $service = app(PrayerPaperGallerySyncService::class);
    $service->syncForBooking($booking);
    $oldMedia = GalleryMedia::query()->where('source_prayer_paper_id', $paper->id)->firstOrFail();
    $oldPath = $oldMedia->original_path;

    $newPath = "prayer-papers/{$booking->booking_number}/A-1-v2.png";
    $newImage = UploadedFile::fake()->image('A-v2.png', 1000, 1500);
    Storage::disk('prayer-paper-gallery-source')->put($newPath, $newImage->getContent());
    $paper->update(['file_path' => $newPath, 'version' => 2, 'generated_at' => now()->addMinute()]);

    $service->syncForBooking($booking);
    $updated = $oldMedia->fresh();

    expect($updated?->id)->toBe($oldMedia->id)
        ->and($updated?->original_path)->not->toBe($oldPath)
        ->and($updated?->width)->toBe(1000)
        ->and($updated?->height)->toBe(1500);
    Storage::disk('prayer-paper-gallery-target')->assertMissing($oldPath);
    Storage::disk('prayer-paper-gallery-target')->assertExists((string) $updated?->original_path);
});

it('backfills prayer papers for old approved bookings without adding pending bookings', function () {
    $approved = approvedPrayerPaperBooking('CD-PAPER-OLD');
    readyPrayerPaper($approved, PrayerPaperType::A);
    $pending = approvedPrayerPaperBooking('CD-PAPER-PENDING');
    $pending->update(['status' => BookingStatus::Pending, 'approved_at' => null]);
    readyPrayerPaper($pending, PrayerPaperType::A);

    expect(Artisan::call('gallery:sync-prayer-papers'))->toBe(Command::SUCCESS)
        ->and(GalleryMedia::query()->where('booking_id', $approved->id)->count())->toBe(1)
        ->and(GalleryMedia::query()->where('booking_id', $pending->id)->count())->toBe(0);
});
