<?php

namespace App\Services;

use App\Enums\SlotStatus;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use Illuminate\Support\Facades\DB;

class SlotCapacityService
{
    private const TABLE_ROWS = ['A', 'F', 'B', 'G', 'D', 'H', 'E', 'J'];

    private const BASE_TABLE_NUMBERS = [18, 28, 38, 58, 68, 78, 88, 98, 108, 118, 128, 158, 168, 178, 188, 198, 208, 218, 228, 238, 258];

    private const PUBLIC_INCENSE_CAPACITY = 60;

    /** @return array{tables_created:int,incense_created:int} */
    public function sync(): array
    {
        return [
            'tables_created' => $this->syncTables(),
            'incense_created' => $this->syncIncense(),
        ];
    }

    public function syncTables(): int
    {
        return DB::transaction(function (): int {
            $created = 0;
            $order = 1;
            $extraRows = config('table_slots.extra_rows', []);
            $extraNumbers = $this->extraTableNumbers();
            $desiredSlots = [];

            foreach (self::TABLE_ROWS as $row) {
                $numbers = self::BASE_TABLE_NUMBERS;

                if (is_array($extraRows) && in_array($row, $extraRows, true)) {
                    $numbers = [...$numbers, ...$extraNumbers];
                }

                foreach ($numbers as $number) {
                    $desiredSlots[] = ['row' => $row, 'number' => $number];
                }
            }

            $existingSlots = TableSlot::query()->lockForUpdate()->get(['allocation_order']);

            if ($existingSlots->isNotEmpty()) {
                $minimumOrder = (int) $existingSlots->min('allocation_order');
                $temporaryFloor = max(
                    (int) $existingSlots->max('allocation_order'),
                    count($desiredSlots),
                ) + $existingSlots->count() + 1;

                TableSlot::query()->increment('allocation_order', $temporaryFloor - $minimumOrder);
            }

            $desiredCodes = [];

            foreach ($desiredSlots as $definition) {
                $code = $definition['row'].$definition['number'];
                $desiredCodes[] = $code;
                $slot = TableSlot::query()->firstOrCreate(['code' => $code], [
                    'row_code' => $definition['row'],
                    'number' => $definition['number'],
                    'allocation_order' => $order,
                    'status' => SlotStatus::Available,
                ]);

                if ($slot->wasRecentlyCreated) {
                    $created++;
                } else {
                    $slot->forceFill([
                        'row_code' => $definition['row'],
                        'number' => $definition['number'],
                        'allocation_order' => $order,
                    ])->save();
                }

                $order++;
            }

            foreach (TableSlot::query()->whereNotIn('code', $desiredCodes)->orderBy('allocation_order')->get() as $slot) {
                $slot->forceFill(['allocation_order' => $order++])->save();
            }

            return $created;
        });
    }

    public function syncIncense(): int
    {
        return DB::transaction(function (): int {
            $created = 0;
            $desiredNumbers = $this->incenseNumbers();
            $existingSlots = IncenseSlot::query()->lockForUpdate()->get(['allocation_order']);

            if ($existingSlots->isNotEmpty()) {
                $minimumOrder = (int) $existingSlots->min('allocation_order');
                $temporaryFloor = max(
                    (int) $existingSlots->max('allocation_order'),
                    count($desiredNumbers),
                ) + $existingSlots->count() + 1;

                IncenseSlot::query()->increment('allocation_order', $temporaryFloor - $minimumOrder);
            }

            foreach ($desiredNumbers as $index => $number) {
                $slot = IncenseSlot::query()->firstOrCreate(['number' => $number], [
                    'allocation_order' => $index + 1,
                    'status' => SlotStatus::Available,
                ]);

                if ($slot->wasRecentlyCreated) {
                    $created++;
                } else {
                    $slot->forceFill(['allocation_order' => $index + 1])->save();
                }
            }

            $order = count($desiredNumbers) + 1;

            foreach (IncenseSlot::query()->whereNotIn('number', $desiredNumbers)->orderBy('allocation_order')->get() as $slot) {
                $slot->forceFill(['allocation_order' => $order++])->save();
            }

            return $created;
        });
    }

    /** @return array<int, int> */
    private function extraTableNumbers(): array
    {
        $count = (int) config('table_slots.extra_columns', 0);

        return $count > 0
            ? collect(range(1, $count))->map(fn (int $position): int => 258 + ($position * 10))->all()
            : [];
    }

    /** @return array<int, int> */
    private function incenseNumbers(): array
    {
        $internalNumbers = array_map('intval', (array) config('internal_company.incense_numbers', [1, 2]));
        $numbers = [];
        $publicCount = 0;
        $number = 1;

        while ($publicCount < self::PUBLIC_INCENSE_CAPACITY || array_diff($internalNumbers, $numbers) !== []) {
            if (! str_contains((string) $number, '4') && $number !== 13) {
                $numbers[] = $number;

                if (! in_array($number, $internalNumbers, true)) {
                    $publicCount++;
                }
            }

            $number++;
        }

        return $numbers;
    }
}
