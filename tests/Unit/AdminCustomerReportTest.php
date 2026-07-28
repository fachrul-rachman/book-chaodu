<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperType;
use App\Models\Booking;
use App\Models\BookingName;
use App\Models\IncenseSlot;
use App\Models\PrayerPaper;
use App\Models\TableSlot;
use App\Services\AdminReportExportService;
use App\Services\AdminReportService;
use App\Services\InternalCompanySlotService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class AdminCustomerReportTest extends TestCase
{
    public function test_customer_row_contains_all_table_and_incense_numbers(): void
    {
        $booking = new Booking;
        $booking->forceFill([
            'booking_number' => 'CD-TEST-123',
            'created_at' => Carbon::parse('2026-07-28 10:00:00'),
            'status' => BookingStatus::Approved,
            'customer_name' => 'Budi',
            'customer_phone' => '+628123456789',
            'customer_email' => 'budi@example.com',
            'package_code_snapshot' => PackageCode::Combo->value,
            'package_name_snapshot' => 'Paket Combo',
        ]);
        $booking->setRelation('names', new Collection([
            (new BookingName)->forceFill([
                'category' => BookingNameCategory::Deceased,
                'position' => 1,
                'indonesian_name' => 'Nama Doa',
            ]),
        ]));
        $booking->setRelation('prayerPapers', new Collection([
            (new PrayerPaper)->forceFill([
                'type' => PrayerPaperType::A,
                'sequence' => 1,
            ]),
        ]));
        $booking->setRelation('tableSlots', new Collection([
            (new TableSlot)->forceFill(['code' => 'A01', 'allocation_order' => 1]),
            (new TableSlot)->forceFill(['code' => 'A02', 'allocation_order' => 2]),
        ]));
        $booking->setRelation('incenseSlots', new Collection([
            (new IncenseSlot)->forceFill(['number' => 12, 'allocation_order' => 1]),
        ]));

        $slotService = Mockery::mock(InternalCompanySlotService::class);
        self::assertInstanceOf(InternalCompanySlotService::class, $slotService);
        $service = new AdminReportService($slotService);
        $method = new ReflectionMethod($service, 'customerRow');
        $row = $method->invoke($service, $booking);

        self::assertSame('Meja: A01, A02 | Hio: 12', $row['slot_number']);
        self::assertSame('A01, A02', $row['table_number']);
        self::assertSame('12', $row['incense_number']);
    }

    public function test_customer_excel_columns_match_the_requested_report(): void
    {
        $reportService = Mockery::mock(AdminReportService::class);
        self::assertInstanceOf(AdminReportService::class, $reportService);
        $customerExpectation = $reportService->shouldReceive('customer');
        self::assertInstanceOf(Mockery\CompositeExpectation::class, $customerExpectation);
        $customerExpectation->andReturn([
            'rows' => [[
                'booking_number' => 'CD-TEST-123',
                'slot_number' => 'Meja: A01 | Hio: 12',
                'booking_date' => '2026-07-28',
                'customer_name' => 'Budi',
                'customer_phone' => '+628123456789',
                'customer_email' => 'budi@example.com',
                'package_name' => 'Paket Combo',
                'prayer_paper_1' => ['name' => 'Nama Doa 1', 'image_url' => null],
                'prayer_paper_2' => ['name' => null, 'image_url' => null],
                'incense_paper' => ['name' => 'Nama Hio', 'image_url' => null],
            ]],
        ]);

        $spreadsheet = new Spreadsheet;
        $method = new ReflectionMethod(AdminReportExportService::class, 'writeCustomerSheet');
        $method->invoke(
            new AdminReportExportService($reportService),
            $spreadsheet->getActiveSheet(),
            1,
            [],
        );
        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame([
            'Nomor Booking',
            'Nomor Meja/Hio',
            'Tanggal Booking',
            'Nama Customer',
            'Nomor Telepon',
            'Email',
            'Paket',
            'Kertas Doa 1',
            'Kertas Doa 2',
            'Kertas Hio',
        ], $sheet->rangeToArray('A1:J1')[0]);
        self::assertSame('Meja: A01 | Hio: 12', $sheet->getCell('B2')->getValue());
        self::assertSame('+628123456789', $sheet->getCell('E2')->getValue());
        self::assertSame(DataType::TYPE_STRING, $sheet->getCell('E2')->getDataType());
        self::assertSame('-', $sheet->getCell('I2')->getValue());
    }

    public function test_customer_pdf_can_be_rendered(): void
    {
        $reportService = Mockery::mock(AdminReportService::class);
        self::assertInstanceOf(AdminReportService::class, $reportService);
        $customerExpectation = $reportService->shouldReceive('customer');
        self::assertInstanceOf(Mockery\CompositeExpectation::class, $customerExpectation);
        $customerExpectation->andReturn([
            'rows' => [],
        ]);
        $filterExpectation = $reportService->shouldReceive('filterLines');
        self::assertInstanceOf(Mockery\CompositeExpectation::class, $filterExpectation);
        $filterExpectation->andReturn([]);

        $response = (new AdminReportExportService($reportService))->exportPdf('customer', []);

        self::assertSame('application/pdf', $response->headers->get('content-type'));
        self::assertStringContainsString(
            'attachment; filename=customer-',
            (string) $response->headers->get('content-disposition'),
        );
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
    }
}
