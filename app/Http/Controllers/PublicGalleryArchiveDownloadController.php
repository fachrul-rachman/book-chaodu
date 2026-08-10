<?php

namespace App\Http\Controllers;

use App\Services\GalleryArchiveService;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicGalleryArchiveDownloadController extends Controller
{
    public function __invoke(
        string $bookingNumber,
        PublicGalleryAlbumService $albumService,
        GalleryArchiveService $archiveService,
    ): StreamedResponse {
        $booking = $albumService->findApprovedBooking($bookingNumber);
        $archive = $archiveService->readyArchive($booking);

        return Storage::disk($archive->storage_disk)->download(
            (string) $archive->file_path,
            'album-'.$booking->booking_number.'.zip',
            [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ],
        );
    }
}
