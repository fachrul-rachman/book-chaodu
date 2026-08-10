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

    public function __construct(public int $mediaId) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function handle(GalleryVideoInspector $inspector): void
    {
        $media = GalleryMedia::query()->find($this->mediaId);

        if (! $media || $media->media_type !== GalleryMediaType::Video || $media->status !== GalleryMediaStatus::Processing) {
            return;
        }

        try {
            $inspector->inspect($media);
        } catch (UnexpectedValueException) {
            $this->reject($media, 'Format video harus H.264 dengan audio AAC.');

            return;
        }

        $media->forceFill([
            'status' => GalleryMediaStatus::Ready,
            'published_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $media = GalleryMedia::query()->find($this->mediaId);

        if ($media && $media->status === GalleryMediaStatus::Processing) {
            $this->reject($media, 'Video gagal diverifikasi. Silakan upload ulang.');
        }
    }

    private function reject(GalleryMedia $media, string $message): void
    {
        Storage::disk($media->storage_disk)->delete($media->original_path);
        $media->forceFill([
            'status' => GalleryMediaStatus::Failed,
            'published_at' => null,
            'error_message' => $message,
        ])->save();
    }
}
