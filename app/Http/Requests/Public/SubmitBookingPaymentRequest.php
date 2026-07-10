<?php

namespace App\Http\Requests\Public;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingExpiryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SubmitBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'sender_name' => ['required', 'string', 'max:120'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'proof' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'pdf'])->max($this->uploadMaxKb()),
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $booking = $this->route('booking');

                if (! $booking instanceof Booking) {
                    return;
                }

                $bookingExpiryService = app(BookingExpiryService::class);

                if ($booking->status === BookingStatus::Pending && $booking->payment()->exists()) {
                    $validator->errors()->add('booking', 'Pembayaran untuk booking ini sudah dikirim.');

                    return;
                }

                if (
                    ! $bookingExpiryService->isAwaitingPayment($booking)
                    && ! $bookingExpiryService->isExpiredBooking($booking)
                ) {
                    $validator->errors()->add('booking', 'Booking ini sudah tidak bisa dibayar lagi.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sender_name' => trim((string) $this->input('sender_name')),
        ]);
    }

    private function uploadMaxKb(): int
    {
        return max(1024, (int) config('phase3.upload_max_mb', 5) * 1024);
    }
}
