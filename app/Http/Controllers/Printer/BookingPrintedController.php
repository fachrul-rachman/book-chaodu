<?php

namespace App\Http\Controllers\Printer;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingPrintedController extends Controller
{
    public function __invoke(Request $request, Booking $booking): RedirectResponse
    {
        abort_if($booking->status !== BookingStatus::Approved, 404);

        $payload = $request->validate([
            'is_printed' => ['required', 'boolean'],
        ]);

        $booking->forceFill([
            'is_printed' => (bool) $payload['is_printed'],
        ])->save();

        return back()->with('status', 'Tanda print berhasil disimpan.');
    }
}
