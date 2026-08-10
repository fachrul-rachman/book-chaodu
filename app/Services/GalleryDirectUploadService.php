<?php

namespace App\Services;

use App\Models\GalleryMedia;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GalleryDirectUploadService
{
    /** @return array<string, mixed> */
    public function initiate(GalleryMedia $media): array
    {
        if ($media->upload_mode === 'SINGLE') {
            $result = Storage::disk($media->storage_disk)->temporaryUploadUrl(
                $media->original_path,
                now()->addMinutes((int) config('gallery.upload_url_ttl_minutes')),
                ['ContentType' => $media->mime_type],
            );

            return [
                'mode' => 'single',
                'url' => $result['url'],
                'headers' => $result['headers'],
            ];
        }

        if ($media->upload_id) {
            return [
                'mode' => 'multipart',
                'partSize' => (int) config('gallery.multipart_part_size_bytes'),
            ];
        }

        $client = $this->client($media);
        $result = $client->createMultipartUpload([
            'Bucket' => $this->bucket($media),
            'Key' => $media->original_path,
            'ContentType' => $media->mime_type,
        ]);
        $uploadId = $result->get('UploadId');

        if (! is_string($uploadId) || $uploadId === '') {
            throw new RuntimeException('R2 tidak mengembalikan ID multipart upload.');
        }

        $media->forceFill(['upload_id' => $uploadId])->save();

        return [
            'mode' => 'multipart',
            'partSize' => (int) config('gallery.multipart_part_size_bytes'),
        ];
    }

    /** @return array{url: string, headers: array<string, string>} */
    public function signPart(GalleryMedia $media, int $partNumber): array
    {
        $client = $this->client($media);
        $command = $client->getCommand('UploadPart', [
            'Bucket' => $this->bucket($media),
            'Key' => $media->original_path,
            'UploadId' => $media->upload_id,
            'PartNumber' => $partNumber,
        ]);
        $request = $client->createPresignedRequest(
            $command,
            now()->addMinutes((int) config('gallery.upload_url_ttl_minutes')),
        );

        return ['url' => (string) $request->getUri(), 'headers' => []];
    }

    /** @param array<int, array{part_number: int, etag: string}> $parts */
    public function complete(GalleryMedia $media, array $parts): void
    {
        if ($media->upload_mode !== 'MULTIPART') {
            return;
        }

        $this->client($media)->completeMultipartUpload([
            'Bucket' => $this->bucket($media),
            'Key' => $media->original_path,
            'UploadId' => $media->upload_id,
            'MultipartUpload' => [
                'Parts' => array_map(fn (array $part): array => [
                    'ETag' => $part['etag'],
                    'PartNumber' => $part['part_number'],
                ], $parts),
            ],
        ]);
    }

    public function abort(GalleryMedia $media): void
    {
        if ($media->upload_mode !== 'MULTIPART' || ! $media->upload_id) {
            return;
        }

        $this->client($media)->abortMultipartUpload([
            'Bucket' => $this->bucket($media),
            'Key' => $media->original_path,
            'UploadId' => $media->upload_id,
        ]);
    }

    private function client(GalleryMedia $media): S3Client
    {
        $disk = Storage::disk($media->storage_disk);

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new RuntimeException('Direct upload hanya tersedia pada disk S3/R2.');
        }

        return $disk->getClient();
    }

    private function bucket(GalleryMedia $media): string
    {
        $bucket = config('filesystems.disks.'.$media->storage_disk.'.bucket');

        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException('Bucket gallery belum dikonfigurasi.');
        }

        return $bucket;
    }
}
