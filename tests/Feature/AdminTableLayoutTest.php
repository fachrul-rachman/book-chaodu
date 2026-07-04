<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\User;

it('shows table layout with slot colors data and booking information', function () {
    $this->seed();
    $package = Package::query()->firstOrFail();

    $pendingBooking = Booking::query()->create([
        'booking_number' => 'CD-PENDING1',
        'idempotency_key' => 'layout-pending-1',
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Prayer->value,
        'package_name_snapshot' => 'Sembahyang',
        'package_price_snapshot' => '2000000',
        'customer_name' => 'Budi',
        'customer_phone' => '+6281234567890',
        'customer_email' => 'budi@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'agent_name' => null,
        'status' => BookingStatus::Pending,
    ]);

    $approvedBooking = Booking::query()->create([
        'booking_number' => 'CD-APPROVED1',
        'idempotency_key' => 'layout-approved-1',
        'package_id' => $package->id,
        'package_code_snapshot' => PackageCode::Prayer->value,
        'package_name_snapshot' => 'Sembahyang',
        'package_price_snapshot' => '2000000',
        'customer_name' => 'Sari',
        'customer_phone' => '+6281234567891',
        'customer_email' => 'sari@example.com',
        'attendee_count' => 2,
        'referral_source' => 'TEMAN',
        'agent_name' => null,
        'status' => BookingStatus::Approved,
    ]);

    TableSlot::query()->where('code', 'A58')->update([
        'status' => SlotStatus::Reserved,
        'booking_id' => $pendingBooking->id,
    ]);

    TableSlot::query()->where('code', 'B18')->update([
        'status' => SlotStatus::Assigned,
        'booking_id' => $approvedBooking->id,
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.table-layout'));

    $response->assertOk();

    $rows = $response->viewData('page')['props']['rows'];
    $rowA = collect($rows)->firstWhere('row_code', 'A');
    $rowB = collect($rows)->firstWhere('row_code', 'B');
    $slotA58 = collect($rowA['slots'])->firstWhere('code', 'A58');
    $slotB18 = collect($rowB['slots'])->firstWhere('code', 'B18');

    expect(collect($rows)->pluck('row_code')->all())->toBe([
        'J',
        'H',
        'G',
        'F',
        'A',
        'B',
        'D',
        'E',
    ])
        ->and($slotA58['status'])->toBe(SlotStatus::Reserved->value)
        ->and($slotA58['booking_number'])->toBe('CD-PENDING1')
        ->and($slotB18['status'])->toBe(SlotStatus::Assigned->value)
        ->and($slotB18['booking_number'])->toBe('CD-APPROVED1');
});
