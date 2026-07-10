<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Http\Requests\Public\SubmitBookingPaymentRequest;
use App\Models\Booking;
use App\Services\BookingExpiryService;
use App\Services\BookingPaymentLinkService;
use App\Services\BookingPaymentSubmissionService;
use App\Services\VirtualAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PublicBookingPaymentController extends Controller
{
    public function store(
        SubmitBookingPaymentRequest $request,
        Booking $booking,
        BookingPaymentLinkService $bookingPaymentLinkService,
        BookingExpiryService $bookingExpiryService,
        BookingPaymentSubmissionService $bookingPaymentSubmissionService,
        VirtualAccountService $virtualAccountService,
    ): JsonResponse {
        if (! $bookingPaymentLinkService->hasValidToken($booking, $request->string('token')->toString())) {
            abort(404);
        }

        $booking = $bookingExpiryService->expireIfNeeded($booking)->fresh(['payment']) ?? $booking;

        if ($bookingExpiryService->isExpiredBooking($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'Booking ini sudah lewat waktu. Silakan booking ulang.',
            ]);
        }

        $virtualAccount = $virtualAccountService->isFixedMode()
            ? $virtualAccountService->requirePackageAccount(
                PackageCode::from($booking->package_code_snapshot),
            )
            : $virtualAccountService->findByBooking($booking);
        $paymentIdentity = $virtualAccountService->paymentIdentity();

        if (! $virtualAccount) {
            throw ValidationException::withMessages([
                'booking' => 'Nomor VA untuk booking ini tidak ditemukan. Silakan booking ulang.',
            ]);
        }

        $booking = $bookingPaymentSubmissionService->submit($booking, [
            ...$request->validated(),
            'virtual_account_bank_name' => $paymentIdentity['bank_name'],
            'virtual_account_number' => $virtualAccount->account_number,
            'virtual_account_holder' => $paymentIdentity['bank_account_holder'],
        ]);

        return response()->json([
            'booking_number' => $booking->booking_number,
            'message' => 'Pembayaran berhasil dikirim. Mohon tunggu pengecekan dari petugas.',
        ]);
    }
}
