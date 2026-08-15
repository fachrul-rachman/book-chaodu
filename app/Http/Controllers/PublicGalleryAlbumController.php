<?php

namespace App\Http\Controllers;

use App\Services\GalleryArchiveService;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicGalleryAlbumController extends Controller
{
    public function __invoke(
        Request $request,
        string $bookingNumber,
        PublicGalleryAlbumService $albumService,
        GalleryArchiveService $archiveService,
    ): Response {
        $booking = $albumService->findApprovedBooking($bookingNumber);
        $media = $albumService->activeMedia($booking)
            ->map(fn ($item): array => $albumService->mediaPayload($booking, $item));
        $response = Inertia::render('public/gallery', [
            'album' => $albumService->albumIdentity($booking),
            'bookingDetails' => $albumService->bookingDetails($booking),
            'media' => $media,
            'downloadAll' => $archiveService->albumPayload($booking),
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
