<?php

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-test');
    config()->set('gallery.event_name', 'Chao Du dari konfigurasi');
    config()->set('gallery.event_date', '2026-09-20');
    config()->set('gallery.album_title', 'Album dari konfigurasi');
    Storage::fake('gallery-test');
});

function gallerySettingsBooking(string $bookingNumber): Booking
{
    $package = Package::query()->firstOrCreate(['code' => PackageCode::Combo], [
        'name' => 'Combo',
        'description' => 'Paket pengujian pengaturan galeri.',
        'price' => 500000,
        'is_active' => true,
        'meal_quota' => 4,
        'requires_table' => true,
        'requires_incense' => true,
    ]);

    return Booking::query()->create([
        'booking_number' => $bookingNumber,
        'idempotency_key' => (string) str()->uuid(),
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Combo->value,
        'package_name_snapshot' => 'Combo',
        'package_price_snapshot' => 500000,
        'customer_name' => 'Customer Pengujian',
        'customer_phone' => '+628123456789',
        'customer_email' => strtolower($bookingNumber).'@example.com',
        'attendee_count' => 4,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
    ]);
}

it('lets only admins open and update the single event gallery settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.gallery-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/gallery-settings/edit')
            ->where('settings.event_name', 'Chao Du dari konfigurasi')
            ->where('settings.event_date', '2026-09-20')
            ->where('settings.album_title', 'Album dari konfigurasi')
            ->where('settings.empty_state_text', 'Dokumentasi acara belum tersedia.')
            ->where('wallpaper_width', 1920)
            ->where('wallpaper_height', 800));

    $this->actingAs($admin)
        ->post(route('admin.gallery-settings.update'), [
            'event_name' => 'Doa Bersama Chao Du 2026',
            'event_date' => '2026-10-18',
            'album_title' => 'Kenangan dalam Kebersamaan',
            'empty_state_text' => 'Tim dokumentasi sedang menyiapkan album Anda.',
        ])
        ->assertRedirect(route('admin.gallery-settings.edit'))
        ->assertSessionHas('status');

    expect(AppSetting::getMany([
        'gallery_event_name',
        'gallery_event_date',
        'gallery_album_title',
        'gallery_empty_state_text',
    ]))->toMatchArray([
        'gallery_event_name' => 'Doa Bersama Chao Du 2026',
        'gallery_event_date' => '2026-10-18',
        'gallery_album_title' => 'Kenangan dalam Kebersamaan',
        'gallery_empty_state_text' => 'Tim dokumentasi sedang menyiapkan album Anda.',
    ]);

    $contentTeam = User::factory()->contentTeam()->create();

    $this->actingAs($contentTeam)
        ->get(route('admin.gallery-settings.edit'))
        ->assertForbidden();
    $this->actingAs($contentTeam)
        ->post(route('admin.gallery-settings.update'), [
            'event_name' => 'Tidak boleh berubah',
            'event_date' => '2026-11-01',
            'album_title' => 'Tidak boleh berubah',
            'empty_state_text' => 'Tidak boleh berubah',
        ])
        ->assertForbidden();
});

it('stores a validated private wallpaper and removes the replaced object', function () {
    $admin = User::factory()->admin()->create();
    Storage::disk('gallery-test')->put('gallery/settings/wallpaper-lama.jpg', 'old');
    AppSetting::putMany([
        'gallery_wallpaper_path' => 'gallery/settings/wallpaper-lama.jpg',
        'gallery_wallpaper_mime_type' => 'image/jpeg',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.gallery-settings.update'), [
            'event_name' => 'Chao Du',
            'event_date' => '2026-10-18',
            'album_title' => 'Album Dokumentasi',
            'empty_state_text' => 'Dokumentasi belum tersedia.',
            'wallpaper' => UploadedFile::fake()->image('wallpaper.webp', 1920, 800)->size(2048),
        ])
        ->assertRedirect(route('admin.gallery-settings.edit'));

    $settings = AppSetting::getMany([
        'gallery_wallpaper_path',
        'gallery_wallpaper_mime_type',
    ]);

    expect($settings['gallery_wallpaper_path'])->not->toBe('gallery/settings/wallpaper-lama.jpg')
        ->and($settings['gallery_wallpaper_mime_type'])->toBe('image/webp');
    Storage::disk('gallery-test')->assertExists((string) $settings['gallery_wallpaper_path']);
    Storage::disk('gallery-test')->assertMissing('gallery/settings/wallpaper-lama.jpg');
});

it('rejects an invalid or oversized wallpaper without changing the saved wallpaper', function (UploadedFile $wallpaper) {
    $admin = User::factory()->admin()->create();
    AppSetting::putMany(['gallery_wallpaper_path' => 'gallery/settings/current.jpg']);

    $this->actingAs($admin)
        ->post(route('admin.gallery-settings.update'), [
            'event_name' => 'Chao Du',
            'event_date' => '2026-10-18',
            'album_title' => 'Album Dokumentasi',
            'empty_state_text' => 'Dokumentasi belum tersedia.',
            'wallpaper' => $wallpaper,
        ])
        ->assertSessionHasErrors('wallpaper');

    expect(AppSetting::query()->where('key', 'gallery_wallpaper_path')->value('value'))
        ->toBe('gallery/settings/current.jpg');
})->with([
    'bukan gambar' => fn () => UploadedFile::fake()->create('wallpaper.pdf', 100, 'application/pdf'),
    'lebih dari batas foto galeri' => fn () => UploadedFile::fake()->image('wallpaper.jpg')->size(30 * 1024 + 1),
    'dimensi bukan 1920 x 800' => fn () => UploadedFile::fake()->image('wallpaper.jpg', 1920, 801)->size(2048),
]);

it('applies the same single event identity to existing and new approved bookings', function () {
    $existing = gallerySettingsBooking('CD-SETTING-OLD');
    AppSetting::putMany([
        'gallery_event_name' => 'Doa Bersama 2026',
        'gallery_event_date' => '2026-10-18',
        'gallery_album_title' => 'Album Keluarga Chao Du',
        'gallery_empty_state_text' => 'Foto dan video sedang disiapkan.',
        'gallery_wallpaper_path' => 'gallery/settings/cover.webp',
        'gallery_wallpaper_mime_type' => 'image/webp',
    ]);
    $new = gallerySettingsBooking('CD-SETTING-NEW');

    foreach ([$existing, $new] as $booking) {
        $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('album.eventName', 'Doa Bersama 2026')
                ->where('album.eventDate', '18 Oktober 2026')
                ->where('album.title', 'Album Keluarga Chao Du')
                ->where('album.emptyStateText', 'Foto dan video sedang disiapkan.')
                ->where('album.wallpaperUrl', route('public.gallery.wallpaper', [
                    'bookingNumber' => $booking->booking_number,
                ])));
    }

    Storage::disk('gallery-test')->put('gallery/settings/cover.webp', 'wallpaper-bytes');
    $this->get(route('public.gallery.wallpaper', ['bookingNumber' => $existing->booking_number]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertStreamedContent('wallpaper-bytes');
});
