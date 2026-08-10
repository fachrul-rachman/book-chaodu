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

class PublicGalleryAlbumService
{
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

    /** @return array<string, string> */
    public function albumIdentity(Booking $booking): array
    {
        return [
            'bookingNumber' => $booking->booking_number,
            'eventName' => (string) config('gallery.event_name'),
            'eventDate' => $this->eventDateLabel(),
            'title' => (string) config('gallery.album_title'),
        ];
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
        ];
    }

    public function previewPath(Booking $booking, GalleryMedia $media): string
    {
        abort_unless($media->status === GalleryMediaStatus::Ready, 404);
        $canAccess = match ($media->scope) {
            GalleryMediaScope::Global => true,
            GalleryMediaScope::Booking => $media->booking_id === $booking->id,
        };
        abort_unless($canAccess, 404);

        $path = $media->thumbnail_path ?: $media->preview_path;

        if (! $path && $media->media_type === GalleryMediaType::Image) {
            $path = $media->original_path;
        }

        abort_unless(is_string($path) && $path !== '', 404);

        return $path;
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

    private function eventDateLabel(): string
    {
        $value = config('gallery.event_date');

        if (! is_string($value) || trim($value) === '') {
            return 'Tanggal acara akan diumumkan';
        }

        $dateValue = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) !== 1) {
            return 'Tanggal acara akan diumumkan';
        }

        [$year, $month, $day] = array_map('intval', explode('-', $dateValue));

        if (! checkdate($month, $day, $year)) {
            return 'Tanggal acara akan diumumkan';
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $day.' '.$months[$month].' '.$year;
    }
}
