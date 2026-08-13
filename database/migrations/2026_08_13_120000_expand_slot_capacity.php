<?php

use App\Enums\SlotStatus;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use App\Services\SlotCapacityService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $capacityService = app(SlotCapacityService::class);

        if (TableSlot::query()->exists()) {
            $capacityService->syncTables();
        }

        if (IncenseSlot::query()->exists()) {
            $capacityService->syncIncense();
        }
    }

    public function down(): void
    {
        $hasAssignedExpansion = IncenseSlot::query()
            ->where('number', '>', 60)
            ->where(function ($query): void {
                $query->whereNot('status', SlotStatus::Available)
                    ->orWhereNotNull('booking_id');
            })
            ->exists() || TableSlot::query()
            ->where('number', '>', 258)
            ->where(function ($query): void {
                $query->whereNot('status', SlotStatus::Available)
                    ->orWhereNotNull('booking_id');
            })
            ->exists();

        if ($hasAssignedExpansion) {
            throw new RuntimeException('Rollback tidak aman: slot kapasitas tambahan sudah digunakan.');
        }

        IncenseSlot::query()->where('number', '>', 60)->delete();
        TableSlot::query()->where('number', '>', 258)->delete();
    }
};
