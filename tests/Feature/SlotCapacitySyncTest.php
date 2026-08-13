<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Models\TableSlot;
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
});

it('preserves existing extra tables and assignments when the configured count is lowered', function () {
    config()->set('table_slots.extra_columns', 2);
    $this->seed(TableSlotSeeder::class);
    TableSlot::query()->where('code', 'A278')->update(['status' => SlotStatus::Reserved]);

    config()->set('table_slots.extra_columns', 0);
    $this->seed(TableSlotSeeder::class);
    $slot = TableSlot::query()->where('code', 'A278')->firstOrFail();

    expect($slot->status)->toBe(SlotStatus::Reserved)
        ->and($slot->isTemporarilyClosed())->toBeTrue();
});

it('provides an idempotent production command for syncing slot capacity', function () {
    config()->set('table_slots.extra_columns', 2);

    expect(Artisan::call('slots:sync-capacity'))->toBe(Command::SUCCESS)
        ->and(Artisan::call('slots:sync-capacity'))->toBe(Command::SUCCESS)
        ->and(TableSlot::query()->where('code', 'A278')->exists())->toBeTrue();
});
