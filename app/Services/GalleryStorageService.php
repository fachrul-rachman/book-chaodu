<?php

namespace App\Services;

use App\Enums\GalleryMediaScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GalleryStorageService
{
    public function diskName(): string
    {
        $diskName = config('gallery.storage_disk');

        if (! is_string($diskName) || $diskName === '') {
            throw new RuntimeException('Disk penyimpanan gallery belum dikonfigurasi.');
        }

        return $diskName;
    }

    public function storeOriginal(
        UploadedFile $file,
        GalleryMediaScope $scope,
        string $uuid,
        ?int $bookingId = null,
    ): string {
        $extension = strtolower((string) $file->extension());

        if ($extension === '') {
            throw new RuntimeException('Extension file gallery tidak dapat dikenali.');
        }

        $directory = $scope === GalleryMediaScope::Global
            ? 'gallery/global/'.$uuid
            : 'gallery/bookings/'.$bookingId.'/'.$uuid;
        $filename = 'original.'.$extension;

        $storedPath = Storage::disk($this->diskName())->putFileAs(
            $directory,
            $file,
            $filename,
            [
                'visibility' => 'private',
                'ContentType' => (string) $file->getMimeType(),
            ],
        );

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('File gallery tidak berhasil disimpan.');
        }

        return $storedPath;
    }

    public function delete(string $path): void
    {
        if ($path === '') {
            return;
        }

        Storage::disk($this->diskName())->delete($path);
    }
}
