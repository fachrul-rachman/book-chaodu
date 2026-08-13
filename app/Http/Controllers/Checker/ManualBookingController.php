<?php

namespace App\Http\Controllers\Checker;

use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checker\StoreManualBookingRequest;
use App\Models\IncenseSlot;
use App\Models\Package;
use App\Models\TableSlot;
use App\Services\CheckerManualBookingService;
use App\Services\InternalCompanySlotService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ManualBookingController extends Controller
{
    public function create(InternalCompanySlotService $internalSlots): Response
    {
        return Inertia::render('checker/manual-bookings/create', [
            'packages' => Package::query()
                ->where('is_active', true)
                ->whereIn('code', array_column(PackageCode::cases(), 'value'))
                ->orderByRaw("CASE code WHEN 'PRAYER' THEN 1 WHEN 'INCENSE' THEN 2 ELSE 3 END")
                ->get(['code', 'name'])
                ->map(fn (Package $package): array => [
                    'code' => $package->code->value,
                    'name' => $package->name,
                ])->all(),
            'table_slots' => TableSlot::query()
                ->where('status', SlotStatus::Available)
                ->whereNotIn('code', $internalSlots->tableCodes())
                ->notTemporarilyClosed()
                ->orderBy('allocation_order')
                ->get(['id', 'code'])
                ->toArray(),
            'incense_slots' => IncenseSlot::query()
                ->where('status', SlotStatus::Available)
                ->whereNotIn('number', $internalSlots->incenseNumbers())
                ->orderBy('allocation_order')
                ->get(['id', 'number'])
                ->toArray(),
            'ocr' => [
                'url' => route('api.public.ocr.store'),
                'max_mb' => (int) config('phase4.ocr_upload_max_mb'),
            ],
        ]);
    }

    public function store(
        StoreManualBookingRequest $request,
        CheckerManualBookingService $service,
    ): RedirectResponse {
        $booking = $service->create($request->validated(), (int) $request->user()->id);

        return to_route('checker.dashboard')->with(
            'status',
            "Booking {$booking->booking_number} berhasil dibuat dan langsung disetujui.",
        );
    }
}
