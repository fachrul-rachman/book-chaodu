<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Booking;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GallerySettingService
{
    public const EVENT_NAME = 'gallery_event_name';

    public const EVENT_DATE = 'gallery_event_date';

    public const ALBUM_TITLE = 'gallery_album_title';

    public const EMPTY_STATE_TEXT = 'gallery_empty_state_text';

    public const WALLPAPER_PATH = 'gallery_wallpaper_path';

    public const WALLPAPER_MIME_TYPE = 'gallery_wallpaper_mime_type';

    /** @return array{event_name:string,event_date:string,album_title:string,empty_state_text:string,wallpaper_path:string|null,wallpaper_mime_type:string|null} */
    public function values(): array
    {
        $stored = AppSetting::getMany($this->keys());

        return [
            'event_name' => $stored[self::EVENT_NAME] ?: (string) config('gallery.event_name', 'Chao Du'),
            'event_date' => $stored[self::EVENT_DATE] ?: (string) config('gallery.event_date', ''),
            'album_title' => $stored[self::ALBUM_TITLE] ?: (string) config('gallery.album_title', 'Album Dokumentasi Acara'),
            'empty_state_text' => $stored[self::EMPTY_STATE_TEXT] ?: (string) config('gallery.empty_state_text', 'Dokumentasi acara belum tersedia.'),
            'wallpaper_path' => $stored[self::WALLPAPER_PATH],
            'wallpaper_mime_type' => $stored[self::WALLPAPER_MIME_TYPE],
        ];
    }

    /**
     * @param  array{event_name:string,event_date:string,album_title:string,empty_state_text:string}  $values
     */
    public function update(array $values, ?UploadedFile $wallpaper): void
    {
        $current = $this->values();
        $newPath = null;
        $newMimeType = null;

        if ($wallpaper) {
            $extension = strtolower((string) $wallpaper->extension());
            $newPath = 'gallery/settings/wallpaper-'.Str::uuid().'.'.$extension;
            $newMimeType = (string) $wallpaper->getMimeType();
            $stored = Storage::disk($this->diskName())->putFileAs(
                'gallery/settings',
                $wallpaper,
                basename($newPath),
                ['visibility' => 'private', 'ContentType' => $newMimeType],
            );

            if (! is_string($stored) || $stored === '') {
                throw new RuntimeException('Wallpaper galeri tidak berhasil disimpan.');
            }

            $newPath = $stored;
        }

        try {
            DB::transaction(function () use ($values, $newPath, $newMimeType): void {
                AppSetting::putMany([
                    self::EVENT_NAME => trim($values['event_name']),
                    self::EVENT_DATE => $values['event_date'],
                    self::ALBUM_TITLE => trim($values['album_title']),
                    self::EMPTY_STATE_TEXT => trim($values['empty_state_text']),
                    ...($newPath ? [
                        self::WALLPAPER_PATH => $newPath,
                        self::WALLPAPER_MIME_TYPE => $newMimeType,
                    ] : []),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk($this->diskName())->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath && $current['wallpaper_path'] && $current['wallpaper_path'] !== $newPath) {
            Storage::disk($this->diskName())->delete($current['wallpaper_path']);
        }
    }

    /** @return array{bookingNumber:string,eventName:string,eventDate:string,title:string,emptyStateText:string,wallpaperUrl:string|null} */
    public function albumIdentity(Booking $booking): array
    {
        $settings = $this->values();

        return [
            'bookingNumber' => $booking->booking_number,
            'eventName' => $settings['event_name'],
            'eventDate' => $this->eventDateLabel($settings['event_date']),
            'title' => $settings['album_title'],
            'emptyStateText' => $settings['empty_state_text'],
            'wallpaperUrl' => $settings['wallpaper_path']
                ? route('public.gallery.wallpaper', ['bookingNumber' => $booking->booking_number])
                : null,
        ];
    }

    public function diskName(): string
    {
        $disk = config('gallery.storage_disk');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('Disk penyimpanan galeri belum dikonfigurasi.');
        }

        return $disk;
    }

    public function wallpaperPath(): string
    {
        $path = $this->values()['wallpaper_path'];
        abort_unless(is_string($path) && $path !== '', 404);

        return $path;
    }

    public function wallpaperMimeType(): string
    {
        return $this->values()['wallpaper_mime_type'] ?: 'application/octet-stream';
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        return [
            self::EVENT_NAME,
            self::EVENT_DATE,
            self::ALBUM_TITLE,
            self::EMPTY_STATE_TEXT,
            self::WALLPAPER_PATH,
            self::WALLPAPER_MIME_TYPE,
        ];
    }

    private function eventDateLabel(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return 'Tanggal acara akan diumumkan';
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            return 'Tanggal acara akan diumumkan';
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $day.' '.$months[$month].' '.$year;
    }
}
