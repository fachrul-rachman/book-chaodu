<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingSuccessController extends Controller
{
    public function __invoke(string $bookingNumber): Response
    {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->firstOrFail();

        return Inertia::render('public/success', [
            'booking_number' => $booking->booking_number,
        ]);
    }
}
