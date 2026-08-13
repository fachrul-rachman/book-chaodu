<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaStatus;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\Package;
use App\Models\TableSlot;
use App\Services\TableLayoutGallerySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'table-layout-gallery');
    config()->set('table_slots.show_closed_slots', false);
    Storage::fake('table-layout-gallery');
    $this->seed();
});

function tableLayoutBooking(string $number, BookingStatus $status = BookingStatus::Approved): Booking
{
    $package = Package::query()->where('code', PackageCode::Prayer)->firstOrFail();

    return Booking::query()->create([
        'booking_number' => $number,
        'idempotency_key' => 'table-layout-'.$number,
        'package_id' => $package->id,
        'package_code_snapshot' => $package->code->value,
        'package_name_snapshot' => $package->name,
        'package_price_snapshot' => $package->price ?? 0,
        'customer_name' => 'Customer Denah',
        'customer_phone' => '+628123456789',
        'customer_email' => 'denah@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'status' => $status,
        'approved_at' => $status === BookingStatus::Approved ? now() : null,
    ]);
}

it('stores one personalized table layout image in an approved booking gallery idempotently', function () {
    $booking = tableLayoutBooking('CD-LAYOUT-IMAGE');
    TableSlot::query()->where('code', 'A58')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $booking->id,
    ]);

    $service = app(TableLayoutGallerySyncService::class);
    expect($service->syncSafely($booking))->toBeTrue();
    $first = GalleryMedia::query()->where('source_table_layout_booking_id', $booking->id)->firstOrFail();
    $service->syncForBooking($booking);
    $media = $first->fresh();

    expect($media?->id)->toBe($first->id)
        ->and($media?->status)->toBe(GalleryMediaStatus::Ready)
        ->and($media?->caption)->toBe('Denah Meja Anda: A58')
        ->and($media?->width)->toBe(1400)
        ->and($media?->height)->toBe(1000)
        ->and(GalleryMedia::query()->where('source_table_layout_booking_id', $booking->id)->count())->toBe(1);

    Storage::disk('table-layout-gallery')->assertExists((string) $media?->original_path);
    $dimensions = getimagesizefromstring(Storage::disk('table-layout-gallery')->get((string) $media?->original_path));
    expect($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBe(1400)
        ->and($dimensions[1])->toBe(1000);
});

it('does not create a table layout image for a booking without a table', function () {
    $booking = tableLayoutBooking('CD-WITHOUT-TABLE');

    app(TableLayoutGallerySyncService::class)->syncForBooking($booking);

    expect(GalleryMedia::query()->where('booking_id', $booking->id)->exists())->toBeFalse();
});

it('backfills table layout images only for approved bookings with a table', function () {
    $approved = tableLayoutBooking('CD-LAYOUT-OLD');
    $pending = tableLayoutBooking('CD-LAYOUT-PENDING', BookingStatus::Pending);
    TableSlot::query()->where('code', 'A58')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $approved->id,
    ]);
    TableSlot::query()->where('code', 'A68')->update([
        'status' => SlotStatus::Reserved,
        'booking_id' => $pending->id,
    ]);

    expect(Artisan::call('gallery:sync-table-layouts'))->toBe(Command::SUCCESS)
        ->and(GalleryMedia::query()->where('source_table_layout_booking_id', $approved->id)->exists())->toBeTrue()
        ->and(GalleryMedia::query()->where('source_table_layout_booking_id', $pending->id)->exists())->toBeFalse();
});
