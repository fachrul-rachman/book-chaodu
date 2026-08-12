<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->reorder(rowFirst: true);
    }

    public function down(): void
    {
        $this->reorder(rowFirst: false);
    }

    private function reorder(bool $rowFirst): void
    {
        $rows = ['A', 'F', 'B', 'G', 'D', 'H', 'E', 'J'];
        $numbers = [18, 28, 38, 58, 68, 78, 88, 98, 108, 118, 128, 158, 168, 178, 188, 198, 208, 218, 228, 238, 258];

        DB::transaction(function () use ($rows, $numbers, $rowFirst): void {
            DB::table('table_slots')->increment('allocation_order', 1000);

            $order = 1;
            $outerValues = $rowFirst ? $rows : $numbers;
            $innerValues = $rowFirst ? $numbers : $rows;

            foreach ($outerValues as $outerValue) {
                foreach ($innerValues as $innerValue) {
                    $rowCode = $rowFirst ? $outerValue : $innerValue;
                    $number = $rowFirst ? $innerValue : $outerValue;

                    DB::table('table_slots')
                        ->where('code', $rowCode.$number)
                        ->update(['allocation_order' => $order]);

                    $order++;
                }
            }
        });
    }
};
