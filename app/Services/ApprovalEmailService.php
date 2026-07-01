<?php

namespace App\Services;

use App\Mail\BookingApprovedMail;
use App\Models\ApprovalIntegration;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

class ApprovalEmailService
{
    public function sendApprovedEmail(
        Booking $booking,
        ApprovalIntegration $integration,
        string $qrContent,
    ): void {
        Mail::to($booking->customer_email, $booking->customer_name)
            ->send(new BookingApprovedMail($booking, $integration, $qrContent));
    }
}
