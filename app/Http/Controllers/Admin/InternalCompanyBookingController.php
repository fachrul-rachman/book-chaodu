<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInternalCompanyBookingRequest;
use App\Services\InternalCompanyBookingService;
use App\Services\InternalCompanySlotService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InternalCompanyBookingController extends Controller
{
    public function create(InternalCompanySlotService $internalCompanySlotService): Response
    {
        return Inertia::render('admin/internal-company-bookings/create', [
            'internal_company' => [
                'label' => $internalCompanySlotService->sourceLabel(),
                'table_codes' => $internalCompanySlotService->tableCodes(),
                'incense_numbers' => $internalCompanySlotService->incenseNumbers(),
            ],
        ]);
    }

    public function store(
        StoreInternalCompanyBookingRequest $request,
        InternalCompanyBookingService $internalCompanyBookingService,
    ): RedirectResponse {
        $booking = $internalCompanyBookingService->create(
            $request->validated(),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'Booking Internal Perusahaan berhasil dibuat.');
    }
}
