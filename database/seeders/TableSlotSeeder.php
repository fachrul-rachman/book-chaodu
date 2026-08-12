<?php

namespace Database\Seeders;

use App\Models\TableSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TableSlotSeeder extends Seeder
{
    public function run(): void
    {
        $rows = ['A', 'F', 'B', 'G', 'D', 'H', 'E', 'J'];
        $numbers = [18, 28, 38, 58, 68, 78, 88, 98, 108, 118, 128, 158, 168, 178, 188, 198, 208, 218, 228, 238, 258];

        DB::transaction(function () use ($rows, $numbers): void {
            TableSlot::query()->increment('allocation_order', 1000);

            $order = 1;

            foreach ($rows as $rowCode) {
                foreach ($numbers as $number) {
                    TableSlot::query()->updateOrCreate(
                        ['code' => $rowCode.$number],
                        [
                            'row_code' => $rowCode,
                            'number' => $number,
                            'allocation_order' => $order,
                        ],
                    );

                    $order++;
                }
            }
        });
    }
}
