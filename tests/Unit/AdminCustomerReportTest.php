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
            'Nomor Meja/Hio',
            'Tanggal Booking',
            'Nama Customer',
            'Paket',
            'Kertas Doa 1',
            'Kertas Doa 2',
            'Kertas Hio',
        ], $sheet->rangeToArray('A1:G1')[0]);
        self::assertSame('Meja: A01 | Hio: 12', $sheet->getCell('A2')->getValue());
        self::assertSame('-', $sheet->getCell('F2')->getValue());
        self::assertNotContains('CD-TEST-123', $this->spreadsheetValues($spreadsheet));
        self::assertNotContains('+628123456789', $this->spreadsheetValues($spreadsheet));
        self::assertNotContains('budi@example.com', $this->spreadsheetValues($spreadsheet));
    }

    public function test_other_report_excel_exports_omit_booking_number_and_phone(): void
    {
        $reportService = Mockery::mock(AdminReportService::class);
        $reportService->shouldReceive('checkIn')->once()->andReturn([
            'rows' => [[
                'booking_number' => 'CD-PRIVATE-CHECKIN',
                'customer_name' => 'Budi',
                'customer_phone' => '+628111111111',
                'package_name' => 'Paket Doa',
                'attendee_count' => 2,
                'vegetarian_quantity' => 1,
                'non_vegetarian_quantity' => 1,
                'table_number' => 'A38',
                'incense_number' => '',
                'agent_name' => null,
            ]],
        ]);
        $reportService->shouldReceive('finance')->once()->andReturn([
            'summary' => [
                'total_bookings' => 1,
                'total_revenue' => 100000,
                'by_package' => [[
                    'package_name' => 'Paket Doa',
                    'booking_count' => 1,
                    'total_revenue' => 100000,
                ]],
            ],
            'rows' => [[
                'booking_number' => 'CD-PRIVATE-FINANCE',
                'booking_date' => '2026-08-16',
                'approval_date' => '2026-08-16',
                'customer_name' => 'Budi',
                'package_name' => 'Paket Doa',
                'amount' => 100000,
                'virtual_account_number' => '123',
                'referral_source' => 'TEMAN',
                'agent_name' => null,
            ]],
        ]);
        $reportService->shouldReceive('agent')->once()->andReturn([
            'groups' => [[
                'display_name' => 'Agent Satu',
                'booking_count' => 1,
                'attendee_count' => 2,
                'total_value' => 100000,
                'bookings' => [[
                    'booking_number' => 'CD-PRIVATE-AGENT',
                    'booking_date' => '2026-08-16',
                    'approval_date' => '2026-08-16',
                    'customer_name' => 'Budi',
                    'package_name' => 'Paket Doa',
                    'attendee_count' => 2,
                    'amount' => 100000,
                ]],
            ]],
        ]);

        $service = new AdminReportExportService($reportService);

        foreach ([
            'writeCheckInSheet' => ['CD-PRIVATE-CHECKIN', '+628111111111'],
            'writeFinanceSheet' => ['CD-PRIVATE-FINANCE'],
            'writeAgentSheet' => ['CD-PRIVATE-AGENT'],
        ] as $methodName => $privateValues) {
            $spreadsheet = new Spreadsheet;
            (new ReflectionMethod($service, $methodName))->invoke(
                $service,
                $spreadsheet->getActiveSheet(),
                1,
                [],
            );

            foreach ($privateValues as $privateValue) {
                self::assertNotContains($privateValue, $this->spreadsheetValues($spreadsheet));
            }
        }
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

    /** @return array<int, mixed> */
    private function spreadsheetValues(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getActiveSheet();

        return collect($sheet->rangeToArray('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow()))
            ->flatten()
            ->all();
    }
}
