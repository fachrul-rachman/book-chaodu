<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingApprovalController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        BookingApprovalService $bookingApprovalService,
    ): RedirectResponse {
        $bookingApprovalService->approve($booking, (int) $request->user()->id);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'Booking berhasil disetujui.');
    }
}
