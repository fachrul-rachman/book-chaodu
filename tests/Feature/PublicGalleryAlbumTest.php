<?php

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Enums\PackageCode;
use App\Models\Booking;
use App\Models\BookingMeal;
use App\Models\GalleryMedia;
use App\Models\Package;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-test');
    config()->set('gallery.event_name', 'Doa Bersama Chao Du');
    config()->set('gallery.event_date', '2026-09-20');
    config()->set('gallery.album_title', 'Kenangan dalam Kebersamaan');
    Storage::fake('gallery-test');
});

function publicAlbumBooking(array $attributes = []): Booking
{
    $package = Package::query()->firstOrCreate(['code' => PackageCode::Combo], [
        'name' => 'Combo',
        'description' => 'Paket combo untuk pengujian album publik.',
        'price' => 500000,
        'is_active' => true,
        'meal_quota' => 4,
        'requires_table' => true,
        'requires_incense' => true,
    ]);

    return Booking::query()->create(array_merge([
        'booking_number' => 'CD-ALBUM01',
        'idempotency_key' => (string) str()->uuid(),
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Combo->value,
        'package_name_snapshot' => 'Combo',
        'package_price_snapshot' => 500000,
        'customer_name' => 'Nama Customer Rahasia',
        'customer_phone' => '+628123456789',
        'customer_email' => 'rahasia@example.com',
        'attendee_count' => 4,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
    ], $attributes));
}

function publicAlbumMedia(array $attributes = []): GalleryMedia
{
    $uuid = (string) str()->uuid();

    return GalleryMedia::query()->create(array_merge([
        'uuid' => $uuid,
        'scope' => GalleryMediaScope::Global,
        'booking_id' => null,
        'media_type' => GalleryMediaType::Image,
        'status' => GalleryMediaStatus::Ready,
        'storage_disk' => 'gallery-test',
        'original_path' => "gallery/global/{$uuid}/original.jpg",
        'thumbnail_path' => "gallery/global/{$uuid}/thumbnail.webp",
        'original_filename' => 'dokumentasi.jpg',
        'stored_filename' => 'original.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size_bytes' => 1024,
        'caption' => 'Doa pembukaan',
        'published_at' => now(),
    ], $attributes));
}

it('opens an approved album by exact booking number with global and owned media only', function () {
    $booking = publicAlbumBooking();
    BookingMeal::query()->create([
        'booking_id' => $booking->id,
        'vegetarian_quantity' => 4,
        'non_vegetarian_quantity' => 0,
    ]);
    $otherBooking = publicAlbumBooking(['booking_number' => 'CD-ALBUM02']);
    $global = publicAlbumMedia(['sort_order' => 1]);
    $owned = publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $booking->id,
        'caption' => 'Dokumentasi meja customer',
        'original_path' => 'gallery/bookings/'.$booking->id.'/owned/original.jpg',
        'thumbnail_path' => 'gallery/bookings/'.$booking->id.'/owned/thumbnail.webp',
    ]);
    publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $otherBooking->id,
        'original_path' => 'gallery/bookings/'.$otherBooking->id.'/other/original.jpg',
        'thumbnail_path' => 'gallery/bookings/'.$otherBooking->id.'/other/thumbnail.webp',
    ]);
    publicAlbumMedia([
        'status' => GalleryMediaStatus::Hidden,
        'original_path' => 'gallery/global/hidden/original.jpg',
        'thumbnail_path' => 'gallery/global/hidden/thumbnail.webp',
    ]);
    publicAlbumMedia([
        'status' => GalleryMediaStatus::Failed,
        'original_path' => 'gallery/global/failed/original.jpg',
        'thumbnail_path' => 'gallery/global/failed/thumbnail.webp',
    ]);

    $response = $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]));

    $response
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Nama Customer Rahasia')
        ->assertDontSee('+628123456789')
        ->assertDontSee('rahasia@example.com')
        ->assertDontSee('gallery/global')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/gallery')
            ->where('album.bookingNumber', 'CD-ALBUM01')
            ->where('album.eventName', 'Doa Bersama Chao Du')
            ->where('album.eventDate', '20 September 2026')
            ->where('album.title', 'Kenangan dalam Kebersamaan')
            ->where('bookingDetails.customerName', 'Nama Customer Rahasia')
            ->where('bookingDetails.packageName', 'Combo')
            ->where('bookingDetails.vegetarianQuantity', 4)
            ->where('bookingDetails.nonVegetarianQuantity', 0)
            ->has('media', 2)
            ->where('media.0.id', $owned->id)
            ->where('media.0.scope', 'BOOKING')
            ->where('media.1.id', $global->id)
            ->where('media.1.scope', 'GLOBAL')
            ->where('media.0.previewUrl', route('public.gallery.media.preview', [
                'bookingNumber' => $booking->booking_number,
                'media' => $owned->id,
            ]))
            ->where('media.0.viewerUrl', route('public.gallery.media.viewer', [
                'bookingNumber' => $booking->booking_number,
                'media' => $owned->id,
            ]))
            ->missing('album.customerName')
            ->missing('bookingDetails.customerPhone')
            ->missing('bookingDetails.customerEmail')
            ->missing('media.0.originalPath')
            ->missing('media.0.storageDisk'));
});

it('shows customer media before global media and orders each group by newest publication time', function () {
    $booking = publicAlbumBooking();
    $ownedOlder = publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $booking->id,
        'sort_order' => 1,
        'published_at' => now()->subHours(4),
    ]);
    $ownedNewer = publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $booking->id,
        'sort_order' => 99,
        'published_at' => now()->subHours(3),
    ]);
    $globalOlder = publicAlbumMedia([
        'sort_order' => 1,
        'published_at' => now()->subHours(2),
    ]);
    $globalNewer = publicAlbumMedia([
        'sort_order' => 99,
        'published_at' => now()->subHour(),
    ]);

    $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('media.0.id', $ownedNewer->id)
            ->where('media.1.id', $ownedOlder->id)
            ->where('media.2.id', $globalNewer->id)
            ->where('media.3.id', $globalOlder->id));
});

it('serves a screen-sized image preview instead of the original in the viewer', function () {
    $booking = publicAlbumBooking();
    $image = publicAlbumMedia([
        'preview_path' => 'gallery/global/viewer-image/preview.webp',
        'original_path' => 'gallery/global/viewer-image/original.jpg',
        'thumbnail_path' => 'gallery/global/viewer-image/thumbnail.webp',
    ]);
    Storage::disk('gallery-test')->put($image->original_path, 'large-original');
    Storage::disk('gallery-test')->put($image->thumbnail_path, 'small-thumbnail');
    Storage::disk('gallery-test')->put($image->preview_path, 'screen-preview');

    $this->get(route('public.gallery.media.viewer', [
        'bookingNumber' => $booking->booking_number,
        'media' => $image->id,
    ]))->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Content-Disposition', 'inline; filename=media-'.$image->uuid.'.webp')
        ->assertStreamedContent('screen-preview');
});

it('streams video with byte range support and safe inline headers', function () {
    $booking = publicAlbumBooking();
    $bytes = '0123456789abcdef';
    $video = publicAlbumMedia([
        'media_type' => GalleryMediaType::Video,
        'original_path' => 'gallery/global/viewer-video/original.mp4',
        'thumbnail_path' => null,
        'original_filename' => 'acara.mp4',
        'stored_filename' => 'original.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'size_bytes' => strlen($bytes),
    ]);
    Storage::disk('gallery-test')->put($video->original_path, $bytes);

    $this->withHeader('Range', 'bytes=4-9')
        ->get(route('public.gallery.media.viewer', [
            'bookingNumber' => $booking->booking_number,
            'media' => $video->id,
        ]))
        ->assertStatus(206)
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes 4-9/16')
        ->assertHeader('Content-Length', '6')
        ->assertHeader('Content-Disposition', 'inline; filename=media-'.$video->uuid.'.mp4')
        ->assertStreamedContent('456789');

    $this->withHeader('Range', '')
        ->get(route('public.gallery.media.viewer', [
            'bookingNumber' => $booking->booking_number,
            'media' => $video->id,
        ]))->assertOk()
        ->assertHeader('Content-Length', '16')
        ->assertStreamedContent($bytes);

    $this->withHeader('Range', 'bytes=99-100')
        ->get(route('public.gallery.media.viewer', [
            'bookingNumber' => $booking->booking_number,
            'media' => $video->id,
        ]))
        ->assertStatus(416)
        ->assertHeader('Content-Range', 'bytes */16');
});

it('exposes a generated video thumbnail as its album preview', function () {
    $booking = publicAlbumBooking();
    $video = publicAlbumMedia([
        'media_type' => GalleryMediaType::Video,
        'original_path' => 'gallery/global/video-thumbnail/original.mp4',
        'thumbnail_path' => 'gallery/global/video-thumbnail/thumbnail.webp',
        'original_filename' => 'acara.mp4',
        'stored_filename' => 'original.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'width' => 1920,
        'height' => 1080,
    ]);
    Storage::disk('gallery-test')->put($video->thumbnail_path, 'video-thumbnail');

    $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('media.0.previewUrl', route('public.gallery.media.preview', [
                'bookingNumber' => $booking->booking_number,
                'media' => $video->id,
            ]))
            ->where('media.0.width', 1920)
            ->where('media.0.height', 1080));

    $this->get(route('public.gallery.media.preview', [
        'bookingNumber' => $booking->booking_number,
        'media' => $video->id,
    ]))->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertStreamedContent('video-thumbnail');
});

it('blocks viewer access to hidden media and media owned by another booking', function () {
    $bookingA = publicAlbumBooking();
    $bookingB = publicAlbumBooking(['booking_number' => 'CD-ALBUM02']);
    $ownedB = publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $bookingB->id,
        'original_path' => 'gallery/bookings/'.$bookingB->id.'/viewer/original.jpg',
        'thumbnail_path' => 'gallery/bookings/'.$bookingB->id.'/viewer/thumbnail.webp',
    ]);
    $hidden = publicAlbumMedia([
        'status' => GalleryMediaStatus::Hidden,
        'original_path' => 'gallery/global/viewer-hidden/original.jpg',
        'thumbnail_path' => 'gallery/global/viewer-hidden/thumbnail.webp',
    ]);

    foreach ([$ownedB, $hidden] as $forbidden) {
        $this->get(route('public.gallery.media.viewer', [
            'bookingNumber' => $bookingA->booking_number,
            'media' => $forbidden->id,
        ]))->assertNotFound();
    }
});

it('returns the same safe not found response for unavailable albums', function (BookingStatus $status) {
    $booking = publicAlbumBooking([
        'booking_number' => 'CD-NOT-PUBLIC',
        'status' => $status,
        'approved_at' => null,
    ]);

    $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
        ->assertNotFound()
        ->assertDontSee($booking->customer_name)
        ->assertDontSee($booking->customer_email);
})->with([
    'pending' => BookingStatus::Pending,
    'rejected' => BookingStatus::Rejected,
]);

it('uses an exact case-sensitive booking number and does not create an album row', function () {
    $booking = publicAlbumBooking();
    $before = Booking::query()->count();

    $this->get('/chaodu/'.strtolower($booking->booking_number))->assertNotFound();
    $this->get('/chaodu/TIDAK-ADA')->assertNotFound();
    $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('media', 0));

    expect(Booking::query()->count())->toBe($before);
});

it('delivers only active global media or media owned by the requested booking', function () {
    $bookingA = publicAlbumBooking();
    $bookingB = publicAlbumBooking(['booking_number' => 'CD-ALBUM02']);
    $global = publicAlbumMedia();
    $ownedA = publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $bookingA->id,
        'original_path' => 'gallery/bookings/'.$bookingA->id.'/a/original.jpg',
        'thumbnail_path' => 'gallery/bookings/'.$bookingA->id.'/a/thumbnail.webp',
    ]);
    $ownedB = publicAlbumMedia([
        'scope' => GalleryMediaScope::Booking,
        'booking_id' => $bookingB->id,
        'original_path' => 'gallery/bookings/'.$bookingB->id.'/b/original.jpg',
        'thumbnail_path' => 'gallery/bookings/'.$bookingB->id.'/b/thumbnail.webp',
    ]);
    $hidden = publicAlbumMedia([
        'status' => GalleryMediaStatus::Hidden,
        'original_path' => 'gallery/global/hidden-delivery/original.jpg',
        'thumbnail_path' => 'gallery/global/hidden-delivery/thumbnail.webp',
    ]);

    Storage::disk('gallery-test')->put($global->thumbnail_path, 'global-thumb');
    Storage::disk('gallery-test')->put($ownedA->thumbnail_path, 'owned-thumb');
    Storage::disk('gallery-test')->put($ownedB->thumbnail_path, 'other-thumb');
    Storage::disk('gallery-test')->put($hidden->thumbnail_path, 'hidden-thumb');

    $this->get(route('public.gallery.media.preview', [
        'bookingNumber' => $bookingA->booking_number,
        'media' => $global->id,
    ]))->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertStreamedContent('global-thumb');

    $this->get(route('public.gallery.media.preview', [
        'bookingNumber' => $bookingA->booking_number,
        'media' => $ownedA->id,
    ]))->assertOk()->assertStreamedContent('owned-thumb');

    foreach ([$ownedB, $hidden] as $forbidden) {
        $this->get(route('public.gallery.media.preview', [
            'bookingNumber' => $bookingA->booking_number,
            'media' => $forbidden->id,
        ]))->assertNotFound();
    }
});

it('protects album and media delivery routes with separate public rate limits', function () {
    expect(Route::getRoutes()->getByName('public.gallery.show')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-album')
        ->and(Route::getRoutes()->getByName('public.gallery.media.preview')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-media')
        ->and(Route::getRoutes()->getByName('public.gallery.media.viewer')?->gatherMiddleware())
        ->toContain('throttle:public-gallery-media');
});
