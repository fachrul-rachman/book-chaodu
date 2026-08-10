<?php

namespace App\Http\Controllers;

use App\Models\GalleryMedia;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicGalleryMediaController extends Controller
{
    public function __invoke(
        string $bookingNumber,
        int $media,
        PublicGalleryAlbumService $albumService,
    ): StreamedResponse {
        $booking = $albumService->findApprovedBooking($bookingNumber);
        $galleryMedia = GalleryMedia::query()->findOrFail($media);
        $path = $albumService->previewPath($booking, $galleryMedia);
        $disk = Storage::disk($galleryMedia->storage_disk);

        abort_unless($disk->exists($path), 404);

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => $galleryMedia->mime_type,
        };

        return $disk->response(
            $path,
            'media-'.$galleryMedia->uuid.'.'.$extension,
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, max-age='.(int) config('gallery.preview_cache_seconds'),
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ],
        );
    }
}
