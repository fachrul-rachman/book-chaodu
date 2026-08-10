<?php

namespace App\Mail;

use App\Enums\PackageCode;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;

class BookingApprovedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        private readonly string $qrContent,
        private readonly string $albumUrl,
    ) {}

    public function build(): self
    {
        $slotRows = array_map(
            fn (array $row): array => [
                'label' => $row['label'],
                'value' => $row['value'] !== '' ? $row['value'] : '-',
            ],
            $this->slotRows(),
        );

        return $this->subject('Booking Anda sudah disetujui')
            ->view('mail.booking-approved', [
                'bookingNumber' => $this->booking->booking_number,
                'customerName' => $this->booking->customer_name,
                'guestCount' => $this->booking->attendee_count,
                'slotRows' => $slotRows,
                'albumUrl' => $this->albumUrl,
                'year' => Carbon::now()->year,
            ])
            ->attachData(
                $this->qrContent,
                $this->booking->booking_number.'-qr.png',
                ['mime' => 'image/png'],
            );
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function slotRows(): array
    {
        $packageCode = PackageCode::from($this->booking->package_code_snapshot);
        $tableCodes = $this->booking->tableSlots->pluck('code')->filter()->implode(', ');
        $incenseNumbers = $this->booking->incenseSlots->pluck('number')->filter()->implode(', ');

        return match ($packageCode) {
            PackageCode::Prayer => [
                ['label' => 'Nomor meja', 'value' => $tableCodes],
            ],
            PackageCode::Incense => [
                ['label' => 'Nomor hio', 'value' => $incenseNumbers],
            ],
            PackageCode::Combo => [
                ['label' => 'Nomor meja', 'value' => $tableCodes],
                ['label' => 'Nomor hio', 'value' => $incenseNumbers],
            ],
        };
    }
}
