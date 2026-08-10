<?php

namespace App\Services;

use App\Models\GalleryMedia;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use UnexpectedValueException;

class GalleryVideoInspector
{
    public function inspect(GalleryMedia $media): void
    {
        $source = Storage::disk($media->storage_disk)->readStream($media->original_path);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'chaodu-gallery-video-');

        if (! is_resource($source) || $temporaryPath === false) {
            if (is_resource($source)) {
                fclose($source);
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
                '-show_entries', 'stream=codec_type,codec_name',
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
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
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
