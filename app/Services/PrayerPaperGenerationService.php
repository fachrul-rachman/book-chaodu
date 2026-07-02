<?php

namespace App\Services;

use App\Enums\BookingNameCategory;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use App\Models\Booking;
use App\Models\PrayerPaper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrayerPaperGenerationService
{
    public function __construct(
        private readonly PrayerPaperRenderer $renderer,
    ) {}

    public function createPendingRows(Booking $booking): void
    {
        $booking = $this->syncRows($booking);
        $this->syncBookingStatus($booking);
    }

    public function generateForBooking(Booking $booking): void
    {
        if (! config('phase5.enabled')) {
            return;
        }

        $booking = $this->syncRows($booking);

        foreach ($booking->prayerPapers->sortBy(['type', 'sequence']) as $paper) {
            $this->generateSingle($booking, $paper);
        }

        $this->syncBookingStatus($booking->fresh('prayerPapers') ?? $booking);
    }

    public function retry(Booking $booking): void
    {
        $booking = $this->syncRows($booking);

        foreach ($booking->prayerPapers->sortBy(['type', 'sequence']) as $paper) {
            $status = PrayerPaperStatus::from((string) $paper->getRawOriginal('status'));

            if (in_array($status, [PrayerPaperStatus::Failed, PrayerPaperStatus::Pending], true)) {
                $this->generateSingle($booking, $paper);
            }
        }

        $this->syncBookingStatus($booking->fresh('prayerPapers') ?? $booking);
    }

    private function generateSingle(Booking $booking, PrayerPaper $paper): void
    {
        $type = PrayerPaperType::from((string) $paper->getRawOriginal('type'));

        $paper->forceFill([
            'status' => PrayerPaperStatus::Processing,
            'error_message' => null,
        ])->save();

        try {
            $rendered = $this->renderer->render($booking, $type, $this->namesForPaper($booking, $paper));
            $nextVersion = max(1, $paper->version + 1);
            $newPath = $this->buildPath($booking, $type, $paper->sequence, $nextVersion, $rendered['extension']);

            Storage::disk((string) config('phase5.storage_disk'))->put($newPath, $rendered['content'], [
                'ContentType' => $rendered['content_type'],
            ]);

            $oldPath = $paper->file_path;

            $paper->forceFill([
                'file_path' => $newPath,
                'version' => $nextVersion,
                'status' => PrayerPaperStatus::Ready,
                'error_message' => null,
                'generated_at' => now(),
            ])->save();

            if ($oldPath && $oldPath !== $newPath) {
                Storage::disk((string) config('phase5.storage_disk'))->delete($oldPath);
            }
        } catch (\Throwable $exception) {
            $paper->forceFill([
                'status' => PrayerPaperStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 500),
            ])->save();
        }
    }

    private function syncRows(Booking $booking): Booking
    {
        $booking = $booking->fresh(['names', 'prayerPapers']) ?? $booking;
        $requiredRows = $this->requiredRows($booking);
        $requiredKeys = collect($requiredRows)
            ->mapWithKeys(fn (array $row): array => [$this->rowKey($row['type'], $row['sequence']) => $row])
            ->all();

        foreach ($booking->prayerPapers as $paper) {
            $key = $this->rowKey(
                PrayerPaperType::from((string) $paper->getRawOriginal('type')),
                (int) ($paper->sequence ?: 1),
            );

            if (isset($requiredKeys[$key])) {
                continue;
            }

            if ($paper->file_path) {
                Storage::disk((string) config('phase5.storage_disk'))->delete($paper->file_path);
            }

            $paper->delete();
        }

        foreach ($requiredRows as $row) {
            PrayerPaper::query()->firstOrCreate(
                [
                    'booking_id' => $booking->id,
                    'type' => $row['type']->value,
                    'sequence' => $row['sequence'],
                ],
                [
                    'status' => PrayerPaperStatus::Pending->value,
                ],
            );
        }

        return $booking->fresh(['names', 'prayerPapers']) ?? $booking;
    }

    /**
     * @return array<int, array{type:PrayerPaperType, sequence:int}>
     */
    private function requiredRows(Booking $booking): array
    {
        $packageCode = PackageCode::from($booking->package_code_snapshot);
        $rows = [];

        if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
            $deceasedCount = max(1, count($this->deceasedEntries($booking)));

            for ($sequence = 1; $sequence <= $deceasedCount; $sequence++) {
                $rows[] = [
                    'type' => PrayerPaperType::A,
                    'sequence' => $sequence,
                ];
            }
        }

        if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
            $rows[] = [
                'type' => PrayerPaperType::B,
                'sequence' => 1,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{text:string, vertical:bool}>
     */
    private function namesForPaper(Booking $booking, PrayerPaper $paper): array
    {
        $type = PrayerPaperType::from((string) $paper->getRawOriginal('type'));

        if ($type === PrayerPaperType::A) {
            $entries = $this->deceasedEntries($booking);
            $index = max(0, ((int) $paper->sequence) - 1);

            return isset($entries[$index]) ? [$entries[$index]] : [];
        }

        $entry = $this->incenseEntry($booking);

        return $entry ? [$entry] : [];
    }

    /**
     * @return array<int, array{text:string, vertical:bool}>
     */
    private function deceasedEntries(Booking $booking): array
    {
        return $booking->names
            ->where('category', BookingNameCategory::Deceased)
            ->sortBy('position')
            ->map(fn ($name): ?array => $this->nameEntry($name->indonesian_name, $name->mandarin_name))
            ->filter()
            ->values()
            ->take(2)
            ->all();
    }

    /**
     * @return array{text:string, vertical:bool}|null
     */
    private function incenseEntry(Booking $booking): ?array
    {
        $name = $booking->names
            ->where('category', BookingNameCategory::Incense)
            ->sortBy('position')
            ->first();

        if (! $name) {
            return null;
        }

        return $this->nameEntry($name->indonesian_name, $name->mandarin_name);
    }

    /**
     * @return array{text:string, vertical:bool}|null
     */
    private function nameEntry(?string $indonesianName, ?string $mandarinName): ?array
    {
        $mandarin = trim((string) ($mandarinName ?? ''));
        $indonesian = trim((string) ($indonesianName ?? ''));
        $display = $mandarin !== '' ? $mandarin : $indonesian;

        if ($display === '') {
            return null;
        }

        return [
            'text' => $display,
            'vertical' => $mandarin !== '',
        ];
    }

    private function buildPath(Booking $booking, PrayerPaperType $type, int $sequence, int $version, string $extension): string
    {
        return sprintf(
            'prayer-papers/%s/%s-%d-v%d.%s',
            $booking->booking_number,
            Str::lower($type->value),
            $sequence,
            $version,
            $extension,
        );
    }

    private function rowKey(PrayerPaperType $type, int $sequence): string
    {
        return $type->value.'-'.$sequence;
    }

    private function syncBookingStatus(Booking $booking): void
    {
        $papers = $booking->prayerPapers;

        if ($papers->isEmpty()) {
            return;
        }

        $status = PrayerPaperStatus::Pending;
        $error = null;
        $latestGeneratedAt = $papers->max('generated_at');

        if ($papers->every(function (PrayerPaper $paper): bool {
            return PrayerPaperStatus::from((string) $paper->getRawOriginal('status')) === PrayerPaperStatus::Ready;
        })) {
            $status = PrayerPaperStatus::Ready;
        } elseif ($papers->contains(function (PrayerPaper $paper): bool {
            return PrayerPaperStatus::from((string) $paper->getRawOriginal('status')) === PrayerPaperStatus::Failed;
        })) {
            $status = PrayerPaperStatus::Failed;
            $error = $papers
                ->filter(function (PrayerPaper $paper): bool {
                    return PrayerPaperStatus::from((string) $paper->getRawOriginal('status')) === PrayerPaperStatus::Failed;
                })
                ->pluck('error_message')
                ->filter()
                ->implode(' | ');
        } elseif ($papers->contains(function (PrayerPaper $paper): bool {
            return PrayerPaperStatus::from((string) $paper->getRawOriginal('status')) === PrayerPaperStatus::Processing;
        })) {
            $status = PrayerPaperStatus::Processing;
        }

        DB::table('bookings')
            ->where('id', $booking->id)
            ->update([
                'prayer_paper_status' => $status->value,
                'prayer_paper_error' => $error,
                'latest_prayer_paper_generated_at' => $latestGeneratedAt,
                'updated_at' => now(),
            ]);
    }
}
