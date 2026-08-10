<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReorderGlobalMediaRequest;
use App\Models\Booking;
use App\Services\BookingGalleryMediaService;
use Illuminate\Http\JsonResponse;

class CustomerMediaOrderController extends Controller
{
    public function __invoke(
        ReorderGlobalMediaRequest $request,
        Booking $booking,
        BookingGalleryMediaService $service,
    ): JsonResponse {
        /** @var array<int, int> $ids */
        $ids = $request->validated('media_ids');
        $service->reorder($booking, $ids);

        return response()->json(['message' => 'Urutan media customer berhasil disimpan.']);
    }
}
