<?php

namespace App\Http\Controllers;

use App\Services\GallerySettingService;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicGalleryWallpaperController extends Controller
{
    public function __invoke(
        string $bookingNumber,
        PublicGalleryAlbumService $albumService,
        GallerySettingService $settingService,
    ): StreamedResponse {
        $albumService->findApprovedBooking($bookingNumber);

        return Storage::disk($settingService->diskName())->response(
            $settingService->wallpaperPath(),
            null,
            [
                'Content-Type' => $settingService->wallpaperMimeType(),
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
