<?php

namespace App\Services;

use App\Models\GalleryMedia;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GalleryVideoStreamService
{
    public function response(GalleryMedia $media, ?string $rangeHeader): StreamedResponse
    {
        $disk = Storage::disk($media->storage_disk);
        abort_unless($disk->exists($media->original_path), 404);

        $size = $media->size_bytes;
        abort_unless($size > 0, 404);
        $range = $this->parseRange($rangeHeader, $size);

        if ($range === false) {
            return new StreamedResponse(null, 416, [
                'Accept-Ranges' => 'bytes',
                'Content-Range' => 'bytes */'.$size,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
        }

        [$start, $end] = $range ?? [0, $size - 1];
        $length = $end - $start + 1;
        $partial = $range !== null;
        $response = new StreamedResponse(
            fn () => $this->stream($disk, $media->original_path, $start, $end, $partial),
            $partial ? 206 : 200,
            [
                'Accept-Ranges' => 'bytes',
                'Content-Type' => 'video/mp4',
                'Content-Length' => (string) $length,
                'Cache-Control' => 'private, max-age='.(int) config('gallery.preview_cache_seconds'),
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ],
        );

        if ($partial) {
            $response->headers->set('Content-Range', "bytes {$start}-{$end}/{$size}");
        }

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                'media-'.$media->uuid.'.mp4',
            ),
        );

        return $response;
    }

    /** @return array{int, int}|null|false */
    private function parseRange(?string $header, int $size): array|null|false
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) !== 1) {
            return false;
        }

        if ($matches[1] === '' && $matches[2] === '') {
            return false;
        }

        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];

            if ($suffixLength <= 0) {
                return false;
            }

            return [max(0, $size - $suffixLength), $size - 1];
        }

        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);

        if ($start >= $size || $end < $start) {
            return false;
        }

        return [$start, $end];
    }

    private function stream(
        FilesystemAdapter $disk,
        string $path,
        int $start,
        int $end,
        bool $partial,
    ): void {
        if ($disk instanceof AwsS3V3Adapter) {
            $this->streamFromS3($disk, $path, $start, $end, $partial);

            return;
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Video tidak dapat dibaca dari penyimpanan.');
        }

        try {
            $this->seek($stream, $start);
            $this->outputResource($stream, $end - $start + 1);
        } finally {
            fclose($stream);
        }
    }

    private function streamFromS3(
        AwsS3V3Adapter $disk,
        string $path,
        int $start,
        int $end,
        bool $partial,
    ): void {
        $config = $disk->getConfig();
        $bucket = $config['bucket'] ?? null;

        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException('Bucket video gallery belum dikonfigurasi.');
        }

        $root = is_string($config['root'] ?? null) ? trim($config['root'], '/') : '';
        $key = $root === '' ? $path : $root.'/'.ltrim($path, '/');
        $options = ['Bucket' => $bucket, 'Key' => $key];

        if ($partial) {
            $options['Range'] = "bytes={$start}-{$end}";
        }

        $result = $disk->getClient()->getObject($options);
        $body = $result['Body'] ?? null;

        if (! $body instanceof StreamInterface) {
            throw new RuntimeException('Stream video R2 tidak tersedia.');
        }

        $remaining = $end - $start + 1;

        while ($remaining > 0 && ! $body->eof()) {
            $chunk = $body->read(min(1024 * 1024, $remaining));

            if ($chunk === '') {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);
        }
    }

    /** @param resource $stream */
    private function seek($stream, int $offset): void
    {
        if ($offset === 0 || fseek($stream, $offset) === 0) {
            return;
        }

        $remaining = $offset;

        while ($remaining > 0 && ! feof($stream)) {
            $chunk = fread($stream, min(1024 * 1024, $remaining));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Posisi video tidak dapat dibaca.');
            }

            $remaining -= strlen($chunk);
        }

        if ($remaining > 0) {
            throw new RuntimeException('Posisi video berada di luar file.');
        }
    }

    /** @param resource $stream */
    private function outputResource($stream, int $length): void
    {
        $remaining = $length;

        while ($remaining > 0 && ! feof($stream)) {
            $chunk = fread($stream, min(1024 * 1024, $remaining));

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);
        }
    }
}
