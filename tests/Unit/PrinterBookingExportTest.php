<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BookingNameCategory;
use App\Http\Controllers\Printer\DashboardController;
use App\Models\Booking;
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
        ]));
        $booking->setRelation('tableSlots', new Collection([
            (new TableSlot)->forceFill(['code' => 'A01', 'allocation_order' => 1]),
        ]));
        $booking->setRelation('incenseSlots', new Collection([
            (new IncenseSlot)->forceFill(['number' => 12, 'allocation_order' => 1]),
        ]));

        $method = new ReflectionMethod(DashboardController::class, 'exportSpreadsheet');
        $spreadsheet = $method->invoke(
            new DashboardController,
            new Collection([$booking]),
        );
        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame([
            'Nomor Booking',
            'Nama Customer',
            'Nama Alm 1',
            'Nama Alm 2',
            'Nomor Meja/Hio',
            'Meja',
            'Hio',
            'Nomor Telepon',
        ], $sheet->rangeToArray('A1:H1')[0]);
        self::assertSame([
            'CD-TEST-123',
            'Budi',
            'Alm Satu',
            '亡者二',
            'Meja: A01 | Hio: 12',
            'A01',
            '12',
            '+628123456789',
        ], $sheet->rangeToArray('A2:H2')[0]);
        self::assertSame(DataType::TYPE_STRING, $sheet->getCell('H2')->getDataType());
    }
}
