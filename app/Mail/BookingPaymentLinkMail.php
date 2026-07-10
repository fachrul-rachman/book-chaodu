<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingPaymentLinkMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $paymentUrl,
        public readonly string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lanjutkan Pembayaran Booking Chao Du',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.booking-payment-link',
            with: [
                'booking' => $this->booking,
                'paymentUrl' => $this->paymentUrl,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
