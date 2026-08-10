<?php

namespace App\Services;

use App\Models\Booking;

class GalleryAlbumUrlService
{
    public function forBooking(Booking $booking): string
    {
        return route('public.gallery.show', [
            'bookingNumber' => $booking->booking_number,
        ]);
    }
}
