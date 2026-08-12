<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
use App\Models\TableSlot;
use Database\Seeders\TableSlotSeeder;

it('reorders existing table slots by row without changing their assignment', function () {
    $migration = require database_path('migrations/2026_08_12_090000_reorder_table_slots_by_row.php');

    $this->seed(TableSlotSeeder::class);
    $migration->down();

    TableSlot::query()->where('code', 'A38')->update([
        'status' => SlotStatus::Reserved->value,
        'booking_id' => 999,
    ]);

    $migration->up();

    expect(TableSlot::query()->orderBy('allocation_order')->limit(4)->pluck('code')->all())
        ->toBe(['A18', 'A28', 'A38', 'A58'])
        ->and(TableSlot::query()->where('code', 'A38')->value('status'))->toBe(SlotStatus::Reserved)
        ->and(TableSlot::query()->where('code', 'A38')->value('booking_id'))->toBe(999);
});
