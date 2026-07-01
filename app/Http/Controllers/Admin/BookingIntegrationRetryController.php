<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalIntegrationComponent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ApprovalIntegrationService;
use Illuminate\Http\RedirectResponse;

class BookingIntegrationRetryController extends Controller
{
    public function __invoke(
        Booking $booking,
        string $component,
        ApprovalIntegrationService $approvalIntegrationService,
    ): RedirectResponse {
        $approvalIntegrationService->retry(
            $booking,
            ApprovalIntegrationComponent::from($component),
        );

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'Retry integrasi selesai dijalankan.');
    }
}
