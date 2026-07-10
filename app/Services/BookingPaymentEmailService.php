<?php

namespace App\Services;

use App\Mail\BookingPaymentLinkMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingPaymentEmailService
{
    public function __construct(
        private readonly BookingPaymentLinkService $bookingPaymentLinkService,
    ) {}

    public function sendPaymentLink(Booking $booking): void
    {
        try {
            Mail::to($booking->customer_email, $booking->customer_name)
                ->send(new BookingPaymentLinkMail(
                    $booking,
                    $this->bookingPaymentLinkService->paymentUrl($booking),
                    optional($this->bookingPaymentLinkService->expiresAt($booking))->format('d M Y H:i'),
                ));
        } catch (\Throwable $throwable) {
            Log::warning('Gagal mengirim email link pembayaran booking.', [
                'booking_number' => $booking->booking_number,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
