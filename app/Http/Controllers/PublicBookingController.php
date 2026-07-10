<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\SubmitBookingRequest;
use App\Services\BookingSubmissionService;
use App\Services\CaptchaVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PublicBookingController extends Controller
{
    public function store(
        SubmitBookingRequest $request,
        BookingSubmissionService $bookingSubmissionService,
        CaptchaVerifier $captchaVerifier,
    ): JsonResponse {
        if (! $captchaVerifier->verify($request->string('captcha_token')->toString())) {
            throw ValidationException::withMessages([
                'captcha_token' => 'Pemeriksaan keamanan belum berhasil. Silakan coba lagi.',
            ]);
        }

        $booking = $bookingSubmissionService->submit($request->validated());

        return response()->json([
            'booking_number' => $booking->booking_number,
            'success_url' => route('public.booking.success', [
                'bookingNumber' => $booking->booking_number,
            ]),
        ], 201);
    }
}
