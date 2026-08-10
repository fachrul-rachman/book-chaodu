<?php

namespace App\Http\Controllers;

use App\Services\GalleryArchiveService;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Http\JsonResponse;

class PublicGalleryArchiveController extends Controller
{
    public function show(
        string $bookingNumber,
        PublicGalleryAlbumService $albumService,
        GalleryArchiveService $archiveService,
    ): JsonResponse {
        $booking = $albumService->findApprovedBooking($bookingNumber);

        return response()->json($archiveService->status($booking), headers: [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function store(
        string $bookingNumber,
        PublicGalleryAlbumService $albumService,
        GalleryArchiveService $archiveService,
    ): JsonResponse {
        $booking = $albumService->findApprovedBooking($bookingNumber);

        return response()->json($archiveService->request($booking), 202, [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
