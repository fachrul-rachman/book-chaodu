<?php

namespace App\Http\Controllers;

use App\Models\GalleryMedia;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicGalleryMediaDownloadController extends Controller
{
    public function __invoke(
        string $bookingNumber,
        int $media,
        PublicGalleryAlbumService $albumService,
    ): StreamedResponse {
        $booking = $albumService->findApprovedBooking($bookingNumber);
        $galleryMedia = GalleryMedia::query()->findOrFail($media);
        $path = $albumService->downloadPath($booking, $galleryMedia);
        $disk = Storage::disk($galleryMedia->storage_disk);
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $albumService->downloadFilename($galleryMedia), [
            'Content-Type' => $galleryMedia->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
