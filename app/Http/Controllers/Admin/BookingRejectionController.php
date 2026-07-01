<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectBookingRequest;
use App\Models\Booking;
use App\Services\BookingRejectionService;
use Illuminate\Http\RedirectResponse;

class BookingRejectionController extends Controller
{
    public function __invoke(
        RejectBookingRequest $request,
        Booking $booking,
        BookingRejectionService $bookingRejectionService,
    ): RedirectResponse {
        $bookingRejectionService->reject(
            $booking,
            (string) $request->validated()['reason'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'Booking berhasil ditolak.');
    }
}
