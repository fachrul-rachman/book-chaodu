<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Models\Booking;
use App\Models\GalleryMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicGalleryAlbumService
{
    public function __construct(private readonly GallerySettingService $settingService) {}

    public function findApprovedBooking(string $bookingNumber): Booking
    {
        return Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('status', BookingStatus::Approved)
            ->firstOrFail();
    }

    /** @return Collection<int, GalleryMedia> */
    public function activeMedia(Booking $booking): Collection
    {
        $global = $this->orderedMediaQuery()
            ->where('scope', GalleryMediaScope::Global)
            ->whereNull('booking_id')
            ->get();
        $owned = $this->orderedMediaQuery()
            ->where('scope', GalleryMediaScope::Booking)
            ->where('booking_id', $booking->id)
            ->get();

        return $global->concat($owned)->values();
    }

    /** @return array<string, string|null> */
    public function albumIdentity(Booking $booking): array
    {
        return $this->settingService->albumIdentity($booking);
    }

    /** @return array<string, int|string|null> */
    public function mediaPayload(Booking $booking, GalleryMedia $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->media_type->value,
            'scope' => $media->scope->value,
            'caption' => $media->caption,
            'width' => $media->width,
            'height' => $media->height,
            'previewUrl' => $this->hasPreview($media)
                ? route('public.gallery.media.preview', [
                    'bookingNumber' => $booking->booking_number,
                    'media' => $media->id,
                ])
                : null,
            'viewerUrl' => route('public.gallery.media.viewer', [
                'bookingNumber' => $booking->booking_number,
                'media' => $media->id,
            ]),
            'downloadUrl' => route('public.gallery.media.download', [
                'bookingNumber' => $booking->booking_number,
                'media' => $media->id,
            ]),
        ];
    }

    public function previewPath(Booking $booking, GalleryMedia $media): string
    {
        $this->assertCanAccess($booking, $media);

        $path = $media->thumbnail_path ?: $media->preview_path;

        if (! $path && $media->media_type === GalleryMediaType::Image) {
            $path = $media->original_path;
        }

        abort_unless(is_string($path) && $path !== '', 404);

        return $path;
    }

    public function viewerPath(Booking $booking, GalleryMedia $media): string
    {
        $this->assertCanAccess($booking, $media);
        $path = $media->media_type === GalleryMediaType::Video
            ? $media->original_path
            : ($media->preview_path ?: $media->thumbnail_path ?: $media->original_path);

        abort_unless($path !== '', 404);

        return $path;
    }

    public function downloadPath(Booking $booking, GalleryMedia $media): string
    {
        $this->assertCanAccess($booking, $media);
        abort_unless($media->original_path !== '', 404);

        return $media->original_path;
    }

    public function downloadFilename(GalleryMedia $media): string
    {
        $base = pathinfo(basename($media->original_filename), PATHINFO_FILENAME);
        $base = Str::ascii($base);
        $base = preg_replace('/[^A-Za-z0-9]+/', '_', $base) ?: 'media';
        $base = Str::limit(trim($base, '_'), 120, '');

        return ($base === '' ? 'media-'.$media->uuid : $base).'.'.strtolower($media->extension);
    }

    /** @return Builder<GalleryMedia> */
    private function orderedMediaQuery(): Builder
    {
        return GalleryMedia::query()
            ->where('status', GalleryMediaStatus::Ready)
            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private function hasPreview(GalleryMedia $media): bool
    {
        return $media->thumbnail_path !== null
            || $media->preview_path !== null
            || $media->media_type === GalleryMediaType::Image;
    }

    private function assertCanAccess(Booking $booking, GalleryMedia $media): void
    {
        abort_unless($media->status === GalleryMediaStatus::Ready, 404);
        $canAccess = match ($media->scope) {
            GalleryMediaScope::Global => true,
            GalleryMediaScope::Booking => $media->booking_id === $booking->id,
        };
        abort_unless($canAccess, 404);
    }
}
