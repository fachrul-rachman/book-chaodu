<?php

declare(strict_types=1);

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\User;
use App\Services\AdminReportService;

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
        'INTERNAL-A38',
        'INTERNAL-HIO-1',
        'INTERNAL-HIO-2',
    )
        ->and(collect($props['finance']['rows'])->pluck('booking_number')->all())->toContain(
            'CD-APPROVED-1',
            'INTERNAL-A18',
            'INTERNAL-A28',
            'INTERNAL-A38',
            'INTERNAL-HIO-1',
            'INTERNAL-HIO-2',
        );
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
        'checkin' => ['key' => 'rows', 'total' => 31, 'page_two_count' => 6],
        'finance' => ['key' => 'rows', 'total' => 31, 'page_two_count' => 6],
        'agent' => ['key' => 'groups', 'total' => 26, 'page_two_count' => 1],
        'customer' => ['key' => 'rows', 'total' => 26, 'page_two_count' => 1],
    ];

    foreach ($expected as $tab => $expectation) {
        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'tab' => $tab,
            'page' => 2,
        ]));

        $response->assertOk();

        $report = $response->viewData('page')['props'][$tab];

        expect($report[$expectation['key']])->toHaveCount($expectation['page_two_count'])
            ->and($report['pagination'])->toMatchArray([
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

    expect($financeExportData['rows'])->toHaveCount(31)
        ->and($financeExportData['summary']['total_bookings'])->toBe(31);
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
