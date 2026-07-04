<?php

namespace App\Services;

use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\IncenseSlot;
use App\Models\Package;
use App\Models\TableSlot;

class AvailabilityService
{
    public function __construct(
        private readonly InternalCompanySlotService $internalCompanySlotService,
    ) {}

    /**
     * @return array{
     *     table_remaining:int,
     *     incense_remaining:int,
     *     packages: array<int, array{
     *         code:string,
     *         available:bool,
     *         reason:string|null
     *     }>
     * }
     */
    public function summary(): array
    {
        $tableRemaining = TableSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('code', $this->internalCompanySlotService->tableCodes())
            ->count();

        $incenseRemaining = IncenseSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('number', $this->internalCompanySlotService->incenseNumbers())
            ->count();

        $packages = Package::query()
            ->orderBy('id')
            ->get()
            ->map(function (Package $package) use ($tableRemaining, $incenseRemaining): array {
                return [
                    'code' => $package->code->value,
                    'available' => $this->isPackageAvailable(
                        $package->code,
                        $tableRemaining,
                        $incenseRemaining,
                    ),
                    'reason' => $this->unavailableReason(
                        $package->code,
                        $tableRemaining,
                        $incenseRemaining,
                    ),
                ];
            })
            ->all();

        return [
            'table_remaining' => $tableRemaining,
            'incense_remaining' => $incenseRemaining,
            'packages' => $packages,
        ];
    }

    public function isPackageAvailable(
        PackageCode $packageCode,
        ?int $tableRemaining = null,
        ?int $incenseRemaining = null,
    ): bool {
        $tableRemaining ??= TableSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('code', $this->internalCompanySlotService->tableCodes())
            ->count();
        $incenseRemaining ??= IncenseSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('number', $this->internalCompanySlotService->incenseNumbers())
            ->count();

        return match ($packageCode) {
            PackageCode::Prayer => $tableRemaining > 0,
            PackageCode::Incense => $incenseRemaining > 0,
            PackageCode::Combo => $tableRemaining > 0 && $incenseRemaining > 0,
        };
    }

    public function unavailableReason(
        PackageCode $packageCode,
        ?int $tableRemaining = null,
        ?int $incenseRemaining = null,
    ): ?string {
        $tableRemaining ??= TableSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('code', $this->internalCompanySlotService->tableCodes())
            ->count();
        $incenseRemaining ??= IncenseSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('number', $this->internalCompanySlotService->incenseNumbers())
            ->count();

        return match ($packageCode) {
            PackageCode::Prayer => $tableRemaining > 0 ? null : 'Nomor meja sudah habis.',
            PackageCode::Incense => $incenseRemaining > 0 ? null : 'Nomor hio sudah habis.',
            PackageCode::Combo => match (true) {
                $tableRemaining <= 0 && $incenseRemaining <= 0 => 'Nomor meja dan nomor hio sudah habis.',
                $tableRemaining <= 0 => 'Nomor meja sudah habis.',
                $incenseRemaining <= 0 => 'Nomor hio sudah habis.',
                default => null,
            },
        };
    }
}
