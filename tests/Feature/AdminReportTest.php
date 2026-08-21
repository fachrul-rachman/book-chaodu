<?php

declare(strict_types=1);

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\IncenseSlot;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\AdminReportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->seed();
});

function createApprovedReportBooking(array $overrides = []): Booking
{
    $packageCode = $overrides['package_code_snapshot'] ?? PackageCode::Prayer->value;
    $package = Package::query()
        ->where('code', PackageCode::from($packageCode))
        ->firstOrFail();

    $booking = Booking::query()->create(array_merge([
        'booking_number' => 'CD-REPORT-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'idempotency_key' => 'report-key-'.str()->random(8),
        'package_id' => $package->id,
        'package_code_snapshot' => $packageCode,
        'package_name_snapshot' => $package->name,
        'package_price_snapshot' => '2000000',
        'customer_name' => 'Customer Report',
        'customer_phone' => '+6281234567890',
        'customer_email' => 'report@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'agent_name' => null,
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
        'created_at' => now()->subDay(),
        'updated_at' => now(),
    ], $overrides));

    $booking->meal()->create([
        'vegetarian_quantity' => 1,
        'non_vegetarian_quantity' => 1,
    ]);

    $booking->payment()->create([
        'expected_amount' => '2000000',
        'sender_name' => 'Budi',
        'transferred_amount' => $overrides['transferred_amount'] ?? '2000000',
        'transfer_date' => now()->toDateString(),
        'proof_path' => 'proof/test.jpg',
    ]);

    return $booking->fresh(['meal', 'payment']) ?? $booking;
}

it('shows approved bookings only in reports', function () {
    $package = Package::query()->where('code', PackageCode::Prayer)->firstOrFail();
    $approved = createApprovedReportBooking([
        'booking_number' => 'CD-APPROVED-1',
        'customer_name' => 'Yang Disetujui',
    ]);

    Booking::query()->create([
        'booking_number' => 'CD-PENDING-1',
        'idempotency_key' => 'report-pending-1',
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Prayer->value,
        'package_name_snapshot' => $package->name,
        'package_price_snapshot' => '2000000',
        'customer_name' => 'Yang Pending',
        'customer_phone' => '+6281234567891',
        'customer_email' => 'pending@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Pending,
    ]);

    Booking::query()->create([
        'booking_number' => 'CD-REJECT-1',
        'idempotency_key' => 'report-reject-1',
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Prayer->value,
        'package_name_snapshot' => $package->name,
        'package_price_snapshot' => '2000000',
        'customer_name' => 'Yang Ditolak',
        'customer_phone' => '+6281234567892',
        'customer_email' => 'reject@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'status' => BookingStatus::Rejected,
        'rejected_at' => now(),
    ]);

    TableSlot::query()->where('code', 'F18')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $approved->id,
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'checkin',
    ]));

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect(collect($props['checkin']['rows'])->pluck('booking_number')->all())->toContain(
        'CD-APPROVED-1',
        'INTERNAL-A18',
        'INTERNAL-A28',
        'INTERNAL-HIO-1',
        'INTERNAL-HIO-2',
    )
        ->not->toContain('INTERNAL-A38')
        ->and(collect($props['finance']['rows'])->pluck('booking_number')->all())->toContain(
            'CD-APPROVED-1',
            'INTERNAL-A18',
            'INTERNAL-A28',
            'INTERNAL-HIO-1',
            'INTERNAL-HIO-2',
        )
        ->not->toContain('INTERNAL-A38');
});

it('shows approved customer names and prayer paper links in customer report', function () {
    $approved = createApprovedReportBooking([
        'booking_number' => 'CD-CUSTOMER-1',
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '+6281234567890',
        'customer_email' => 'budi@example.com',
        'package_code_snapshot' => PackageCode::Combo->value,
    ]);

    $approved->names()->createMany([
        [
            'category' => BookingNameCategory::Deceased,
            'position' => 1,
            'indonesian_name' => 'ALM BUDI',
            'mandarin_name' => null,
        ],
        [
            'category' => BookingNameCategory::Deceased,
            'position' => 2,
            'indonesian_name' => null,
            'mandarin_name' => '林光月',
        ],
        [
            'category' => BookingNameCategory::Incense,
            'position' => 1,
            'indonesian_name' => 'KELUARGA BUDI',
            'mandarin_name' => null,
        ],
    ]);

    $papers = collect([
        [PrayerPaperType::A, 1, 'prayer-papers/customer-a1.png'],
        [PrayerPaperType::A, 2, 'prayer-papers/customer-a2.png'],
        [PrayerPaperType::B, 1, 'prayer-papers/customer-b1.png'],
    ])->map(fn (array $paper) => $approved->prayerPapers()->create([
        'type' => $paper[0],
        'sequence' => $paper[1],
        'file_path' => $paper[2],
        'version' => 1,
        'status' => PrayerPaperStatus::Ready,
        'generated_at' => now(),
    ]));

    $pending = createApprovedReportBooking([
        'booking_number' => 'CD-CUSTOMER-PENDING',
        'status' => BookingStatus::Pending,
        'approved_at' => null,
    ]);

    $pending->names()->create([
        'category' => BookingNameCategory::Deceased,
        'position' => 1,
        'indonesian_name' => 'TIDAK BOLEH MUNCUL',
        'mandarin_name' => null,
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'customer',
    ]));

    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $row = collect($props['customer']['rows'])->firstWhere('booking_number', 'CD-CUSTOMER-1');

    expect(collect($props['customer']['rows'])->pluck('booking_number')->all())
        ->toContain('CD-CUSTOMER-1')
        ->not->toContain('CD-CUSTOMER-PENDING')
        ->and($row['customer_name'])->toBe('Budi Santoso')
        ->and($row['customer_phone'])->toBe('+6281234567890')
        ->and($row['customer_email'])->toBe('budi@example.com')
        ->and($row['prayer_paper_1']['name'])->toBe('ALM BUDI')
        ->and($row['prayer_paper_1']['image_url'])->toBe(route('admin.prayer-papers.show', $papers[0]))
        ->and($row['prayer_paper_2']['name'])->toBe('林光月')
        ->and($row['prayer_paper_2']['image_url'])->toBe(route('admin.prayer-papers.show', $papers[1]))
        ->and($row['incense_paper']['name'])->toBe('KELUARGA BUDI')
        ->and($row['incense_paper']['image_url'])->toBe(route('admin.prayer-papers.show', $papers[2]));
});

it('summarizes approved customer bookings by package using the active filters', function () {
    $prayerOne = createApprovedReportBooking([
        'booking_number' => 'CD-SUMMARY-PRAYER-1',
        'package_code_snapshot' => PackageCode::Prayer->value,
    ]);
    $prayerOne->forceFill(['created_at' => '2026-07-10 08:00:00'])->save();

    $prayerTwo = createApprovedReportBooking([
        'booking_number' => 'CD-SUMMARY-PRAYER-2',
        'package_code_snapshot' => PackageCode::Prayer->value,
    ]);
    $prayerTwo->forceFill(['created_at' => '2026-07-11 08:00:00'])->save();

    $combo = createApprovedReportBooking([
        'booking_number' => 'CD-SUMMARY-COMBO-1',
        'package_code_snapshot' => PackageCode::Combo->value,
    ]);
    $combo->forceFill(['created_at' => '2026-07-12 08:00:00'])->save();

    $outsideRange = createApprovedReportBooking([
        'booking_number' => 'CD-SUMMARY-OUTSIDE-RANGE',
        'package_code_snapshot' => PackageCode::Incense->value,
    ]);
    $outsideRange->forceFill(['created_at' => '2026-06-30 08:00:00'])->save();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'customer',
        'date_field' => 'booking',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
    ]));

    $response->assertOk();

    $customer = $response->viewData('page')['props']['customer'];
    $counts = collect($customer['summary']['by_package'])
        ->mapWithKeys(fn (array $item): array => [$item['package_code'] => $item['booking_count']])
        ->all();
    $prayerRow = collect($customer['rows'])->firstWhere('booking_number', 'CD-SUMMARY-PRAYER-1');

    expect(collect($customer['rows'])->pluck('booking_number')->all())
        ->not->toContain('CD-SUMMARY-OUTSIDE-RANGE')
        ->and($customer['summary']['total_bookings'])->toBe(3)
        ->and($counts)->toBe([
            PackageCode::Prayer->value => 2,
            PackageCode::Incense->value => 0,
            PackageCode::Combo->value => 1,
        ])
        ->and($prayerRow['booking_date'])->toBe('2026-07-10')
        ->and($prayerRow['status'])->toBe(BookingStatus::Approved->value);
});

it('paginates every report tab with 25 items per page', function () {
    foreach (range(1, 26) as $index) {
        createApprovedReportBooking([
            'booking_number' => 'CD-PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'customer_name' => 'Customer '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'referral_source' => 'AGENT',
            'agent_name' => 'Agent '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $admin = User::factory()->admin()->create();

    $expected = [
        'checkin' => ['key' => 'rows', 'total' => 30, 'page_two_count' => 5],
        'finance' => ['key' => 'rows', 'total' => 30, 'page_two_count' => 5],
        'agent' => ['key' => 'groups', 'total' => 26, 'page_two_count' => 1],
        'customer' => ['key' => 'rows', 'total' => 26, 'page_two_count' => 1],
        'after_event' => ['key' => 'rows', 'total' => 26, 'page_two_count' => 1],
    ];

    foreach ($expected as $tab => $expectation) {
        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'tab' => $tab,
            'page' => 2,
        ]));

        $response->assertOk();

        $report = $response->viewData('page')['props'][$tab];

        $this->assertCount(
            $expectation['page_two_count'],
            $report[$expectation['key']],
            "Jumlah baris halaman kedua tab {$tab} tidak sesuai.",
        );

        expect($report['pagination'])->toMatchArray([
            'current_page' => 2,
            'per_page' => 25,
            'total' => $expectation['total'],
        ]);
    }

    $reportService = app(AdminReportService::class);
    $financeExportData = $reportService->finance($reportService->filters([
        'tab' => 'finance',
        'page' => 2,
    ]));

    expect($financeExportData['rows'])->toHaveCount(30)
        ->and($financeExportData['summary']['total_bookings'])->toBe(30);
});

it('uses stored transferred amount in finance report', function () {
    $package = Package::query()->where('code', PackageCode::Prayer)->firstOrFail();
    $package->forceFill([
        'price' => '9999999',
        'is_active' => true,
    ])->save();

    createApprovedReportBooking([
        'booking_number' => 'CD-FINANCE-1',
        'package_price_snapshot' => '2000000',
        'transferred_amount' => '1234567',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'finance',
    ]));

    $props = $response->viewData('page')['props'];
    $realBookingRow = collect($props['finance']['rows'])
        ->firstWhere('booking_number', 'CD-FINANCE-1');

    expect($props['finance']['summary']['total_revenue'])->toBe(1234567.0)
        ->and($realBookingRow['amount'])->toBe(1234567.0)
        ->and($realBookingRow['virtual_account_number'])->toBeNull()
        ->and(collect($props['finance']['rows'])->pluck('booking_number')->all())->toContain('INTERNAL-A18');
});

it('groups agent names with basic normalization only', function () {
    createApprovedReportBooking([
        'booking_number' => 'CD-AGENT-1',
        'referral_source' => 'AGENT',
        'agent_name' => ' Budi  Sudarno ',
    ]);

    createApprovedReportBooking([
        'booking_number' => 'CD-AGENT-2',
        'referral_source' => 'AGENT',
        'agent_name' => 'budi sudarno',
    ]);

    createApprovedReportBooking([
        'booking_number' => 'CD-AGENT-3',
        'referral_source' => 'AGENT',
        'agent_name' => 'Budi S.',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'agent',
    ]));

    $groups = $response->viewData('page')['props']['agent']['groups'];

    expect(count($groups))->toBe(2)
        ->and($groups[0]['display_name'])->toBe('Budi Sudarno')
        ->and($groups[0]['booking_count'])->toBe(2)
        ->and($groups[1]['display_name'])->toBe('Budi S.')
        ->and($groups[1]['booking_count'])->toBe(1);
});

it('can export printable check-in pdf', function () {
    createApprovedReportBooking([
        'booking_number' => 'CD-PRINT-1',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.export', [
        'tab' => 'checkin',
        'format' => 'pdf',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('shows the admin-only after event report with table and incense numbers separated', function () {
    $approved = createApprovedReportBooking([
        'booking_number' => 'CD-AFTER-EVENT-1',
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '+6281234567890',
        'referral_source' => 'AGENT',
        'agent_name' => 'Agent Lestari',
        'package_code_snapshot' => PackageCode::Combo->value,
        'approved_at' => '2026-08-16 14:30:00',
    ]);
    createApprovedReportBooking([
        'booking_number' => 'CD-AFTER-EVENT-PENDING',
        'status' => BookingStatus::Pending,
        'approved_at' => null,
    ]);

    TableSlot::query()->where('code', 'F18')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $approved->id,
    ]);
    IncenseSlot::query()->where('number', 7)->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $approved->id,
    ]);

    $admin = User::factory()->admin()->create();
    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'after_event',
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];
    expect($props)->toHaveKey('after_event');

    $row = collect($props['after_event']['rows'])->firstWhere('booking_number', 'CD-AFTER-EVENT-1');

    expect(array_column($props['tabs'], 'value'))->toContain('after_event')
        ->and(collect($props['after_event']['rows'])->pluck('booking_number')->all())
        ->toContain('CD-AFTER-EVENT-1')
        ->not->toContain('CD-AFTER-EVENT-PENDING')
        ->and($row)->toMatchArray([
            'booking_number' => 'CD-AFTER-EVENT-1',
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '+6281234567890',
            'agent_name' => 'Agent Lestari',
            'approval_date' => '2026-08-16',
            'table_number' => 'F18',
            'incense_number' => '7',
        ])
        ->and($row['package_name'])->not->toBeEmpty()
        ->and($props['export_urls']['after_event']['xlsx'])->toBe(route('admin.reports.export', [
            'tab' => 'after_event',
            'format' => 'xlsx',
        ]));
});

it('exports the after event report to Excel and PDF for admins only', function () {
    $booking = createApprovedReportBooking([
        'booking_number' => 'CD-AFTER-EXPORT',
        'customer_name' => 'Customer Export',
        'customer_phone' => '+628111222333',
        'referral_source' => 'WEBSITE',
        'agent_name' => null,
        'approved_at' => '2026-08-16 09:00:00',
    ]);
    TableSlot::query()->where('code', 'F18')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $booking->id,
    ]);

    $admin = User::factory()->admin()->create();
    $xlsxResponse = $this->actingAs($admin)->get(route('admin.reports.export', [
        'tab' => 'after_event',
        'format' => 'xlsx',
    ]));

    $xlsxResponse->assertOk()->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    $temporaryFile = tempnam(sys_get_temp_dir(), 'after-event-report-');
    file_put_contents($temporaryFile, $xlsxResponse->streamedContent());
    $sheet = IOFactory::load($temporaryFile)->getActiveSheet();

    expect($sheet->rangeToArray('A7:H7')[0])->toBe([
        'Kode booking',
        'Nama customer',
        'Nomor telepon',
        'Nama agent',
        'Tanggal disetujui',
        'Paket',
        'Nomor meja',
        'Nomor hio',
    ])->and($sheet->rangeToArray('A8:H8')[0])->toBe([
        'CD-AFTER-EXPORT',
        'Customer Export',
        '+628111222333',
        '-',
        '2026-08-16',
        $booking->package_name_snapshot,
        'F18',
        '-',
    ]);
    unlink($temporaryFile);

    $this->actingAs($admin)->get(route('admin.reports.export', [
        'tab' => 'after_event',
        'format' => 'pdf',
    ]))->assertOk()->assertHeader('content-type', 'application/pdf');

    $checker = User::factory()->checker()->create();
    $this->actingAs($checker)->get(route('admin.reports.index', ['tab' => 'after_event']))
        ->assertForbidden();
    $this->actingAs($checker)->get(route('admin.reports.export', [
        'tab' => 'after_event',
        'format' => 'xlsx',
    ]))->assertForbidden();
});
