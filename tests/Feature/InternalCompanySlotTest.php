<?php

declare(strict_types=1);

use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\SlotAllocator;

beforeEach(function () {
    $this->seed();
});

it('keeps internal company slots unavailable for public allocation', function () {
    $bookingId = 999;

    app(SlotAllocator::class)->reserveForPackage(PackageCode::Combo, $bookingId);

    expect(TableSlot::query()->where('booking_id', $bookingId)->value('code'))->toBe('F18')
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
        'INTERNAL-A38',
        'INTERNAL-HIO-1',
        'INTERNAL-HIO-2',
    )
        ->and($rows->firstWhere('booking_number', 'INTERNAL-A18')['customer_name'])->toBe('Internal Perusahaan')
        ->and($rows->firstWhere('booking_number', 'INTERNAL-HIO-1')['package_name'])->toBe('Hio Internal');
});
