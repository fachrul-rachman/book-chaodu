<?php

namespace App\Services;

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GalleryMediaService
{
    public function __construct(
        private readonly GalleryStorageService $galleryStorageService,
    ) {}

    public function storeOriginal(
        UploadedFile $file,
        GalleryMediaScope $scope,
        User $uploader,
        ?Booking $booking = null,
    ): GalleryMedia {
        $this->validateTarget($scope, $booking);

        if (! $uploader->exists) {
            throw new DomainException('Uploader gallery harus merupakan user yang tersimpan.');
        }

        $mimeType = (string) $file->getMimeType();
        $mediaType = $this->mediaType($mimeType);
        $uuid = (string) Str::uuid();
        $path = $this->galleryStorageService->storeOriginal(
            $file,
            $scope,
            $uuid,
            $booking?->id,
        );

        try {
            return GalleryMedia::query()->create([
                'uuid' => $uuid,
                'scope' => $scope,
                'booking_id' => $booking?->id,
                'media_type' => $mediaType,
                'status' => GalleryMediaStatus::Processing,
                'storage_disk' => $this->galleryStorageService->diskName(),
                'original_path' => $path,
                'original_filename' => $this->safeOriginalName($file),
                'stored_filename' => basename($path),
                'mime_type' => $mimeType,
                'extension' => strtolower((string) $file->extension()),
                'size_bytes' => max(0, (int) $file->getSize()),
                'uploaded_by' => $uploader->id,
            ]);
        } catch (Throwable $throwable) {
            try {
                $this->galleryStorageService->delete($path);
            } catch (Throwable $cleanupError) {
                report($cleanupError);
            }

            throw $throwable;
        }
    }

    private function validateTarget(GalleryMediaScope $scope, ?Booking $booking): void
    {
        if ($scope === GalleryMediaScope::Global && $booking !== null) {
            throw new DomainException('Media global tidak boleh terhubung ke booking.');
        }

        if ($scope === GalleryMediaScope::Booking && $booking === null) {
            throw new DomainException('Media customer wajib terhubung ke booking.');
        }

        if ($booking !== null && ! $booking->exists) {
            throw new DomainException('Booking tujuan belum tersimpan.');
        }
    }

    private function mediaType(string $mimeType): GalleryMediaType
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => GalleryMediaType::Image,
            str_starts_with($mimeType, 'video/') => GalleryMediaType::Video,
            default => throw new RuntimeException('Tipe media gallery tidak didukung.'),
        };
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $normalized = str_replace('\\', '/', $file->getClientOriginalName());

        return basename($normalized);
    }
}
