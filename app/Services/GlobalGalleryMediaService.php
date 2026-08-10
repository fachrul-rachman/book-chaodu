<?php

namespace App\Services;

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Jobs\ProcessGalleryImage;
use App\Jobs\ProcessGalleryVideo;
use App\Models\GalleryMedia;
use App\Models\GalleryMediaDeletion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GlobalGalleryMediaService
{
    public function __construct(
        private GalleryDirectUploadService $directUpload,
        private GalleryStorageService $storage,
    ) {}

    /** @param array{original_filename: string, upload_token: string, mime_type: string, size_bytes: int, caption?: string|null} $data
     * @return array{media: GalleryMedia, upload: array<string, mixed>}
     */
    public function initiate(array $data, User $uploader): array
    {
        $existing = GalleryMedia::query()->where('upload_token', $data['upload_token'])->first();

        if ($existing) {
            if ($existing->uploaded_by !== $uploader->id) {
                throw ValidationException::withMessages(['upload_token' => 'Token upload tidak valid.']);
            }

            return ['media' => $existing, 'upload' => $this->directUpload->initiate($existing)];
        }

        $uuid = (string) Str::uuid();
        $extension = strtolower((string) pathinfo($data['original_filename'], PATHINFO_EXTENSION));
        $type = str_starts_with($data['mime_type'], 'image/') ? GalleryMediaType::Image : GalleryMediaType::Video;
        $uploadMode = $data['size_bytes'] <= (int) config('gallery.single_upload_max_bytes') ? 'SINGLE' : 'MULTIPART';

        $media = GalleryMedia::query()->firstOrCreate(['upload_token' => $data['upload_token']], [
            'uuid' => $uuid,
            'scope' => GalleryMediaScope::Global,
            'media_type' => $type,
            'status' => GalleryMediaStatus::Processing,
            'storage_disk' => $this->storage->diskName(),
            'original_path' => "gallery/global/{$uuid}/original.{$extension}",
            'original_filename' => $data['original_filename'],
            'stored_filename' => "original.{$extension}",
            'mime_type' => $data['mime_type'],
            'extension' => $extension,
            'size_bytes' => $data['size_bytes'],
            'caption' => $data['caption'] ?? null,
            'upload_mode' => $uploadMode,
            'upload_expires_at' => now()->addMinutes((int) config('gallery.upload_url_ttl_minutes')),
            'uploaded_by' => $uploader->id,
        ]);

        if (! $media->wasRecentlyCreated) {
            if ($media->uploaded_by !== $uploader->id) {
                throw ValidationException::withMessages(['upload_token' => 'Token upload tidak valid.']);
            }

            return ['media' => $media, 'upload' => $this->directUpload->initiate($media)];
        }

        try {
            return ['media' => $media, 'upload' => $this->directUpload->initiate($media)];
        } catch (Throwable $exception) {
            $media->delete();
            throw $exception;
        }
    }

    /** @param array<int, array{part_number: int, etag: string}> $parts */
    public function complete(GalleryMedia $media, array $parts): GalleryMedia
    {
        $this->assertGlobal($media);

        if (in_array($media->status, [GalleryMediaStatus::Ready, GalleryMediaStatus::Hidden], true)
            || ($media->status === GalleryMediaStatus::Processing && $media->upload_expires_at === null)) {
            return $media;
        }

        if ($media->status !== GalleryMediaStatus::Processing) {
            throw ValidationException::withMessages(['media' => 'Upload ini tidak dapat diselesaikan.']);
        }

        if ($media->upload_mode === 'MULTIPART') {
            $expectedPartCount = (int) ceil($media->size_bytes / (int) config('gallery.multipart_part_size_bytes'));
            usort($parts, fn (array $left, array $right): int => $left['part_number'] <=> $right['part_number']);

            if (array_column($parts, 'part_number') !== range(1, $expectedPartCount)) {
                throw ValidationException::withMessages(['parts' => 'Daftar bagian video tidak lengkap.']);
            }
        }

        $this->directUpload->complete($media, $parts);
        $disk = Storage::disk($media->storage_disk);

        try {
            if (! $disk->exists($media->original_path) || $disk->size($media->original_path) !== $media->size_bytes) {
                throw ValidationException::withMessages(['file' => 'Ukuran file yang diterima tidak sesuai.']);
            }

            if (! $this->signatureMatches($media)) {
                throw ValidationException::withMessages(['file' => 'Isi file tidak sesuai dengan tipe yang dipilih.']);
            }
        } catch (ValidationException $exception) {
            $disk->delete($media->original_path);
            $media->forceFill([
                'status' => GalleryMediaStatus::Failed,
                'error_message' => $exception->errors()['file'][0] ?? 'File gagal diverifikasi.',
            ])->save();
            throw $exception;
        }

        $media->forceFill([
            'status' => GalleryMediaStatus::Processing,
            'upload_id' => null,
            'upload_expires_at' => null,
            'error_message' => null,
            'published_at' => null,
        ])->save();

        if ($media->media_type === GalleryMediaType::Image) {
            ProcessGalleryImage::dispatch($media->id);
        } else {
            ProcessGalleryVideo::dispatch($media->id);
        }

        return $media->refresh();
    }

    /** @param array<int, int> $ids */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            $lockedIds = GalleryMedia::query()
                ->where('scope', GalleryMediaScope::Global)
                ->whereIn('id', $ids)
                ->select('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($lockedIds->count() !== count($ids)) {
                throw ValidationException::withMessages(['media_ids' => 'Daftar media global tidak valid.']);
            }

            foreach ($ids as $position => $id) {
                GalleryMedia::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function delete(GalleryMedia $media, User $user): void
    {
        $this->assertGlobal($media);

        if ($media->status === GalleryMediaStatus::Processing && $media->upload_mode === 'MULTIPART') {
            $this->directUpload->abort($media);
        }

        $paths = array_values(array_filter([$media->original_path, $media->preview_path, $media->thumbnail_path]));
        Storage::disk($media->storage_disk)->delete($paths);

        DB::transaction(function () use ($media, $user): void {
            GalleryMediaDeletion::query()->create([
                'media_uuid' => $media->uuid,
                'scope' => $media->scope->value,
                'media_type' => $media->media_type->value,
                'original_filename' => $media->original_filename,
                'deleted_by' => $user->id,
                'deleted_at' => now(),
            ]);
            $media->delete();
        });
    }

    private function signatureMatches(GalleryMedia $media): bool
    {
        $disk = Storage::disk($media->storage_disk);
        $stream = $disk->readStream($media->original_path);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            $bytes = fread($stream, 32) ?: '';
        } finally {
            fclose($stream);
        }

        $signatureMatches = match ($media->mime_type) {
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1A\n"),
            'image/webp' => substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP',
            'video/mp4' => substr($bytes, 4, 4) === 'ftyp',
            default => false,
        };

        if (! $signatureMatches || $media->media_type !== GalleryMediaType::Image) {
            return $signatureMatches;
        }

        return @getimagesizefromstring($disk->get($media->original_path)) !== false;
    }

    private function assertGlobal(GalleryMedia $media): void
    {
        if ($media->scope !== GalleryMediaScope::Global) {
            throw ValidationException::withMessages(['media' => 'Media bukan bagian dari album global.']);
        }
    }
}
