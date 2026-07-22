<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PrayerPaperType;
use App\Http\Controllers\Printer\PrayerPaperFileController;
use App\Models\Booking;
use App\Models\BookingName;
use App\Models\PrayerPaper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class PrinterPrayerPaperFileControllerTest extends TestCase
{
    public function test_it_downloads_an_r2_file_through_the_application_with_a_clear_filename(): void
    {
        config()->set('phase5.storage_disk', 'prayer-paper-files');
        Storage::fake('prayer-paper-files');
        config()->set('filesystems.disks.prayer-paper-files.driver', 's3');

        Storage::disk('prayer-paper-files')->put('papers/prayer.png', 'image-content');

        $booking = new Booking;
        $booking->forceFill([
            'booking_number' => 'CD-TEST-123',
            'status' => BookingStatus::Approved,
        ]);
        $booking->setRelation('names', new Collection([
            (new BookingName)->forceFill([
                'category' => BookingNameCategory::Deceased,
                'position' => 1,
                'indonesian_name' => 'Tan Ah Kok',
                'mandarin_name' => null,
            ]),
        ]));

        $paper = new PrayerPaper;
        $paper->forceFill([
            'type' => PrayerPaperType::A,
            'sequence' => 1,
            'file_path' => 'papers/prayer.png',
        ]);
        $paper->setRelation('booking', $booking);

        $response = app(PrayerPaperFileController::class)($paper);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertStringContainsString(
            'attachment; filename=CD-TEST-123-kertas-doa-1-tan-ah-kok.png',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_it_streams_an_r2_file_without_requesting_file_metadata(): void
    {
        config()->set('phase5.storage_disk', 'prayer-paper-files');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'image-content');
        rewind($stream);

        $disk = Mockery::mock(FilesystemAdapter::class);
        $readStreamExpectation = $disk->shouldReceive('readStream');
        self::assertInstanceOf(Mockery\CompositeExpectation::class, $readStreamExpectation);
        $readStreamExpectation->andReturn($stream);
        $disk->shouldNotReceive('download');
        $disk->shouldNotReceive('size');

        Storage::shouldReceive('disk')
            ->once()
            ->with('prayer-paper-files')
            ->andReturn($disk);

        $booking = new Booking;
        $booking->forceFill([
            'booking_number' => 'CD-TEST-123',
            'status' => BookingStatus::Approved,
        ]);
        $booking->setRelation('names', new Collection([
            (new BookingName)->forceFill([
                'category' => BookingNameCategory::Deceased,
                'position' => 1,
                'indonesian_name' => 'Tan Ah Kok',
                'mandarin_name' => null,
            ]),
        ]));

        $paper = new PrayerPaper;
        $paper->forceFill([
            'type' => PrayerPaperType::A,
            'sequence' => 1,
            'file_path' => 'papers/prayer.png',
        ]);
        $paper->setRelation('booking', $booking);

        $response = app(PrayerPaperFileController::class)($paper);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        self::assertSame('image-content', $content);
    }
}
