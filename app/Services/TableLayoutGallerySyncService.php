<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\TableSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TableLayoutGallerySyncService
{
    public function __construct(private readonly TableLayoutImageRenderer $renderer) {}

    public function syncForBooking(Booking $booking): void
    {
        $booking = $booking->fresh('tableSlots') ?? $booking;

        if ($booking->status !== BookingStatus::Approved) {
            return;
        }

        $target = $booking->tableSlots->sortBy('allocation_order')->first();

        if (! $target) {
            $this->removeExisting($booking);

            return;
        }

        $bytes = $this->renderer->render(
            TableSlot::query()->orderByDesc('number')->orderBy('allocation_order')->get(),
            $target->code,
        );
        $targetDisk = (string) config('gallery.storage_disk');
        $version = substr(hash('sha256', $bytes), 0, 16);
        $targetPath = "gallery/bookings/{$booking->id}/table-layout/{$version}.png";
        $disk = Storage::disk($targetDisk);
        $written = $disk->put($targetPath, $bytes, [
            'visibility' => 'private',
            'ContentType' => 'image/png',
        ]);

        if (! $written) {
            throw new RuntimeException('Denah meja tidak dapat disimpan ke galeri.');
        }

        $oldPath = null;

        $media = DB::transaction(function () use ($booking, $target, $targetDisk, $targetPath, $bytes, &$oldPath): GalleryMedia {
            $media = GalleryMedia::query()
                ->where('source_table_layout_booking_id', $booking->id)
                ->lockForUpdate()
                ->first();
            $oldPath = $media?->original_path;
            $media ??= new GalleryMedia([
                'uuid' => (string) Str::uuid(),
                'scope' => GalleryMediaScope::Booking,
                'booking_id' => $booking->id,
                'source_table_layout_booking_id' => $booking->id,
                'media_type' => GalleryMediaType::Image,
            ]);
            $media->forceFill([
                'status' => GalleryMediaStatus::Ready,
                'storage_disk' => $targetDisk,
                'original_path' => $targetPath,
                'preview_path' => null,
                'thumbnail_path' => null,
                'original_filename' => 'Denah Meja '.$target->code.'.png',
                'stored_filename' => basename($targetPath),
                'mime_type' => 'image/png',
                'extension' => 'png',
                'size_bytes' => strlen($bytes),
                'width' => TableLayoutImageRenderer::WIDTH,
                'height' => TableLayoutImageRenderer::HEIGHT,
                'duration_seconds' => null,
                'caption' => 'Denah Meja Anda: '.$target->code,
                'sort_order' => -1100,
                'error_message' => null,
                'uploaded_by' => null,
                'published_at' => $booking->approved_at ?? now(),
            ])->save();

            return $media;
        });

        if ($oldPath && $oldPath !== $media->original_path) {
            $disk->delete($oldPath);
        }
    }

    public function syncSafely(Booking $booking): bool
    {
        try {
            $this->syncForBooking($booking);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function removeExisting(Booking $booking): void
    {
        $media = GalleryMedia::query()
            ->where('source_table_layout_booking_id', $booking->id)
            ->first();

        if (! $media) {
            return;
        }

        Storage::disk($media->storage_disk)->delete(array_values(array_filter([
            $media->original_path,
            $media->preview_path,
            $media->thumbnail_path,
        ])));
        $media->delete();
    }
}
