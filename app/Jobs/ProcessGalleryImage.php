<?php

namespace App\Jobs;

use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Models\GalleryMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessGalleryImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = GalleryMedia::query()->find($this->mediaId);

        if (! $media || $media->media_type !== GalleryMediaType::Image || $media->status !== GalleryMediaStatus::Processing) {
            return;
        }

        try {
            $disk = Storage::disk($media->storage_disk);
            $bytes = $disk->get($media->original_path);
            $source = @imagecreatefromstring($bytes);

            if ($source === false) {
                throw new RuntimeException('File foto rusak atau tidak dapat dibaca.');
            }

            try {
                $width = imagesx($source);
                $height = imagesy($source);
                $max = 640;
                $scale = min(1, $max / max($width, $height));
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));
                $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

                if ($thumbnail === false) {
                    throw new RuntimeException('Thumbnail tidak dapat dibuat.');
                }

                try {
                    imagealphablending($thumbnail, false);
                    imagesavealpha($thumbnail, true);
                    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                    ob_start();
                    imagewebp($thumbnail, null, 82);
                    $thumbnailBytes = ob_get_clean();
                } finally {
                    imagedestroy($thumbnail);
                }
            } finally {
                imagedestroy($source);
            }

            if (! is_string($thumbnailBytes) || $thumbnailBytes === '') {
                throw new RuntimeException('Thumbnail tidak dapat disimpan.');
            }

            $thumbnailPath = dirname($media->original_path).'/thumbnail.webp';
            $disk->put($thumbnailPath, $thumbnailBytes, ['visibility' => 'private', 'ContentType' => 'image/webp']);
            $media->forceFill([
                'thumbnail_path' => $thumbnailPath,
                'width' => $width,
                'height' => $height,
                'status' => GalleryMediaStatus::Ready,
                'published_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $media->forceFill([
                'status' => GalleryMediaStatus::Failed,
                'error_message' => 'Foto gagal diproses.',
            ])->save();
            throw $exception;
        }
    }
}
