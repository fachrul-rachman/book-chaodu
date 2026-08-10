<?php

namespace App\Services;

use App\Enums\GalleryArchiveStatus;
use App\Models\GalleryArchive;
use App\Models\GalleryMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class GalleryArchiveBuilder
{
    public function __construct(
        private PublicGalleryAlbumService $albumService,
        private GalleryArchiveService $archiveService,
    ) {}

    public function build(int $archiveId): void
    {
        $archive = $this->start($archiveId);

        if (! $archive) {
            return;
        }

        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chaodu-gallery-'.Str::uuid();
        $targetPath = "gallery/archives/{$archive->booking_id}/{$archive->fingerprint}.zip";

        try {
            if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
                throw new RuntimeException('Folder sementara ZIP tidak dapat dibuat.');
            }

            $media = $this->albumService->activeMedia($archive->booking);
            if ($media->isEmpty() || $this->archiveService->fingerprint($media) !== $archive->fingerprint) {
                throw new RuntimeException('Isi album berubah saat ZIP disiapkan.');
            }

            $zipPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'album.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('File ZIP tidak dapat dibuat.');
            }

            $closed = false;

            try {
                foreach ($media as $index => $item) {
                    $localPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'media-'.$item->id.'.'.$item->extension;
                    $this->copyToLocal($item, $localPath);
                    $archiveName = $this->archiveFilename($item, $index + 1);

                    if (! $zip->addFile($localPath, $archiveName)) {
                        throw new RuntimeException('Media tidak dapat dimasukkan ke ZIP.');
                    }

                    if ($item->extension === 'mp4') {
                        $zip->setCompressionName($archiveName, ZipArchive::CM_STORE);
                    }
                }
            } finally {
                $closed = $zip->close();
            }

            if (! $closed) {
                throw new RuntimeException('File ZIP tidak dapat diselesaikan.');
            }

            $freshMedia = $this->albumService->activeMedia($archive->booking);
            if ($this->archiveService->fingerprint($freshMedia) !== $archive->fingerprint) {
                throw new RuntimeException('Isi album berubah saat ZIP disiapkan.');
            }

            $stream = fopen($zipPath, 'rb');
            if (! is_resource($stream)) {
                throw new RuntimeException('File ZIP tidak dapat dibaca.');
            }

            try {
                $written = Storage::disk($archive->storage_disk)->writeStream($targetPath, $stream, [
                    'visibility' => 'private',
                    'ContentType' => 'application/zip',
                ]);
            } finally {
                fclose($stream);
            }

            if (! $written) {
                throw new RuntimeException('File ZIP tidak dapat disimpan.');
            }

            $size = filesize($zipPath);
            if (! is_int($size)) {
                throw new RuntimeException('Ukuran ZIP tidak dapat dibaca.');
            }

            $archive->forceFill([
                'status' => GalleryArchiveStatus::Ready,
                'file_path' => $targetPath,
                'size_bytes' => $size,
                'error_message' => null,
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('gallery.archive_ttl_hours', 24)),
            ])->save();
        } catch (Throwable $exception) {
            Storage::disk($archive->storage_disk)->delete($targetPath);
            $archive->forceFill([
                'status' => GalleryArchiveStatus::Failed,
                'file_path' => null,
                'size_bytes' => null,
                'error_message' => 'ZIP gagal dibuat. Silakan coba lagi.',
                'completed_at' => now(),
                'expires_at' => null,
            ])->save();

            throw $exception;
        } finally {
            $this->removeTemporaryDirectory($temporaryDirectory);
        }
    }

    private function start(int $archiveId): ?GalleryArchive
    {
        return DB::transaction(function () use ($archiveId): ?GalleryArchive {
            $archive = GalleryArchive::query()->with('booking')->lockForUpdate()->find($archiveId);

            if (! $archive || $archive->status !== GalleryArchiveStatus::Pending) {
                return null;
            }

            $archive->forceFill([
                'status' => GalleryArchiveStatus::Processing,
                'started_at' => now(),
                'completed_at' => null,
                'error_message' => null,
            ])->save();

            return $archive;
        });
    }

    private function copyToLocal(GalleryMedia $media, string $localPath): void
    {
        $source = Storage::disk($media->storage_disk)->readStream($media->original_path);
        $target = fopen($localPath, 'wb');

        if (! is_resource($source) || ! is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }

            throw new RuntimeException('Original media tidak dapat dibaca.');
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('Original media tidak dapat disalin.');
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    private function archiveFilename(GalleryMedia $media, int $position): string
    {
        $base = pathinfo(basename($media->original_filename), PATHINFO_FILENAME);
        $base = Str::ascii($base);
        $base = preg_replace('/[^A-Za-z0-9]+/', '_', $base) ?: 'media';
        $base = trim($base, '_');
        $base = Str::limit($base === '' ? 'media' : $base, 100, '');

        return sprintf('%03d-%s.%s', $position, $base, strtolower($media->extension));
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \FilesystemIterator($directory) as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isFile()) {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
