<?php

namespace App\Services;

use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SlotAllocator
{
    /**
     * @return array{table_code:string|null, incense_number:int|null}
     */
    public function reserveForPackage(PackageCode $packageCode, int $bookingId): array
    {
        return DB::transaction(function () use ($packageCode, $bookingId): array {
            $tableSlot = null;
            $incenseSlot = null;

            if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
                $tableSlot = $this->lockFirstAvailableTableSlot();

                if (! $tableSlot) {
                    throw new SlotUnavailableException('Nomor meja sudah habis.');
                }

                $this->markReserved($tableSlot, $bookingId);
            }

            if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
                $incenseSlot = $this->lockFirstAvailableIncenseSlot();

                if (! $incenseSlot) {
                    throw new SlotUnavailableException('Nomor hio sudah habis.');
                }

                $this->markReserved($incenseSlot, $bookingId);
            }

            return [
                'table_code' => $tableSlot?->code,
                'incense_number' => $incenseSlot?->number,
            ];
        });
    }

    public function assignByBookingId(int $bookingId): void
    {
        DB::transaction(function () use ($bookingId): void {
            TableSlot::query()
                ->where('booking_id', $bookingId)
                ->where('status', SlotStatus::Reserved)
                ->update(['status' => SlotStatus::Assigned->value]);

            IncenseSlot::query()
                ->where('booking_id', $bookingId)
                ->where('status', SlotStatus::Reserved)
                ->update(['status' => SlotStatus::Assigned->value]);
        });
    }

    public function releaseByBookingId(int $bookingId): void
    {
        DB::transaction(function () use ($bookingId): void {
            TableSlot::query()
                ->where('booking_id', $bookingId)
                ->update([
                    'status' => SlotStatus::Available->value,
                    'booking_id' => null,
                ]);

            IncenseSlot::query()
                ->where('booking_id', $bookingId)
                ->update([
                    'status' => SlotStatus::Available->value,
                    'booking_id' => null,
                ]);
        });
    }

    public function replaceTableSlot(int $bookingId, int $newSlotId): void
    {
        DB::transaction(function () use ($bookingId, $newSlotId): void {
            $currentSlot = TableSlot::query()
                ->where('booking_id', $bookingId)
                ->lockForUpdate()
                ->first();

            $newSlot = TableSlot::query()
                ->whereKey($newSlotId)
                ->lockForUpdate()
                ->first();

            if (! $currentSlot || ! $newSlot) {
                throw new SlotUnavailableException('Nomor meja tidak ditemukan.');
            }

            if ($currentSlot->id === $newSlot->id) {
                return;
            }

            if ($newSlot->status !== SlotStatus::Available) {
                throw new SlotUnavailableException('Nomor meja pengganti sudah dipakai.');
            }

            $currentSlot->forceFill([
                'status' => SlotStatus::Available,
                'booking_id' => null,
            ])->save();

            $newSlot->forceFill([
                'status' => SlotStatus::Reserved,
                'booking_id' => $bookingId,
            ])->save();
        });
    }

    public function replaceIncenseSlot(int $bookingId, int $newSlotId): void
    {
        DB::transaction(function () use ($bookingId, $newSlotId): void {
            $currentSlot = IncenseSlot::query()
                ->where('booking_id', $bookingId)
                ->lockForUpdate()
                ->first();

            $newSlot = IncenseSlot::query()
                ->whereKey($newSlotId)
                ->lockForUpdate()
                ->first();

            if (! $currentSlot || ! $newSlot) {
                throw new SlotUnavailableException('Nomor hio tidak ditemukan.');
            }

            if ($currentSlot->id === $newSlot->id) {
                return;
            }

            if ($newSlot->status !== SlotStatus::Available) {
                throw new SlotUnavailableException('Nomor hio pengganti sudah dipakai.');
            }

            $currentSlot->forceFill([
                'status' => SlotStatus::Available,
                'booking_id' => null,
            ])->save();

            $newSlot->forceFill([
                'status' => SlotStatus::Reserved,
                'booking_id' => $bookingId,
            ])->save();
        });
    }

    private function lockFirstAvailableTableSlot(): ?TableSlot
    {
        return TableSlot::query()
            ->where('status', SlotStatus::Available)
            ->orderBy('allocation_order')
            ->lock('FOR UPDATE SKIP LOCKED')
            ->first();
    }

    private function lockFirstAvailableIncenseSlot(): ?IncenseSlot
    {
        return IncenseSlot::query()
            ->where('status', SlotStatus::Available)
            ->orderBy('allocation_order')
            ->lock('FOR UPDATE SKIP LOCKED')
            ->first();
    }

    private function markReserved(Model $slot, int $bookingId): void
    {
        $slot->forceFill([
            'status' => SlotStatus::Reserved,
            'booking_id' => $bookingId,
        ])->save();
    }
}
