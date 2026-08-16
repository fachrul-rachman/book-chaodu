<?php

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Enums\PackageCode;
use App\Jobs\BuildGalleryArchive;
use App\Models\Booking;
use App\Models\GalleryArchive;
use App\Models\GalleryMedia;
use App\Models\Package;
use App\Services\GalleryArchiveService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-download-test');
    config()->set('gallery.archive_ttl_hours', 24);
    Storage::fake('gallery-download-test');
});

function downloadAlbumBooking(array $attributes = []): Booking
{
    $package = Package::query()->firstOrCreate(['code' => PackageCode::Combo], [
        'name' => 'Combo',
        'description' => 'Paket combo untuk pengujian download album.',
        'price' => 500000,
        'is_active' => true,
        'meal_quota' => 4,
        'requires_table' => true,
        'requires_incense' => true,
    ]);

    return Booking::query()->create(array_merge([
        'booking_number' => 'CD-DOWNLOAD01',
        'idempotency_key' => (string) str()->uuid(),
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Combo->value,
        'package_name_snapshot' => 'Combo',
        'package_price_snapshot' => 500000,
        'customer_name' => 'Customer Download',
        'customer_phone' => '+628123456789',
        'customer_email' => 'download@example.com',
        'attendee_count' => 4,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
    ], $attributes));
}

function downloadAlbumMedia(array $attributes = []): GalleryMedia
{
    $uuid = (string) str()->uuid();

    return GalleryMedia::query()->create(array_merge([
        'uuid' => $uuid,
        'scope' => GalleryMediaScope::Global,
        'booking_id' => null,
        'media_type' => GalleryMediaType::Image,
        'status' => GalleryMediaStatus::Ready,
        'storage_disk' => 'gallery-download-test',
        'original_path' => "gallery/global/{$uuid}/original.jpg",
        'thumbnail_path' => "gallery/global/{$uuid}/thumbnail.webp",
        'original_filename' => 'Doa Pembukaan.jpg',
        'stored_filename' => 'original.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size_bytes' => 12,
        'caption' => 'Doa pembukaan',
        'published_at' => now(),
    ], $attributes));
}

it('exposes safe download links and the total original size on an approved album', function () {
    $booking = downloadAlbumBooking();
    $media = downloadAlbumMedia(['size_bytes' => 1500]);

    $this->get(route('public.gallery.show', $booking->booking_number))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('media.0.downloadUrl', route('public.gallery.media.download', [
                'bookingNumber' => $booking->booking_number,
                'media' => $media->id,
            ]))
            ->where('downloadAll.totalSizeBytes', 1500)
            ->where('downloadAll.status', 'IDLE')
            ->where('downloadAll.requestUrl', route('public.gallery.archive.store', $booking->booking_number))
            ->where('downloadAll.statusUrl', route('public.gallery.archive.show', $booking->booking_number))
            ->where('downloadAll.downloadUrl', null));
});

it('downloads an original global or owned file with a safe attachment name', function () {
    $booking = downloadAlbumBooking();
    $global = downloadAlbumMedia(['original_filename' => '../Doa Pembukaan (1).jpg']);
    $owned = downloadAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $booking->id,
        'original_path' => "gallery/bookings/{$booking->id}/owned/original.mp4",
        'media_type' => GalleryMediaType::Video,
        'original_filename' => 'Video Keluarga.mp4',
        'stored_filename' => 'original.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
    ]);
    Storage::disk('gallery-download-test')->put($global->original_path, 'global-original');
    Storage::disk('gallery-download-test')->put($owned->original_path, 'owned-original');

    $this->get(route('public.gallery.media.download', [$booking->booking_number, $global->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Disposition', 'attachment; filename=Doa_Pembukaan_1.jpg')
        ->assertStreamedContent('global-original');

    $this->get(route('public.gallery.media.download', [$booking->booking_number, $owned->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertStreamedContent('owned-original');
});

it('blocks downloads for another booking, hidden media, and unavailable albums', function () {
    $bookingA = downloadAlbumBooking();
    $bookingB = downloadAlbumBooking(['booking_number' => 'CD-DOWNLOAD02']);
    $ownedB = downloadAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $bookingB->id,
        'original_path' => "gallery/bookings/{$bookingB->id}/private/original.jpg",
    ]);
    $hidden = downloadAlbumMedia(['status' => GalleryMediaStatus::Hidden]);
    $pending = downloadAlbumBooking([
        'booking_number' => 'CD-DOWNLOAD03',
        'status' => BookingStatus::Pending,
        'approved_at' => null,
    ]);

    foreach ([$ownedB, $hidden] as $media) {
        $this->get(route('public.gallery.media.download', [$bookingA->booking_number, $media->id]))
            ->assertNotFound();
    }

    $this->get(route('public.gallery.media.download', [$pending->booking_number, $hidden->id]))
        ->assertNotFound();
});

it('creates only one queued archive for repeated requests with unchanged media', function () {
    Queue::fake();
    $booking = downloadAlbumBooking();
    downloadAlbumMedia();

    $first = $this->postJson(route('public.gallery.archive.store', $booking->booking_number))
        ->assertAccepted()
        ->assertJsonPath('status', 'PENDING');
    $second = $this->postJson(route('public.gallery.archive.store', $booking->booking_number))
        ->assertAccepted()
        ->assertJsonPath('status', 'PENDING');

    expect($first->json('archiveId'))->toBe($second->json('archiveId'));
    $this->assertDatabaseCount('gallery_archives', 1);
    Queue::assertPushed(BuildGalleryArchive::class, 1);
});

it('builds a zip containing active global and owned originals only', function () {
    Queue::fake();
    $booking = downloadAlbumBooking();
    $other = downloadAlbumBooking(['booking_number' => 'CD-DOWNLOAD02']);
    $global = downloadAlbumMedia(['original_filename' => 'Pembukaan.jpg']);
    $owned = downloadAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $booking->id,
        'original_path' => "gallery/bookings/{$booking->id}/owned/original.mp4",
        'media_type' => GalleryMediaType::Video,
        'original_filename' => 'Keluarga.mp4',
        'stored_filename' => 'original.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
    ]);
    $hidden = downloadAlbumMedia(['status' => GalleryMediaStatus::Hidden]);
    $ownedOther = downloadAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $other->id,
        'original_path' => "gallery/bookings/{$other->id}/other/original.jpg",
    ]);

    foreach ([$global, $owned, $hidden, $ownedOther] as $media) {
        Storage::disk('gallery-download-test')->put($media->original_path, 'bytes-'.$media->id);
    }

    $archiveId = $this->postJson(route('public.gallery.archive.store', $booking->booking_number))
        ->assertAccepted()
        ->json('archiveId');
    app()->call([new BuildGalleryArchive($archiveId), 'handle']);

    $archive = GalleryArchive::query()->findOrFail($archiveId);
    expect($archive->status->value)->toBe('READY')
        ->and(now()->diffInHours($archive->expires_at))->toBeGreaterThan(23.9)
        ->and($archive->file_path)->not->toBeNull();
    Storage::disk('gallery-download-test')->assertExists($archive->file_path);

    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('gallery-download-test')->path($archive->file_path)))->toBeTrue()
        ->and($zip->numFiles)->toBe(2)
        ->and($zip->getFromName('001-Keluarga.mp4'))->toBe('bytes-'.$owned->id)
        ->and($zip->getFromName('002-Pembukaan.jpg'))->toBe('bytes-'.$global->id);
    $zip->close();

    $zipBytes = Storage::disk('gallery-download-test')->get($archive->file_path);
    $this->get(route('public.gallery.archive.download', $booking->booking_number))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip')
        ->assertHeader('Content-Disposition', 'attachment; filename=album-CD-DOWNLOAD01.zip')
        ->assertStreamedContent($zipBytes);

    $this->postJson(route('public.gallery.archive.store', $booking->booking_number))
        ->assertAccepted()
        ->assertJsonPath('status', 'READY');
    Queue::assertPushed(BuildGalleryArchive::class, 1);
});

it('uses a new fingerprint after album contents change', function () {
    Queue::fake();
    $booking = downloadAlbumBooking();
    downloadAlbumMedia();
    $firstId = $this->postJson(route('public.gallery.archive.store', $booking->booking_number))->json('archiveId');

    downloadAlbumMedia(['original_path' => 'gallery/global/new/original.jpg']);
    $secondId = $this->postJson(route('public.gallery.archive.store', $booking->booking_number))->json('archiveId');

    expect($secondId)->not->toBe($firstId);
    $this->assertDatabaseCount('gallery_archives', 2);
    Queue::assertPushed(BuildGalleryArchive::class, 2);
});

it('does not serve expired archives and cleanup never removes original media', function () {
    $booking = downloadAlbumBooking();
    $media = downloadAlbumMedia();
    Storage::disk('gallery-download-test')->put($media->original_path, 'original-stays');
    $archive = GalleryArchive::query()->create([
        'booking_id' => $booking->id,
        'fingerprint' => app(GalleryArchiveService::class)->fingerprint(collect([$media])),
        'status' => 'READY',
        'storage_disk' => 'gallery-download-test',
        'file_path' => "gallery/archives/{$booking->id}/expired.zip",
        'size_bytes' => 100,
        'expires_at' => now()->subMinute(),
    ]);
    Storage::disk('gallery-download-test')->put($archive->file_path, 'expired-zip');

    $this->get(route('public.gallery.archive.download', $booking->booking_number))->assertNotFound();
    $this->artisan('gallery:cleanup-archives')->assertSuccessful();

    Storage::disk('gallery-download-test')->assertMissing($archive->file_path);
    Storage::disk('gallery-download-test')->assertExists($media->original_path);
    expect($archive->fresh()->status->value)->toBe('EXPIRED')
        ->and($archive->fresh()->file_path)->toBeNull();
});

it('marks archive failures safely and allows a retry to be queued', function () {
    Queue::fake();
    $booking = downloadAlbumBooking();
    downloadAlbumMedia();
    $archiveId = $this->postJson(route('public.gallery.archive.store', $booking->booking_number))->json('archiveId');

    expect(fn () => app()->call([new BuildGalleryArchive($archiveId), 'handle']))->toThrow(RuntimeException::class);
    expect(GalleryArchive::query()->findOrFail($archiveId)->status->value)->toBe('FAILED');

    $this->postJson(route('public.gallery.archive.store', $booking->booking_number))
        ->assertAccepted()
        ->assertJsonPath('status', 'PENDING');
    Queue::assertPushed(BuildGalleryArchive::class, 2);
});

it('rate limits public download and archive routes', function () {
    expect(Route::getRoutes()->getByName('public.gallery.media.download')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-media')
        ->and(Route::getRoutes()->getByName('public.gallery.archive.store')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-archive')
        ->and(Route::getRoutes()->getByName('public.gallery.archive.show')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-archive')
        ->and(Route::getRoutes()->getByName('public.gallery.archive.download')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-archive');
});
