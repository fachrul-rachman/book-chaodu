<?php

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\GalleryMediaDeletion;
use App\Models\IncenseSlot;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\GalleryDirectUploadService;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-test');
    config()->set('gallery.single_upload_max_bytes', 100 * 1024 * 1024);
    config()->set('gallery.multipart_part_size_bytes', 10 * 1024 * 1024);
    Storage::fake('gallery-test');
});

function customerGalleryBooking(array $attributes = []): Booking
{
    $package = Package::query()->firstOrCreate(['code' => PackageCode::Combo], [
        'name' => 'Combo',
        'description' => 'Paket combo untuk pengujian gallery.',
        'price' => 500000,
        'is_active' => true,
        'meal_quota' => 4,
        'requires_table' => true,
        'requires_incense' => true,
    ]);

    return Booking::query()->create(array_merge([
        'booking_number' => 'CD-CUSTOMER01',
        'idempotency_key' => (string) str()->uuid(),
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Combo->value,
        'package_name_snapshot' => 'Combo',
        'package_price_snapshot' => 500000,
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '+628123456789',
        'customer_email' => 'budi-private@example.com',
        'attendee_count' => 4,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
    ], $attributes));
}

function customerGalleryMedia(Booking $booking, array $attributes = []): GalleryMedia
{
    $uuid = (string) str()->uuid();

    return GalleryMedia::query()->create(array_merge([
        'uuid' => $uuid,
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $booking->id,
        'media_type' => GalleryMediaType::Image,
        'status' => GalleryMediaStatus::Ready,
        'storage_disk' => 'gallery-test',
        'original_path' => "gallery/bookings/{$booking->id}/{$uuid}/original.jpg",
        'original_filename' => 'meja-customer.jpg',
        'stored_filename' => 'original.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size_bytes' => 128,
        'uploaded_by' => User::factory()->contentTeam()->create()->id,
        'published_at' => now(),
    ], $attributes));
}

it('searches approved bookings by number or customer name with a minimal payload', function () {
    $booking = customerGalleryBooking();
    customerGalleryMedia($booking);
    customerGalleryBooking([
        'booking_number' => 'CD-REJECTED01',
        'customer_name' => 'Budi Ditolak',
        'status' => BookingStatus::Rejected,
        'approved_at' => null,
        'rejected_at' => now(),
    ]);

    $this->actingAs(User::factory()->contentTeam()->create())
        ->get(route('content.customer-media.index', ['q' => 'Budi']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('content/customer-media/index')
            ->has('results', 1)
            ->where('results.0.id', $booking->id)
            ->where('results.0.bookingNumber', 'CD-CUSTOMER01')
            ->where('results.0.customerName', 'Budi Santoso')
            ->where('results.0.packageName', 'Combo')
            ->where('results.0.mediaCount', 1)
            ->missing('results.0.customer_phone')
            ->missing('results.0.customer_email')
            ->missing('results.0.attendee_count'));
});

it('shows only operational booking details and its own media', function () {
    $booking = customerGalleryBooking();
    TableSlot::query()->create([
        'code' => 'A18', 'row_code' => 'A', 'number' => 18, 'allocation_order' => 1,
        'status' => SlotStatus::Assigned, 'booking_id' => $booking->id,
    ]);
    IncenseSlot::query()->create([
        'number' => 1, 'allocation_order' => 1, 'status' => SlotStatus::Assigned, 'booking_id' => $booking->id,
    ]);
    $media = customerGalleryMedia($booking);
    $otherBooking = customerGalleryBooking(['booking_number' => 'CD-CUSTOMER02']);
    customerGalleryMedia($otherBooking);

    $this->actingAs(User::factory()->contentTeam()->create())
        ->get(route('content.customer-media.index', ['booking' => $booking->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedBooking.id', $booking->id)
            ->where('selectedBooking.tableNumber', 'A18')
            ->where('selectedBooking.incenseNumber', '1')
            ->missing('selectedBooking.customerPhone')
            ->missing('selectedBooking.customerEmail')
            ->has('media', 1)
            ->where('media.0.id', $media->id));
});

it('does not allow pending or rejected bookings to become media targets', function (BookingStatus $status) {
    $booking = customerGalleryBooking([
        'status' => $status,
        'approved_at' => null,
        'rejected_at' => $status === BookingStatus::Rejected ? now() : null,
    ]);

    $this->actingAs(User::factory()->contentTeam()->create())
        ->get(route('content.customer-media.index', ['booking' => $booking->id]))
        ->assertNotFound();
})->with([BookingStatus::Pending, BookingStatus::Rejected]);

it('initiates a direct upload under the selected booking path', function () {
    $booking = customerGalleryBooking();
    $uploader = User::factory()->contentTeam()->create();
    $direct = Mockery::mock(GalleryDirectUploadService::class);
    $direct->shouldReceive('initiate')->once()->andReturn([
        'mode' => 'single', 'url' => 'https://upload.test/customer', 'headers' => ['Content-Type' => 'image/jpeg'],
    ]);
    $this->app->instance(GalleryDirectUploadService::class, $direct);

    $this->actingAs($uploader)
        ->postJson(route('content.customer-media.uploads.store', $booking), [
            'upload_token' => (string) str()->uuid(),
            'original_filename' => 'meja-a18.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ])
        ->assertCreated()
        ->assertJsonPath('upload.mode', 'single');

    $media = GalleryMedia::query()->sole();
    expect($media->scope)->toBe(GalleryMediaScope::Booking)
        ->and($media->booking_id)->toBe($booking->id)
        ->and($media->original_path)->toStartWith("gallery/bookings/{$booking->id}/");
});

it('rejects an upload when the selected booking is no longer approved', function () {
    $booking = customerGalleryBooking(['status' => BookingStatus::Pending, 'approved_at' => null]);

    $this->actingAs(User::factory()->contentTeam()->create())
        ->postJson(route('content.customer-media.uploads.store', $booking), [
            'upload_token' => (string) str()->uuid(),
            'original_filename' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ])
        ->assertNotFound();
});

it('prevents managing media from another booking through a manipulated URL', function () {
    $bookingA = customerGalleryBooking(['booking_number' => 'CD-ISOLATION-A']);
    $bookingB = customerGalleryBooking(['booking_number' => 'CD-ISOLATION-B']);
    $mediaB = customerGalleryMedia($bookingB);
    $user = User::factory()->contentTeam()->create();

    $this->actingAs($user)
        ->patchJson(route('content.customer-media.update', [$bookingA, $mediaB]), ['caption' => 'Bukan milik A'])
        ->assertNotFound();
    $this->deleteJson(route('content.customer-media.destroy', [$bookingA, $mediaB]))
        ->assertNotFound();

    expect($mediaB->refresh()->caption)->toBeNull();
});

it('completes video upload and manages caption visibility order and permanent deletion', function () {
    $booking = customerGalleryBooking();
    $video = customerGalleryMedia($booking, [
        'media_type' => GalleryMediaType::Video,
        'status' => GalleryMediaStatus::Processing,
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'stored_filename' => 'original.mp4',
        'original_filename' => 'meja.mp4',
        'size_bytes' => 16,
        'upload_mode' => 'SINGLE',
        'upload_expires_at' => now()->addMinutes(15),
        'published_at' => null,
    ]);
    $photo = customerGalleryMedia($booking);
    Storage::disk('gallery-test')->put($video->original_path, "\x00\x00\x00\x10ftypisom0000");
    Storage::disk('gallery-test')->put($photo->original_path, 'photo');
    $user = User::factory()->contentTeam()->create();

    $this->actingAs($user)
        ->postJson(route('content.customer-media.uploads.complete', [$booking, $video]), ['parts' => []])
        ->assertOk()->assertJsonPath('media.status', 'READY');
    $this->patchJson(route('content.customer-media.update', [$booking, $video]), ['caption' => 'Meja customer'])
        ->assertOk();
    $this->patchJson(route('content.customer-media.status', [$booking, $video]), ['status' => 'HIDDEN'])
        ->assertOk()->assertJsonPath('media.status', 'HIDDEN');
    $this->putJson(route('content.customer-media.order', $booking), ['media_ids' => [$photo->id, $video->id]])
        ->assertOk();
    $this->deleteJson(route('content.customer-media.destroy', [$booking, $photo]))
        ->assertOk();

    expect($video->refresh()->caption)->toBe('Meja customer')
        ->and($video->sort_order)->toBe(2)
        ->and(GalleryMedia::query()->find($photo->id))->toBeNull()
        ->and(GalleryMediaDeletion::query()->where('media_uuid', $photo->uuid)->exists())->toBeTrue();
});

it('blocks non content users from customer media endpoints', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('content.customer-media.index'))
        ->assertForbidden();
});
