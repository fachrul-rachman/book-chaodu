<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\InternalCompanySlotService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    Storage::fake('prayer-paper-files');

    $this->seed();
});

function internalCompanyPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'customer_name' => 'Internal Satu',
        'customer_phone' => '+6281234567890',
        'customer_email' => 'internal.booking@gmail.com',
        'attendee_count' => '2',
        'vegetarian_quantity' => '1',
        'non_vegetarian_quantity' => '1',
        'deceased_names' => [
            [
                'position' => 1,
                'indonesian_name' => 'Nama Satu',
                'mandarin_name' => '',
            ],
            [
                'position' => 2,
                'indonesian_name' => '',
                'mandarin_name' => '',
            ],
        ],
        'incense_name' => [
            'position' => 1,
            'indonesian_name' => 'Keluarga Internal',
            'mandarin_name' => '',
        ],
    ], $overrides);
}

it('creates internal company booking with direct approved status and special slots', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.internal-company-bookings.store'), internalCompanyPayload())
        ->assertRedirect();

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Approved)
        ->and($booking->referral_source)->toBe(app(InternalCompanySlotService::class)->sourceValue())
        ->and($booking->package_code_snapshot)->toBe(PackageCode::Combo->value)
        ->and($booking->approved_by)->toBe($admin->id)
        ->and($booking->tableSlots()->value('code'))->toBe('A18')
        ->and($booking->incenseSlots()->value('number'))->toBe(1)
        ->and($booking->tableSlots()->value('status'))->toBe(SlotStatus::Assigned)
        ->and($booking->incenseSlots()->value('status'))->toBe(SlotStatus::Assigned)
        ->and($booking->prayer_paper_status)->toBe(PrayerPaperStatus::Ready);
});

it('prevents public booking from taking internal company slots', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.internal-company-bookings.store'), internalCompanyPayload())
        ->assertRedirect();

    $booking = Booking::query()->firstOrFail();

    expect(TableSlot::query()->where('code', 'A18')->value('booking_id'))->toBe($booking->id)
        ->and(TableSlot::query()->where('code', 'A28')->value('booking_id'))->toBeNull()
        ->and(TableSlot::query()->where('code', 'A38')->value('booking_id'))->toBeNull();
});

it('shows internal company booking as blue layout item', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.internal-company-bookings.store'), internalCompanyPayload())
        ->assertRedirect();

    $response = $this->actingAs($admin)->get(route('admin.table-layout'));
    $rows = $response->viewData('page')['props']['rows'];
    $rowA = collect($rows)->firstWhere('row_code', 'A');
    $slotA18 = collect($rowA['slots'])->firstWhere('code', 'A18');

    expect($slotA18['is_internal_company'])->toBeTrue()
        ->and($slotA18['booking_number'])->not->toBeNull();
});
