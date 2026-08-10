<?php

namespace App\Services;

use App\Enums\GalleryArchiveStatus;
use App\Jobs\BuildGalleryArchive;
use App\Models\Booking;
use App\Models\GalleryArchive;
use App\Models\GalleryMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GalleryArchiveService
{
    public function __construct(private PublicGalleryAlbumService $albumService) {}

    /** @return array<string, int|string|null> */
    public function albumPayload(Booking $booking): array
    {
        $media = $this->albumService->activeMedia($booking);
        $archive = $this->currentArchive($booking, $media);

        return $this->payload($booking, $media, $archive);
    }

    /** @return array<string, int|string|null> */
    public function request(Booking $booking): array
    {
        return DB::transaction(function () use ($booking): array {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $media = $this->albumService->activeMedia($lockedBooking);
            abort_if($media->isEmpty(), 422, 'Album belum memiliki media untuk di-download.');

            $fingerprint = $this->fingerprint($media);
            $archive = GalleryArchive::query()->firstOrNew([
                'booking_id' => $lockedBooking->id,
                'fingerprint' => $fingerprint,
            ]);
            $shouldDispatch = ! $archive->exists;

            if (! $archive->exists) {
                $archive->fill([
                    'status' => GalleryArchiveStatus::Pending,
                    'storage_disk' => (string) config('gallery.storage_disk'),
                ]);
            } elseif ($archive->status === GalleryArchiveStatus::Failed
                || $archive->status === GalleryArchiveStatus::Expired
                || ($archive->status === GalleryArchiveStatus::Ready && ! $this->isUsable($archive))) {
                $this->deleteArchiveFile($archive);
                $archive->forceFill([
                    'status' => GalleryArchiveStatus::Pending,
                    'file_path' => null,
                    'size_bytes' => null,
                    'error_message' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'expires_at' => null,
                ]);
                $shouldDispatch = true;
            }

            $archive->save();

            if ($shouldDispatch) {
                BuildGalleryArchive::dispatch($archive->id)->afterCommit();
            }

            return $this->payload($lockedBooking, $media, $archive);
        }, 3);
    }

    /** @return array<string, int|string|null> */
    public function status(Booking $booking): array
    {
        $media = $this->albumService->activeMedia($booking);

        return $this->payload($booking, $media, $this->currentArchive($booking, $media));
    }

    public function readyArchive(Booking $booking): GalleryArchive
    {
        $media = $this->albumService->activeMedia($booking);
        $archive = $this->currentArchive($booking, $media);

        abort_unless($archive && $archive->status === GalleryArchiveStatus::Ready, 404);
        abort_unless($this->isUsable($archive), 404);

        return $archive;
    }

    /** @param Collection<int, GalleryMedia> $media */
    public function fingerprint(Collection $media): string
    {
        $manifest = $media->map(fn (GalleryMedia $item): array => [
            'id' => $item->id,
            'path' => $item->original_path,
            'size' => $item->size_bytes,
            'updated' => $item->updated_at?->format('Y-m-d H:i:s.u'),
        ])->values()->all();

        return hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    public function cleanupExpired(): int
    {
        $cleaned = 0;

        GalleryArchive::query()
            ->where('status', GalleryArchiveStatus::Ready)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function (Collection $archives) use (&$cleaned): void {
                foreach ($archives as $archive) {
                    DB::transaction(function () use ($archive, &$cleaned): void {
                        $locked = GalleryArchive::query()->lockForUpdate()->find($archive->id);

                        if (! $locked
                            || $locked->status !== GalleryArchiveStatus::Ready
                            || ! $locked->expires_at
                            || $locked->expires_at->isFuture()) {
                            return;
                        }

                        $this->deleteArchiveFile($locked);
                        $locked->forceFill([
                            'status' => GalleryArchiveStatus::Expired,
                            'file_path' => null,
                            'size_bytes' => null,
                        ])->save();
                        $cleaned++;
                    });
                }
            });

        return $cleaned;
    }

    /** @param Collection<int, GalleryMedia> $media */
    private function currentArchive(Booking $booking, Collection $media): ?GalleryArchive
    {
        if ($media->isEmpty()) {
            return null;
        }

        return GalleryArchive::query()
            ->where('booking_id', $booking->id)
            ->where('fingerprint', $this->fingerprint($media))
            ->first();
    }

    /**
     * @param  Collection<int, GalleryMedia>  $media
     * @return array<string, int|string|null>
     */
    private function payload(Booking $booking, Collection $media, ?GalleryArchive $archive): array
    {
        $status = $archive instanceof GalleryArchive
            ? $archive->status
            : GalleryArchiveStatus::Expired;
        $usable = $archive instanceof GalleryArchive
            && $status === GalleryArchiveStatus::Ready
            && $this->isUsable($archive);
        $publicStatus = $status === GalleryArchiveStatus::Ready && ! $usable
            ? GalleryArchiveStatus::Expired
            : $status;

        return [
            'archiveId' => $archive?->id,
            'status' => $archive ? ($usable ? GalleryArchiveStatus::Ready->value : $publicStatus->value) : 'IDLE',
            'totalSizeBytes' => (int) $media->sum('size_bytes'),
            'requestUrl' => route('public.gallery.archive.store', $booking->booking_number),
            'statusUrl' => route('public.gallery.archive.show', $booking->booking_number),
            'downloadUrl' => $usable
                ? route('public.gallery.archive.download', $booking->booking_number)
                : null,
        ];
    }

    private function isUsable(GalleryArchive $archive): bool
    {
        return is_string($archive->file_path)
            && $archive->file_path !== ''
            && $archive->expires_at?->isFuture() === true
            && Storage::disk($archive->storage_disk)->exists($archive->file_path);
    }

    private function deleteArchiveFile(GalleryArchive $archive): void
    {
        if (is_string($archive->file_path) && $archive->file_path !== '') {
            Storage::disk($archive->storage_disk)->delete($archive->file_path);
        }
    }
}
