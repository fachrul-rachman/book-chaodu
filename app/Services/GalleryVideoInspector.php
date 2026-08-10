<?php

namespace App\Services;

use App\Models\GalleryMedia;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use UnexpectedValueException;

class GalleryVideoInspector
{
    /** @return array{thumbnail_path: string, width: int, height: int, duration_seconds: float} */
    public function inspect(GalleryMedia $media): array
    {
        $source = Storage::disk($media->storage_disk)->readStream($media->original_path);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'chaodu-gallery-video-');
        $thumbnailTemporaryPath = tempnam(sys_get_temp_dir(), 'chaodu-gallery-thumbnail-');

        if (! is_resource($source) || $temporaryPath === false || $thumbnailTemporaryPath === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            if (is_string($thumbnailTemporaryPath) && is_file($thumbnailTemporaryPath)) {
                unlink($thumbnailTemporaryPath);
            }

            throw new RuntimeException('Video tidak dapat disiapkan untuk verifikasi.');
        }

        try {
            $target = fopen($temporaryPath, 'wb');
            if (! is_resource($target)) {
                throw new RuntimeException('File sementara video tidak dapat dibuka.');
            }

            try {
                if (stream_copy_to_stream($source, $target) === false) {
                    throw new RuntimeException('Video tidak dapat disalin untuk verifikasi.');
                }
            } finally {
                fclose($target);
                fclose($source);
            }

            $process = new Process([
                (string) config('gallery.ffprobe_binary', 'ffprobe'),
                '-v', 'error',
                '-show_entries', 'stream=codec_type,codec_name,width,height:format=duration',
                '-of', 'json',
                $temporaryPath,
            ]);
            $process->setTimeout((float) config('gallery.video_inspection_timeout_seconds', 1800));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new UnexpectedValueException('File bukan video MP4 yang dapat dibaca.');
            }

            $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $streams = is_array($metadata['streams'] ?? null) ? $metadata['streams'] : [];
            $this->assertCompatibleStreams($streams);

            $videoStream = collect($streams)->first(
                fn (mixed $stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'video',
            );
            $width = (int) ($videoStream['width'] ?? 0);
            $height = (int) ($videoStream['height'] ?? 0);
            $duration = max(0, (float) ($metadata['format']['duration'] ?? 0));

            if ($width < 1 || $height < 1) {
                throw new RuntimeException('Dimensi video tidak dapat dibaca.');
            }

            $thumbnailBytes = $this->extractThumbnail(
                $temporaryPath,
                $thumbnailTemporaryPath,
                $duration > 0 ? min(1.0, $duration / 2) : 0.0,
            );
            $thumbnailPath = dirname($media->original_path).'/thumbnail.webp';
            $stored = Storage::disk($media->storage_disk)->put($thumbnailPath, $thumbnailBytes, [
                'visibility' => 'private',
                'ContentType' => 'image/webp',
            ]);

            if (! $stored) {
                throw new RuntimeException('Thumbnail video tidak dapat disimpan.');
            }

            return [
                'thumbnail_path' => $thumbnailPath,
                'width' => $width,
                'height' => $height,
                'duration_seconds' => $duration,
            ];
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            if (is_file($thumbnailTemporaryPath)) {
                unlink($thumbnailTemporaryPath);
            }
        }
    }

    private function extractThumbnail(string $videoPath, string $thumbnailPath, float $seconds): string
    {
        foreach (array_unique([$seconds, 0.0]) as $seekSeconds) {
            if (is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }

            $process = new Process([
                (string) config('gallery.ffmpeg_binary', 'ffmpeg'),
                '-v', 'error',
                '-ss', number_format($seekSeconds, 3, '.', ''),
                '-i', $videoPath,
                '-frames:v', '1',
                '-an',
                '-vf', 'scale=min(960\\,iw):-2',
                '-c:v', 'libwebp',
                '-quality', '82',
                '-f', 'image2',
                '-y',
                $thumbnailPath,
            ]);
            $process->setTimeout((float) config('gallery.video_thumbnail_timeout_seconds', 300));
            $process->run();

            if ($process->isSuccessful() && is_file($thumbnailPath)) {
                $bytes = file_get_contents($thumbnailPath);

                if (is_string($bytes) && $bytes !== '') {
                    return $bytes;
                }
            }
        }

        throw new RuntimeException('Thumbnail video tidak dapat dibuat.');
    }

    /** @param array<int, mixed> $streams */
    public function assertCompatibleStreams(array $streams): void
    {
        $videoCodecs = [];
        $audioCodecs = [];

        foreach ($streams as $stream) {
            if (! is_array($stream)) {
                continue;
            }

            $type = $stream['codec_type'] ?? null;
            $codec = strtolower((string) ($stream['codec_name'] ?? ''));

            if ($type === 'video') {
                $videoCodecs[] = $codec;
            } elseif ($type === 'audio') {
                $audioCodecs[] = $codec;
            }
        }

        if ($videoCodecs !== ['h264'] || collect($audioCodecs)->contains(fn (string $codec): bool => $codec !== 'aac')) {
            throw new UnexpectedValueException('Format video harus H.264 dengan audio AAC.');
        }
    }
}
