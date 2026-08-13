<?php

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Enums\PackageCode;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\Package;
use App\Models\User;
use App\Services\GalleryMediaService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-test');
    Storage::fake('gallery-test');
});

function galleryFoundationBooking(): Booking
{
    $package = Package::factory()->create([
        'code' => PackageCode::Prayer,
        'name' => 'Sembahyang',
        'price' => 200000,
        'meal_quota' => 2,
        'requires_table' => true,
        'requires_incense' => false,
    ]);

    return Booking::query()->create([
        'booking_number' => 'CD-GALLERY01',
        'idempotency_key' => 'gallery-foundation-booking',
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Prayer->value,
        'package_name_snapshot' => 'Sembahyang',
        'package_price_snapshot' => 200000,
        'customer_name' => 'Budi',
        'customer_phone' => '+628123456789',
        'customer_email' => 'budi@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Approved,
    ]);
}

it('creates the gallery media schema needed by later modules', function () {
    expect(Schema::hasColumns('gallery_media', [
        'id',
        'uuid',
        'scope',
        'booking_id',
        'source_prayer_paper_id',
        'media_type',
        'status',
        'storage_disk',
        'original_path',
        'preview_path',
        'thumbnail_path',
        'original_filename',
        'stored_filename',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'caption',
        'error_message',
        'uploaded_by',
        'published_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('stores a global image with an opaque path and metadata', function () {
    $uploader = User::factory()->contentTeam()->create();
    $file = UploadedFile::fake()->image('pembukaan.jpg', 1200, 800)->size(512);

    $media = app(GalleryMediaService::class)->storeOriginal(
        $file,
        GalleryMediaScope::Global,
        $uploader,
    );

    expect($media->scope)->toBe(GalleryMediaScope::Global)
        ->and($media->booking_id)->toBeNull()
        ->and($media->media_type)->toBe(GalleryMediaType::Image)
        ->and($media->status)->toBe(GalleryMediaStatus::Processing)
        ->and($media->storage_disk)->toBe('gallery-test')
        ->and($media->original_filename)->toBe('pembukaan.jpg')
        ->and($media->uploaded_by)->toBe($uploader->id)
        ->and($media->original_path)->toStartWith('gallery/global/'.$media->uuid.'/original.');

    Storage::disk('gallery-test')->assertExists($media->original_path);
});

it('stores booking media under the selected booking and exposes both relationships', function () {
    $uploader = User::factory()->contentTeam()->create();
    $booking = galleryFoundationBooking();
    $file = UploadedFile::fake()->create('meja-a18.mp4', 1024, 'video/mp4');

    $media = app(GalleryMediaService::class)->storeOriginal(
        $file,
        GalleryMediaScope::Booking,
        $uploader,
        $booking,
    );

    expect($media->booking->is($booking))->toBeTrue()
        ->and($media->uploader->is($uploader))->toBeTrue()
        ->and($media->media_type)->toBe(GalleryMediaType::Video)
        ->and($media->original_path)->toStartWith('gallery/bookings/'.$booking->id.'/'.$media->uuid.'/original.')
        ->and($booking->galleryMedia()->whereKey($media->id)->exists())->toBeTrue()
        ->and($uploader->uploadedGalleryMedia()->whereKey($media->id)->exists())->toBeTrue();

    Storage::disk('gallery-test')->assertExists($media->original_path);
});

it('rejects a global media target that contains a booking', function () {
    $uploader = User::factory()->contentTeam()->create();
    $booking = galleryFoundationBooking();
    $file = UploadedFile::fake()->image('global.jpg');

    expect(fn () => app(GalleryMediaService::class)->storeOriginal(
        $file,
        GalleryMediaScope::Global,
        $uploader,
        $booking,
    ))->toThrow(DomainException::class);

    expect(GalleryMedia::query()->count())->toBe(0);
    Storage::disk('gallery-test')->assertDirectoryEmpty('/');
});

it('rejects booking media without a booking target', function () {
    $uploader = User::factory()->contentTeam()->create();
    $file = UploadedFile::fake()->image('customer.jpg');

    expect(fn () => app(GalleryMediaService::class)->storeOriginal(
        $file,
        GalleryMediaScope::Booking,
        $uploader,
    ))->toThrow(DomainException::class);

    expect(GalleryMedia::query()->count())->toBe(0);
    Storage::disk('gallery-test')->assertDirectoryEmpty('/');
});

it('rejects files that are neither images nor videos before storing metadata', function () {
    $uploader = User::factory()->contentTeam()->create();
    $file = UploadedFile::fake()->create('catatan.pdf', 100, 'application/pdf');

    expect(fn () => app(GalleryMediaService::class)->storeOriginal(
        $file,
        GalleryMediaScope::Global,
        $uploader,
    ))->toThrow(RuntimeException::class, 'Tipe media gallery tidak didukung.');

    expect(GalleryMedia::query()->count())->toBe(0);
    Storage::disk('gallery-test')->assertDirectoryEmpty('/');
});

it('removes the uploaded object when saving metadata fails', function () {
    $uploader = User::factory()->contentTeam()->create();
    $file = UploadedFile::fake()->image('gagal.jpg');

    GalleryMedia::creating(function (): void {
        throw new RuntimeException('Database gagal.');
    });

    try {
        expect(fn () => app(GalleryMediaService::class)->storeOriginal(
            $file,
            GalleryMediaScope::Global,
            $uploader,
        ))->toThrow(RuntimeException::class, 'Database gagal.');

        Storage::disk('gallery-test')->assertDirectoryEmpty('/');
    } finally {
        GalleryMedia::flushEventListeners();
    }
});

it('checks gallery storage with a temporary object and removes it afterwards', function () {
    config()->set('filesystems.disks.gallery-test', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/gallery-test'),
        'key' => 'configured-for-test',
        'secret' => 'configured-for-test',
        'bucket' => 'gallery-test',
        'endpoint' => 'https://gallery.test',
        'region' => 'auto',
    ]);

    expect(Artisan::call('storage:gallery-check', ['--write' => true]))
        ->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Koneksi tulis dan hapus gallery berhasil.');

    Storage::disk('gallery-test')->assertDirectoryEmpty('/');
});

it('fails the gallery storage check when required configuration is missing', function () {
    config()->set('filesystems.disks.gallery-test', [
        'driver' => 's3',
        'key' => null,
        'secret' => null,
        'bucket' => null,
        'endpoint' => null,
    ]);

    expect(Artisan::call('storage:gallery-check'))
        ->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Konfigurasi gallery belum lengkap pada field: key.');
});
