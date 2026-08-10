<?php

namespace App\Http\Controllers;

use App\Enums\GalleryMediaType;
use App\Models\GalleryMedia;
use App\Services\GalleryVideoStreamService;
use App\Services\PublicGalleryAlbumService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicGalleryViewerMediaController extends Controller
{
    public function __invoke(
        Request $request,
        string $bookingNumber,
        int $media,
        PublicGalleryAlbumService $albumService,
        GalleryVideoStreamService $videoStream,
    ): Response {
        $booking = $albumService->findApprovedBooking($bookingNumber);
        $galleryMedia = GalleryMedia::query()->findOrFail($media);
        $path = $albumService->viewerPath($booking, $galleryMedia);

        if ($galleryMedia->media_type === GalleryMediaType::Video) {
            return $videoStream->response($galleryMedia, $request->header('Range'));
        }

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
