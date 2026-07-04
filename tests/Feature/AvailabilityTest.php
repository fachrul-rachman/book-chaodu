<?php

declare(strict_types=1);

use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\SlotAllocator;
use Database\Seeders\IncenseSlotSeeder;
use Database\Seeders\TableSlotSeeder;

it('seeds table slots in the required order', function () {
    $this->seed(TableSlotSeeder::class);

    $codes = TableSlot::query()
        ->orderBy('allocation_order')
        ->limit(10)
        ->pluck('code')
        ->all();

    expect($codes)->toBe([
        'A18',
        'F18',
        'B18',
        'G18',
        'D18',
        'H18',
        'E18',
        'J18',
        'A28',
        'F28',
    ]);
});

it('seeds incense slots with the valid numbers only', function () {
    $this->seed(IncenseSlotSeeder::class);

    expect(IncenseSlot::query()->count())->toBe(44)
        ->and(IncenseSlot::query()->pluck('number')->all())->toContain(1, 12, 60)
        ->and(IncenseSlot::query()->pluck('number')->all())->not->toContain(4, 13, 14, 24, 40);
});

it('returns remaining counts and package availability', function () {
    $this->seed();

    $summary = app(AvailabilityService::class)->summary();

    expect($summary['table_remaining'])->toBe(165)
        ->and($summary['incense_remaining'])->toBe(42)
        ->and(collect($summary['packages'])->keyBy('code')->get(PackageCode::Combo->value)['available'])->toBeTrue();
});

it('reserves the first table slot in order and can release it back', function () {
    $this->seed(TableSlotSeeder::class);
    $allocator = app(SlotAllocator::class);

    $first = $allocator->reserveForPackage(PackageCode::Prayer, 101);
    expect($first['table_code'])->toBe('F18');

    $allocator->releaseByBookingId(101);

    $second = $allocator->reserveForPackage(PackageCode::Prayer, 102);
    expect($second['table_code'])->toBe('F18');
});

it('reserves table and incense together for combo', function () {
    $this->seed();
    $allocator = app(SlotAllocator::class);

    $result = $allocator->reserveForPackage(PackageCode::Combo, 201);

    expect($result)->toBe([
        'table_code' => 'F18',
        'incense_number' => 3,
    ]);
});

it('does not reserve only one side when combo stock is incomplete', function () {
    $this->seed();
    $allocator = app(SlotAllocator::class);

    IncenseSlot::query()->update([
        'status' => SlotStatus::Reserved->value,
    ]);

    expect(fn () => $allocator->reserveForPackage(PackageCode::Combo, 300))
        ->toThrow(SlotUnavailableException::class);

    expect(TableSlot::query()->where('booking_id', 300)->exists())->toBeFalse()
        ->and(IncenseSlot::query()->where('booking_id', 300)->exists())->toBeFalse();
});

it('shows public availability data', function () {
    $this->seed();

    $this->getJson(route('api.public.availability.show'))
        ->assertOk()
        ->assertJsonPath('table_remaining', 165)
        ->assertJsonPath('incense_remaining', 42);
});

it('shows remaining counts on the admin home page', function () {
    $this->seed();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('"table_remaining":165', false)
        ->assertSee('"incense_remaining":42', false);
});
