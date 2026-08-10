<?php

namespace App\Services;

use App\Mail\BookingApprovedMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

class ApprovalEmailService
{
    public function __construct(private readonly GalleryAlbumUrlService $albumUrlService) {}

    public function sendApprovedEmail(
        Booking $booking,
        string $qrContent,
    ): void {
        Mail::to($booking->customer_email, $booking->customer_name)
            ->send(new BookingApprovedMail(
                $booking,
                $qrContent,
                $this->albumUrlService->forBooking($booking),
            ));
    }
}
