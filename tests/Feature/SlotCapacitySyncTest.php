<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use App\Services\AvailabilityService;
use Database\Seeders\IncenseSlotSeeder;
use Database\Seeders\TableSlotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

it('creates the configured number of extra columns only for the six enabled rows', function () {
    config()->set('table_slots.extra_columns', 2);

    $this->seed(TableSlotSeeder::class);

    foreach (['A', 'B', 'D', 'F', 'G', 'H'] as $row) {
        expect(TableSlot::query()->whereIn('code', [$row.'268', $row.'278'])->count())->toBe(2);
    }

    expect(TableSlot::query()->whereIn('code', ['E268', 'E278', 'J268', 'J278'])->exists())->toBeFalse();
    expect(app(AvailabilityService::class)->summary()['table_remaining'])->toBe(178);
});

it('preserves existing extra tables and assignments when the configured count is lowered', function () {
    config()->set('table_slots.extra_columns', 2);
    $this->seed(TableSlotSeeder::class);
    TableSlot::query()->where('code', 'A278')->update(['status' => SlotStatus::Reserved]);

    config()->set('table_slots.extra_columns', 0);
    $this->seed(TableSlotSeeder::class);
    $slot = TableSlot::query()->where('code', 'A278')->firstOrFail();

    expect($slot->status)->toBe(SlotStatus::Reserved)
        ->and($slot->isTemporarilyClosed())->toBeTrue()
        ->and(TableSlot::query()->notTemporarilyClosed()->where('code', 'A278')->exists())->toBeFalse();
});

it('provides an idempotent production command for syncing slot capacity', function () {
    config()->set('table_slots.extra_columns', 2);

    expect(Artisan::call('slots:sync-capacity'))->toBe(Command::SUCCESS)
        ->and(Artisan::call('slots:sync-capacity'))->toBe(Command::SUCCESS)
        ->and(TableSlot::query()->where('code', 'A278')->exists())->toBeTrue();
});

it('reverses unused expansion but refuses to remove a slot that is already used', function () {
    $migration = require database_path('migrations/2026_08_13_120000_expand_slot_capacity.php');
    config()->set('table_slots.extra_columns', 0);
    $this->seed(TableSlotSeeder::class);
    $this->seed(IncenseSlotSeeder::class);
    IncenseSlot::query()->where('number', '>', 60)->delete();
    config()->set('table_slots.extra_columns', 2);
    $migration->up();
    TableSlot::query()->where('code', 'A278')->update(['status' => SlotStatus::Reserved]);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    TableSlot::query()->where('code', 'A278')->update(['status' => SlotStatus::Available]);
    $migration->down();

    expect(TableSlot::query()->where('number', '>', 258)->exists())->toBeFalse()
        ->and(IncenseSlot::query()->where('number', '>', 60)->exists())->toBeFalse();
});
