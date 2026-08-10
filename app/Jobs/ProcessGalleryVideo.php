<?php

namespace App\Jobs;

use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Models\GalleryMedia;
use App\Services\GalleryVideoInspector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnexpectedValueException;

class ProcessGalleryVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $mediaId,
        public bool $thumbnailOnly = false,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function handle(GalleryVideoInspector $inspector): void
    {
        $media = GalleryMedia::query()->find($this->mediaId);

        $canProcess = $media
            && $media->media_type === GalleryMediaType::Video
            && ($media->status === GalleryMediaStatus::Processing
                || ($this->thumbnailOnly && $media->status === GalleryMediaStatus::Ready));

        if (! $canProcess) {
            return;
        }

        try {
            $metadata = $inspector->inspect($media);
        } catch (UnexpectedValueException) {
            if ($this->thumbnailOnly) {
                $media->forceFill(['error_message' => 'Thumbnail video lama gagal dibuat karena format tidak didukung.'])->save();

                return;
            }

            $this->reject($media, 'Format video harus H.264 dengan audio AAC.');

            return;
        }

        $media->forceFill([
            'thumbnail_path' => $metadata['thumbnail_path'],
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'duration_seconds' => $metadata['duration_seconds'],
            'status' => GalleryMediaStatus::Ready,
            'published_at' => $media->published_at ?? now(),
            'error_message' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $media = GalleryMedia::query()->find($this->mediaId);

        if ($media && $this->thumbnailOnly && $media->status === GalleryMediaStatus::Ready) {
            $media->forceFill(['error_message' => 'Thumbnail video lama gagal dibuat. Jalankan backfill kembali.'])->save();
        } elseif ($media && $media->status === GalleryMediaStatus::Processing) {
            $this->reject($media, 'Video gagal diverifikasi. Silakan upload ulang.');
        }
    }

    private function reject(GalleryMedia $media, string $message): void
    {
        Storage::disk($media->storage_disk)->delete(array_filter([
            $media->original_path,
            $media->thumbnail_path,
        ]));
        $media->forceFill([
            'status' => GalleryMediaStatus::Failed,
            'published_at' => null,
            'error_message' => $message,
        ])->save();
    }
}
