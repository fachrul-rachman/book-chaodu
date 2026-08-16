<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingNameCategory;
use App\Http\Controllers\Printer\DashboardController;
use App\Models\Booking;
use App\Models\BookingMeal;
use App\Models\BookingName;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use ReflectionMethod;
use Tests\TestCase;

class PrinterBookingExportTest extends TestCase
{
    public function test_printer_excel_contains_the_requested_columns_and_values(): void
    {
        $booking = new Booking;
        $booking->forceFill([
            'booking_number' => 'CD-TEST-123',
            'customer_name' => 'Budi',
            'customer_phone' => '+628123456789',
        ]);
        $booking->setRelation('names', new Collection([
            (new BookingName)->forceFill([
                'category' => BookingNameCategory::Deceased,
                'position' => 2,
                'indonesian_name' => 'Alm Dua',
                'mandarin_name' => '亡者二',
            ]),
            (new BookingName)->forceFill([
                'category' => BookingNameCategory::Deceased,
                'position' => 1,
                'indonesian_name' => 'Alm Satu',
                'mandarin_name' => null,
            ]),
            (new BookingName)->forceFill([
                'category' => BookingNameCategory::Incense,
                'position' => 1,
                'indonesian_name' => 'Nama Hio',
                'mandarin_name' => null,
            ]),
        ]));
        $booking->setRelation('tableSlots', new Collection([
            (new TableSlot)->forceFill(['code' => 'A01', 'allocation_order' => 1]),
        ]));
        $booking->setRelation('incenseSlots', new Collection([
            (new IncenseSlot)->forceFill(['number' => 12, 'allocation_order' => 1]),
        ]));
        $booking->setRelation('meal', (new BookingMeal)->forceFill([
            'vegetarian_quantity' => 2,
            'non_vegetarian_quantity' => 3,
        ]));

        $method = new ReflectionMethod(DashboardController::class, 'exportSpreadsheet');
        $spreadsheet = $method->invoke(
            new DashboardController,
            new Collection([$booking]),
        );
        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame([
            'Nama Customer',
            'Nama Alm 1',
            'Nama Alm 2',
            'Nama Hio',
            'Meja',
            'Hio',
            'Vegetarian',
            'Non Vegetarian',
        ], $sheet->rangeToArray('A1:H1')[0]);
        self::assertSame([
            'Budi',
            'Alm Satu',
            '亡者二',
            'Nama Hio',
            'A01',
            '12',
            '2',
            '3',
        ], $sheet->rangeToArray('A2:H2')[0]);
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('G2')->getDataType());
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('H2')->getDataType());
    }

    public function test_printer_excel_sorts_tables_by_requested_row_then_hio_only_by_number(): void
    {
        $bookings = new Collection([
            $this->bookingWithSlots('CD-HIO-12', incenseNumber: 12),
            $this->bookingWithSlots('CD-J-01', tableCode: 'J01'),
            $this->bookingWithSlots('CD-A-10', tableCode: 'A10'),
            $this->bookingWithSlots('CD-NONE'),
            $this->bookingWithSlots('CD-E-01', tableCode: 'E01'),
            $this->bookingWithSlots('CD-H-01', tableCode: 'H01'),
            $this->bookingWithSlots('CD-B-01', tableCode: 'B01'),
            $this->bookingWithSlots('CD-A-02', tableCode: 'A02'),
            $this->bookingWithSlots('CD-HIO-01', incenseNumber: 1),
            $this->bookingWithSlots('CD-D-01', tableCode: 'D01'),
            $this->bookingWithSlots('CD-F-01', tableCode: 'F01'),
            $this->bookingWithSlots('CD-G-01', tableCode: 'G01'),
        ]);

        $method = new ReflectionMethod(DashboardController::class, 'exportSpreadsheet');
        $spreadsheet = $method->invoke(new DashboardController, $bookings);

        self::assertSame([
            'Customer CD-A-02',
            'Customer CD-A-10',
            'Customer CD-B-01',
            'Customer CD-D-01',
            'Customer CD-F-01',
            'Customer CD-G-01',
            'Customer CD-H-01',
            'Customer CD-E-01',
            'Customer CD-J-01',
            'Customer CD-HIO-01',
            'Customer CD-HIO-12',
            'Customer CD-NONE',
        ], array_column($spreadsheet->getActiveSheet()->rangeToArray('A2:A13'), 0));
    }

    private function bookingWithSlots(
        string $bookingNumber,
        ?string $tableCode = null,
        ?int $incenseNumber = null,
    ): Booking {
        $booking = (new Booking)->forceFill([
            'booking_number' => $bookingNumber,
            'customer_name' => 'Customer '.$bookingNumber,
        ]);
        $booking->setRelation('names', new Collection);
        $booking->setRelation('tableSlots', new Collection(
            $tableCode === null
                ? []
                : [(new TableSlot)->forceFill([
                    'code' => $tableCode,
                    'row_code' => substr($tableCode, 0, 1),
                    'number' => (int) substr($tableCode, 1),
                    'allocation_order' => 1,
                ])],
        ));
        $booking->setRelation('incenseSlots', new Collection(
            $incenseNumber === null
                ? []
                : [(new IncenseSlot)->forceFill([
                    'number' => $incenseNumber,
                    'allocation_order' => 1,
                ])],
        ));
        $booking->setRelation('meal', null);

        return $booking;
    }
}
