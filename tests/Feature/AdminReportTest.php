<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\User;

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

    TableSlot::query()->where('code', 'A18')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $approved->id,
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', [
        'tab' => 'checkin',
    ]));

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect(collect($props['checkin']['rows'])->pluck('booking_number')->all())->toBe([
        'CD-APPROVED-1',
    ])
        ->and(collect($props['finance']['rows'])->pluck('booking_number')->all())->toBe([
            'CD-APPROVED-1',
        ]);
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

    expect($props['finance']['summary']['total_revenue'])->toBe(1234567.0)
        ->and($props['finance']['rows'][0]['amount'])->toBe(1234567.0)
        ->and($props['finance']['rows'][0]['virtual_account_number'])->toBeNull();
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
