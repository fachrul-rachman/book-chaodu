<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\PrayerPaperRenderer;
use App\Services\SlotAllocator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase5.enabled', true);
    config()->set('phase5.storage_disk', 'internal-prayer-papers');
    config()->set('gallery.storage_disk', 'internal-gallery');
    Storage::fake('internal-prayer-papers');
    Storage::fake('internal-gallery');
    $this->seed();
});

it('creates a minimal A18 internal booking with its fixed incense pair and gallery papers', function () {
    $image = UploadedFile::fake()->image('paper.png', 900, 1400)->getContent();
    $renderer = Mockery::mock(PrayerPaperRenderer::class);
    $renderer->shouldReceive('render')->times(3)->andReturn([
        'content' => $image,
        'content_type' => 'image/png',
        'extension' => 'png',
    ]);
    app()->instance(PrayerPaperRenderer::class, $renderer);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.internal-company-bookings.store'), [
        'table_code' => 'A18',
        'customer_name' => 'Amin Supriyadi Liu',
        'deceased_names' => [
            ['position' => 1, 'mandarin_name' => '李樹華'],
            ['position' => 2, 'mandarin_name' => '劉梅清'],
        ],
        'incense_name' => ['position' => 1, 'mandarin_name' => '李鴻銘闔家'],
    ])->assertRedirect();

    $booking = Booking::query()->where('customer_name', 'Amin Supriyadi Liu')->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Approved)
        ->and($booking->customer_phone)->toBeNull()
        ->and($booking->customer_email)->toBeNull()
        ->and($booking->attendee_count)->toBeNull()
        ->and((float) $booking->package_price_snapshot)->toBe(0.0)
        ->and($booking->meal)->toBeNull()
        ->and($booking->payment)->toBeNull()
        ->and($booking->tableSlots()->value('code'))->toBe('A18')
        ->and($booking->incenseSlots()->value('number'))->toBe(1)
        ->and($booking->prayerPapers()->count())->toBe(3)
        ->and(GalleryMedia::query()->where('booking_id', $booking->id)->count())->toBe(4)
        ->and(GalleryMedia::query()->where('source_table_layout_booking_id', $booking->id)->exists())->toBeTrue();
});

it('pairs A28 with incense 2 even when A18 has not been booked', function () {
    $image = UploadedFile::fake()->image('paper.png', 900, 1400)->getContent();
    $renderer = Mockery::mock(PrayerPaperRenderer::class);
    $renderer->shouldReceive('render')->times(2)->andReturn([
        'content' => $image,
        'content_type' => 'image/png',
        'extension' => 'png',
    ]);
    app()->instance(PrayerPaperRenderer::class, $renderer);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.internal-company-bookings.store'), [
        'table_code' => 'A28',
        'customer_name' => 'PT. Alam Hijau Lestari',
        'deceased_names' => [
            ['position' => 1, 'indonesian_name' => 'Edmund Kwong'],
        ],
        'incense_name' => ['position' => 1, 'indonesian_name' => 'PT. Alam Hijau Lestari'],
    ])->assertRedirect();

    $booking = Booking::query()->where('customer_name', 'PT. Alam Hijau Lestari')->firstOrFail();

    expect($booking->tableSlots()->value('code'))->toBe('A28')
        ->and($booking->incenseSlots()->value('number'))->toBe(2)
        ->and(TableSlot::query()->where('code', 'A18')->value('booking_id'))->toBeNull();
});

it('rejects unavailable or non-internal table choices without creating a booking', function () {
    $admin = User::factory()->admin()->create();
    $payload = [
        'table_code' => 'F18',
        'customer_name' => 'Internal Salah',
        'deceased_names' => [['position' => 1, 'indonesian_name' => 'Nama Doa']],
        'incense_name' => ['position' => 1, 'indonesian_name' => 'Nama Hio'],
    ];

    $this->actingAs($admin)
        ->post(route('admin.internal-company-bookings.store'), $payload)
        ->assertSessionHasErrors('table_code');

    expect(Booking::query()->where('customer_name', 'Internal Salah')->exists())->toBeFalse();
});

it('keeps internal company slots unavailable for public allocation', function () {
    $bookingId = 999;

    app(SlotAllocator::class)->reserveForPackage(PackageCode::Combo, $bookingId);

    expect(TableSlot::query()->where('booking_id', $bookingId)->value('code'))->toBe('A38')
        ->and(IncenseSlot::query()->where('booking_id', $bookingId)->value('number'))->toBe(3)
        ->and(TableSlot::query()->where('code', 'A18')->value('booking_id'))->toBeNull()
        ->and(IncenseSlot::query()->where('number', 1)->value('booking_id'))->toBeNull();
});

it('shows internal company table slots as blue items in layout', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.table-layout'));
    $rows = $response->viewData('page')['props']['rows'];
    $rowA = collect($rows)->firstWhere('row_code', 'A');
    $slotA18 = collect($rowA['slots'])->firstWhere('code', 'A18');

    expect($slotA18['is_internal_company'])->toBeTrue()
        ->and($slotA18['booking_number'])->toBeNull()
        ->and($slotA18['status'])->toBe(SlotStatus::Available->value);
});

it('shows internal company rows in reports', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'finance',
    ]));

    $rows = collect($response->viewData('page')['props']['finance']['rows']);

    expect($rows->pluck('booking_number')->all())->toContain(
        'INTERNAL-A18',
        'INTERNAL-A28',
        'INTERNAL-HIO-1',
        'INTERNAL-HIO-2',
    )
        ->not->toContain('INTERNAL-A38')
        ->and($rows->firstWhere('booking_number', 'INTERNAL-A18')['customer_name'])->toBe('Internal Perusahaan')
        ->and($rows->firstWhere('booking_number', 'INTERNAL-HIO-1')['package_name'])->toBe('Hio Internal');
});
