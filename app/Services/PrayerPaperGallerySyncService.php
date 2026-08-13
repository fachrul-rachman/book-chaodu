<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\PrayerPaper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PrayerPaperGallerySyncService
{
    public function syncForBooking(Booking $booking): void
    {
        $booking = $booking->fresh(['prayerPapers']) ?? $booking;

        if ($booking->status !== BookingStatus::Approved) {
            return;
        }

        $papers = $booking->prayerPapers
            ->filter(fn (PrayerPaper $paper): bool => PrayerPaperStatus::from((string) $paper->getRawOriginal('status')) === PrayerPaperStatus::Ready && filled($paper->file_path))
            ->sortBy(['type', 'sequence'])
            ->values();
        $paperIds = $booking->prayerPapers->pluck('id')->all();
        $this->removeStaleCopies($booking, $paperIds);
        $prayerCount = $papers->where('type', PrayerPaperType::A)->count();

        foreach ($papers as $paper) {
            $this->syncPaper($booking, $paper, $prayerCount);
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

    /** @param array<int, int> $paperIds */
    private function removeStaleCopies(Booking $booking, array $paperIds): void
    {
        $query = GalleryMedia::query()
            ->where('booking_id', $booking->id)
            ->whereNotNull('source_prayer_paper_id');

        if ($paperIds !== []) {
            $query->whereNotIn('source_prayer_paper_id', $paperIds);
        }

        foreach ($query->get() as $media) {
            Storage::disk($media->storage_disk)->delete(array_values(array_filter([
                $media->original_path,
                $media->preview_path,
                $media->thumbnail_path,
            ])));
            $media->delete();
        }
    }

    private function syncPaper(Booking $booking, PrayerPaper $paper, int $prayerCount): void
    {
        $sourcePath = (string) $paper->file_path;
        $bytes = Storage::disk((string) config('phase5.storage_disk'))->get($sourcePath);
        $dimensions = @getimagesizefromstring($bytes);
        $width = $dimensions !== false && $dimensions[0] > 0 ? $dimensions[0] : null;
        $height = $dimensions !== false && $dimensions[1] > 0 ? $dimensions[1] : null;
        $mimeType = $dimensions !== false ? $dimensions['mime'] : 'image/png';

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'png';
        $targetDisk = (string) config('gallery.storage_disk');
        $targetPath = "gallery/bookings/{$booking->id}/prayer-papers/{$paper->id}/v{$paper->version}.{$extension}";
        $disk = Storage::disk($targetDisk);
        $written = $disk->put($targetPath, $bytes, [
            'visibility' => 'private',
            'ContentType' => $mimeType,
        ]);

        if (! $written) {
            throw new RuntimeException('Salinan kertas doa tidak dapat disimpan ke galeri.');
        }

        $oldPath = null;

        try {
            $media = DB::transaction(function () use (
                $booking,
                $paper,
                $prayerCount,
                $targetDisk,
                $targetPath,
                $extension,
                $bytes,
                $width,
                $height,
                $mimeType,
                &$oldPath,
            ): GalleryMedia {
                $media = GalleryMedia::query()
                    ->where('source_prayer_paper_id', $paper->id)
                    ->lockForUpdate()
                    ->first();
                $oldPath = $media?->original_path;
                $type = PrayerPaperType::from((string) $paper->getRawOriginal('type'));

                $media ??= new GalleryMedia([
                    'uuid' => (string) Str::uuid(),
                    'scope' => GalleryMediaScope::Booking,
                    'booking_id' => $booking->id,
                    'source_prayer_paper_id' => $paper->id,
                    'media_type' => GalleryMediaType::Image,
                ]);
                $media->forceFill([
                    'status' => GalleryMediaStatus::Ready,
                    'storage_disk' => $targetDisk,
                    'original_path' => $targetPath,
                    'preview_path' => null,
                    'thumbnail_path' => null,
                    'original_filename' => $this->filename($type, (int) $paper->sequence, $prayerCount),
                    'stored_filename' => basename($targetPath),
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'size_bytes' => strlen($bytes),
                    'width' => $width,
                    'height' => $height,
                    'duration_seconds' => null,
                    'caption' => $this->caption($type, (int) $paper->sequence, $prayerCount),
                    'sort_order' => $this->sortOrder($type, (int) $paper->sequence),
                    'error_message' => null,
                    'uploaded_by' => null,
                    'published_at' => $paper->generated_at ?? now(),
                ])->save();

                return $media;
            });
        } catch (Throwable $exception) {
            if ($oldPath !== $targetPath) {
                $disk->delete($targetPath);
            }

            throw $exception;
        }

        if ($oldPath && $oldPath !== $media->original_path) {
            $disk->delete($oldPath);
        }
    }

    private function caption(PrayerPaperType $type, int $sequence, int $prayerCount): string
    {
        if ($type === PrayerPaperType::B) {
            return 'Kertas Hio';
        }

        return $prayerCount > 1 ? 'Kertas Doa '.$sequence : 'Kertas Doa';
    }

    private function filename(PrayerPaperType $type, int $sequence, int $prayerCount): string
    {
        return $this->caption($type, $sequence, $prayerCount).'.png';
    }

    private function sortOrder(PrayerPaperType $type, int $sequence): int
    {
        return $type === PrayerPaperType::A ? -1000 + $sequence : -900;
    }
}
