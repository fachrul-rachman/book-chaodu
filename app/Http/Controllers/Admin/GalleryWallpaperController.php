<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GallerySettingService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GalleryWallpaperController extends Controller
{
    public function __invoke(GallerySettingService $service): StreamedResponse
    {
        return Storage::disk($service->diskName())->response(
            $service->wallpaperPath(),
            null,
            [
                'Content-Type' => $service->wallpaperMimeType(),
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
