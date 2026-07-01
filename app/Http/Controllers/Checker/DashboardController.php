<?php

namespace App\Http\Controllers\Checker;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\CheckerLookupService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CheckerLookupService $checkerLookupService): Response
    {
        $lookupCode = trim((string) $request->query('kode', ''));
        $lookupError = null;
        $result = null;

        if ($lookupCode !== '') {
            $booking = $checkerLookupService->findBooking($lookupCode);

            if (! $booking) {
                $lookupError = 'Kode tidak ditemukan.';
            } elseif ($booking->status !== BookingStatus::Approved) {
                $lookupError = 'Booking ini belum bisa check-in.';
                $result = $this->blockedResult($booking);
            } else {
                $result = $this->result($booking);
            }
        }

        return Inertia::render('checker/dashboard', [
            'lookup_code' => $lookupCode,
            'lookup_error' => $lookupError,
            'result' => $result,
        ]);
    }

    /**
     * @return array{
     *     booking_id:int,
     *     booking_number:string,
     *     status:string
     * }
     */
    private function blockedResult(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
        ];
    }

    /**
     * @return array{
     *     booking_id:int,
     *     booking_number:string,
     *     customer_name:string,
     *     attendee_count:int,
     *     vegetarian_quantity:int,
     *     non_vegetarian_quantity:int,
     *     table_codes:array<int,string>,
     *     incense_numbers:array<int,int>,
     *     check_in_status:string,
     *     checked_in_at:string|null,
     *     checked_in_by:string|null
     * }
     */
    private function result(Booking $booking): array
    {
        $meal = $booking->meal;

        return [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'customer_name' => $booking->customer_name,
            'attendee_count' => $booking->attendee_count,
            'vegetarian_quantity' => $meal ? $meal->vegetarian_quantity : 0,
            'non_vegetarian_quantity' => $meal ? $meal->non_vegetarian_quantity : 0,
            'table_codes' => $booking->tableSlots->pluck('code')->filter()->values()->all(),
            'incense_numbers' => $booking->incenseSlots->pluck('number')->filter()->values()->all(),
            'check_in_status' => $booking->checkIn ? 'SUDAH_MASUK' : 'SIAP_MASUK',
            'checked_in_at' => optional($booking->checkIn?->checked_in_at)->format('d M Y H:i'),
            'checked_in_by' => $booking->checkIn?->checker?->name,
        ];
    }
}
