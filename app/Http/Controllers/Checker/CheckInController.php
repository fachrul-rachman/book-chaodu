<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        CheckInService $checkInService,
    ): RedirectResponse {
        $checkInService->checkIn($booking, $request->user());

        return redirect()
            ->route('checker.dashboard', ['kode' => $booking->booking_number])
            ->with('status', 'Check-in berhasil dicatat.');
    }
}
