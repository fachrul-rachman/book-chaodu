<?php

namespace App\Http\Controllers\Printer;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Models\BookingName;
use App\Models\PrayerPaper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrayerPaperFileController extends Controller
{
    public function __invoke(PrayerPaper $prayerPaper): StreamedResponse
    {
        abort_if($prayerPaper->booking?->status !== BookingStatus::Approved, 404);
        abort_if(blank($prayerPaper->file_path), 404);

        $disk = Storage::disk((string) config('phase5.storage_disk'));
        $stream = $disk->readStream($prayerPaper->file_path);

        abort_if(! is_resource($stream), 404);

        return response()->streamDownload(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            $this->fileName($prayerPaper),
            ['Content-Type' => $this->contentType($prayerPaper)],
        );
    }

    private function contentType(PrayerPaper $prayerPaper): string
    {
        return match (Str::lower(pathinfo((string) $prayerPaper->file_path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function fileName(PrayerPaper $prayerPaper): string
    {
        $booking = $prayerPaper->booking;
        $type = $this->paperType($prayerPaper);
        $label = $type === PrayerPaperType::A
            ? 'kertas-doa-'.max(1, (int) $prayerPaper->sequence)
            : 'kertas-hio';
        $name = $this->paperName($prayerPaper);
        $nameSlug = Str::slug($name) ?: 'nama';
        $extension = pathinfo((string) $prayerPaper->file_path, PATHINFO_EXTENSION) ?: 'png';

        return sprintf(
            '%s-%s-%s.%s',
            $booking->booking_number,
            $label,
            $nameSlug,
            $extension,
        );
    }

    private function paperName(PrayerPaper $prayerPaper): string
    {
        $booking = $prayerPaper->booking;

        if (! $booking) {
            return '';
        }

        $type = $this->paperType($prayerPaper);
        $names = $booking->names
            ->where(
                'category',
                $type === PrayerPaperType::A
                    ? BookingNameCategory::Deceased
                    : BookingNameCategory::Incense,
            )
            ->sortBy('position')
            ->values();
        $name = $type === PrayerPaperType::A
            ? $names->get(max(0, ((int) $prayerPaper->sequence) - 1))
            : $names->first();

        return $name instanceof BookingName
            ? (trim((string) $name->mandarin_name) ?: trim((string) $name->indonesian_name))
            : '';
    }

    private function paperType(PrayerPaper $paper): PrayerPaperType
    {
        $type = $paper->getAttribute('type');

        return $type instanceof PrayerPaperType
            ? $type
            : PrayerPaperType::from((string) $type);
    }
}
