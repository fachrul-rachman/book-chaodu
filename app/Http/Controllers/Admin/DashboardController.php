<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\AvailabilityService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AvailabilityService $availabilityService): Response
    {
        return Inertia::render('admin/dashboard', [
            'availability' => $availabilityService->summary(),
            'booking_counts' => [
                'pending' => Booking::query()->where('status', BookingStatus::Pending)->count(),
                'approved' => Booking::query()->where('status', BookingStatus::Approved)->count(),
                'rejected' => Booking::query()->where('status', BookingStatus::Rejected)->count(),
            ],
        ]);
    }
}
