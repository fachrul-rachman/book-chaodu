<?php

namespace App\Http\Controllers;

use App\Enums\BookingNameCategory;
use App\Enums\PackageCode;
use App\Models\Booking;
use App\Services\BookingExpiryService;
use App\Services\BookingPaymentLinkService;
use App\Services\VirtualAccountService;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingPaymentPageController extends Controller
{
    public function __invoke(
        string $bookingNumber,
        BookingPaymentLinkService $bookingPaymentLinkService,
        BookingExpiryService $bookingExpiryService,
        VirtualAccountService $virtualAccountService,
    ): Response {
        $booking = Booking::query()
            ->with(['names', 'meal', 'payment', 'tableSlots', 'incenseSlots'])
            ->where('booking_number', $bookingNumber)
            ->firstOrFail();

        abort_unless(
            $bookingPaymentLinkService->hasValidToken($booking, request()->query('token')),
            404,
        );

        $booking = $bookingExpiryService->expireIfNeeded($booking)->fresh(['names', 'meal', 'payment', 'tableSlots', 'incenseSlots']) ?? $booking;

        $deceasedNames = $booking->names
            ->where('category', BookingNameCategory::Deceased)
            ->sortBy('position')
            ->map(fn ($name): array => [
                'position' => $name->position,
                'indonesian_name' => $name->indonesian_name,
                'mandarin_name' => $name->mandarin_name,
            ])
            ->values()
            ->all();

        $incenseName = $booking->names
            ->first(fn ($name): bool => $name->category === BookingNameCategory::Incense);
        $virtualAccount = $virtualAccountService->isFixedMode()
            ? $virtualAccountService->requirePackageAccount(
                PackageCode::from($booking->package_code_snapshot),
            )
            : $virtualAccountService->findByBooking($booking);
        $paymentIdentity = $virtualAccountService->paymentIdentity();

        return Inertia::render('public/payment', [
            'booking' => [
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'attendee_count' => $booking->attendee_count,
                'package_name' => $booking->package_name_snapshot,
                'package_price' => $booking->package_price_snapshot,
                'status' => $booking->status->value,
                'sender_name' => $booking->payment?->sender_name,
                'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
                'proof_name' => $booking->payment?->proof_path ? basename($booking->payment->proof_path) : null,
                'virtual_account_bank_name' => $booking->payment?->virtual_account_bank_name ?? $paymentIdentity['bank_name'],
                'virtual_account_number' => $booking->payment?->virtual_account_number ?? $virtualAccount?->account_number,
                'virtual_account_holder' => $booking->payment?->virtual_account_holder ?? $paymentIdentity['bank_account_holder'],
                'table_slot' => $booking->tableSlots->sortBy('allocation_order')->first()?->code,
                'incense_slot' => $booking->incenseSlots->sortBy('allocation_order')->first()?->number,
                'deceased_names' => $deceasedNames,
                'incense_name' => [
                    'indonesian_name' => $incenseName?->indonesian_name,
                    'mandarin_name' => $incenseName?->mandarin_name,
                ],
                'expires_at' => optional($bookingPaymentLinkService->expiresAt($booking))->toIso8601String(),
                'payment_url' => route('public.booking.payment.store', [
                    'booking' => $booking->id,
                ]).'?token='.urlencode((string) request()->query('token')),
                'is_expired' => $bookingExpiryService->isExpiredBooking($booking),
                'is_waiting_payment' => $bookingExpiryService->isAwaitingPayment($booking),
                'is_waiting_review' => $booking->status === \App\Enums\BookingStatus::Pending && $booking->payment !== null,
            ],
            'limits' => [
                'upload_max_mb' => max(1, (int) config('phase3.upload_max_mb', 5)),
            ],
        ]);
    }
}
