<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\SlotStatus;
use App\Enums\VirtualAccountStatus;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PrayerPaper;
use App\Models\TableSlot;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase3.submit_rate_limit_max_attempts', 6);
    config()->set('phase3.submit_rate_limit_decay_seconds', 60);
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    Storage::fake('booking-private');
    Storage::fake('prayer-paper-files');

    $this->seed();
    seedAdminVirtualAccounts();
});

function seedAdminVirtualAccounts(): void
{
    foreach ([
        [PackageCode::Prayer, ['900001']],
        [PackageCode::Incense, ['910001']],
        [PackageCode::Combo, ['920001']],
    ] as [$packageCode, $numbers]) {
        foreach ($numbers as $number) {
            VirtualAccount::query()->create([
                'package_code' => $packageCode,
                'account_number' => $number,
            ]);
        }
    }
}

function activateAdminBookingPackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function adminBookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'admin-booking-key-1',
        'customer_name' => 'Budi Santoso',
        'customer_phone_local' => '81234567890',
        'customer_email' => 'customer@gmail.com',
        'attendee_count' => '2',
        'package_code' => PackageCode::Prayer->value,
        'deceased_names' => [
            [
                'indonesian_name' => 'Tan Ah Kok',
                'mandarin_name' => '林珖月',
                'source_image' => null,
            ],
            [
                'indonesian_name' => '',
                'mandarin_name' => '',
                'source_image' => null,
            ],
        ],
        'incense_name' => [
            'indonesian_name' => '',
            'mandarin_name' => '',
            'source_image' => null,
        ],
        'vegetarian_quantity' => '1',
        'non_vegetarian_quantity' => '1',
        'sender_name' => 'Budi',
        'transferred_amount' => '2000000',
        'transfer_date' => now()->toDateString(),
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
        'referral_source' => 'TEMAN',
        'agent_name' => '',
        'confirmation_checked' => '1',
        'captcha_token' => '',
    ], $overrides);
}

function createPendingBooking(array $overrides = []): Booking
{
    $payload = adminBookingPayload($overrides);
    activateAdminBookingPackage(PackageCode::from($payload['package_code']));

    test()->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    return Booking::query()->latest('id')->firstOrFail()->fresh(['names', 'meal', 'payment', 'prayerPapers']) ?? Booking::query()->latest('id')->firstOrFail();
}

it('shows booking lists for all statuses to admin users', function () {
    $pending = createPendingBooking();
    $approved = createPendingBooking([
        'idempotency_key' => 'admin-booking-key-approved',
    ]);
    $approved->forceFill([
        'status' => BookingStatus::Approved,
    ])->save();

    $rejected = createPendingBooking([
        'idempotency_key' => 'admin-booking-key-rejected',
    ]);
    $rejected->forceFill([
        'status' => BookingStatus::Rejected,
        'rejection_reason' => 'Data belum sesuai.',
    ])->save();

    $admin = User::factory()->admin()->create();

    $allResponse = $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk();

    $allBookings = collect($allResponse->viewData('page')['props']['bookings'] ?? []);

    expect($allBookings->pluck('booking_number')->all())
        ->toContain($pending->booking_number, $approved->booking_number, $rejected->booking_number)
        ->and($allBookings->pluck('status')->all())
        ->toContain(
            BookingStatus::Pending->value,
            BookingStatus::Approved->value,
            BookingStatus::Rejected->value,
        );

    $pendingResponse = $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['status' => BookingStatus::Pending->value]))
        ->assertOk();

    $pendingBookings = collect($pendingResponse->viewData('page')['props']['bookings'] ?? []);

    expect($pendingBookings)->toHaveCount(1)
        ->and($pendingBookings->pluck('booking_number')->all())->toBe([$pending->booking_number])
        ->and($pendingBookings->pluck('status')->all())->toBe([BookingStatus::Pending->value]);
});

it('shows booking detail including reserved slots and proof', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertSee($booking->customer_name)
        ->assertSee('F18')
        ->assertSee('bukti-transfer.jpg');
});

it('allows admin to update allowed booking fields and regenerate final file after name revision', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();
    $paper = PrayerPaper::query()->where('booking_id', $booking->id)->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'customer_name' => 'Budi Revisi',
            'customer_phone' => '+6282233344455',
            'customer_email' => 'revisi@gmail.com',
            'attendee_count' => 3,
            'sender_name' => 'Pengirim Baru',
            'virtual_account_number' => $booking->payment?->virtual_account_number,
            'transferred_amount' => '2100000',
            'transfer_date' => now()->toDateString(),
            'referral_source' => 'KELUARGA',
            'agent_name' => null,
            'vegetarian_quantity' => 1,
            'non_vegetarian_quantity' => 1,
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Nama Revisi',
                    'mandarin_name' => '陳秀蓮',
                ],
            ],
            'incense_name' => null,
        ])
        ->assertRedirect();

    $booking->refresh();
    $paper->refresh();

    expect($booking->customer_name)->toBe('Budi Revisi')
        ->and($booking->customer_phone)->toBe('+6282233344455')
        ->and($booking->names()->where('position', 1)->value('mandarin_name'))->toBe('陳秀蓮')
        ->and($paper->status)->toBe(PrayerPaperStatus::Ready)
        ->and($paper->version)->toBe(2);
});

it('does not allow admin to change package on an existing booking', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.bookings.show', $booking))
        ->put(route('admin.bookings.update', $booking), [
            'package_code' => PackageCode::Combo->value,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'attendee_count' => $booking->attendee_count,
            'sender_name' => $booking->payment?->sender_name,
            'virtual_account_number' => $booking->payment?->virtual_account_number,
            'transferred_amount' => '2000000',
            'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
            'referral_source' => $booking->referral_source,
            'agent_name' => $booking->agent_name,
            'vegetarian_quantity' => $booking->meal?->vegetarian_quantity,
            'non_vegetarian_quantity' => $booking->meal?->non_vegetarian_quantity,
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Tan Ah Kok',
                    'mandarin_name' => null,
                ],
            ],
        ])
        ->assertRedirect(route('admin.bookings.show', $booking))
        ->assertSessionHasErrors('package_code');
});

it('replaces a reserved table slot safely', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'attendee_count' => $booking->attendee_count,
            'sender_name' => $booking->payment?->sender_name,
            'virtual_account_number' => $booking->payment?->virtual_account_number,
            'transferred_amount' => '2000000',
            'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
            'referral_source' => $booking->referral_source,
            'agent_name' => $booking->agent_name,
            'vegetarian_quantity' => $booking->meal?->vegetarian_quantity,
            'non_vegetarian_quantity' => $booking->meal?->non_vegetarian_quantity,
            'replace_table_slot_id' => TableSlot::query()->where('code', 'B18')->value('id'),
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Tan Ah Kok',
                    'mandarin_name' => '林珖月',
                ],
            ],
        ])
        ->assertRedirect();

    expect(TableSlot::query()->where('code', 'F18')->value('status'))->toBe(SlotStatus::Available)
        ->and(TableSlot::query()->where('code', 'F18')->value('booking_id'))->toBeNull()
        ->and(TableSlot::query()->where('code', 'B18')->value('status'))->toBe(SlotStatus::Reserved)
        ->and(TableSlot::query()->where('code', 'B18')->value('booking_id'))->toBe($booking->id);
});

it('approves a booking and assigns reserved slots', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.approve', $booking))
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Approved)
        ->and($booking->approved_by)->toBe($admin->id)
        ->and(TableSlot::query()->where('booking_id', $booking->id)->value('status'))->toBe(SlotStatus::Assigned);
});

it('keeps approval safe from double requests', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();

    expect(TableSlot::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and(Booking::query()->findOrFail($booking->id)->status)->toBe(BookingStatus::Approved);
});

it('rejects a booking with reason and releases reserved slots', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.reject', $booking), [
            'reason' => 'Bukti transfer belum sesuai.',
        ])
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Rejected)
        ->and($booking->rejection_reason)->toBe('Bukti transfer belum sesuai.')
        ->and(TableSlot::query()->where('code', 'F18')->value('status'))->toBe(SlotStatus::Available)
        ->and(TableSlot::query()->where('code', 'F18')->value('booking_id'))->toBeNull();
});

it('keeps rejection safe from double requests', function () {
    $booking = createPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.bookings.reject', $booking), [
        'reason' => 'Data belum sesuai.',
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('admin.bookings.reject', $booking), [
        'reason' => 'Data belum sesuai.',
    ])->assertRedirect();

    expect(Booking::query()->findOrFail($booking->id)->status)->toBe(BookingStatus::Rejected);
});

it('allows zero meal quantities when admin updates a booking', function () {
    $booking = createPendingBooking([
        'idempotency_key' => 'admin-booking-zero-meal',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'attendee_count' => $booking->attendee_count,
            'sender_name' => $booking->payment?->sender_name,
            'virtual_account_number' => $booking->payment?->virtual_account_number,
            'transferred_amount' => '2000000',
            'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
            'referral_source' => $booking->referral_source,
            'agent_name' => $booking->agent_name,
            'vegetarian_quantity' => 0,
            'non_vegetarian_quantity' => 0,
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Tan Ah Kok',
                    'mandarin_name' => 'æž—ç–æœˆ',
                ],
            ],
            'incense_name' => null,
        ])
        ->assertRedirect();

    expect($booking->fresh()?->meal?->vegetarian_quantity)->toBe(0)
        ->and($booking->fresh()?->meal?->non_vegetarian_quantity)->toBe(0);
});

it('rejects meal quantity above package quota when admin updates a booking', function () {
    $booking = createPendingBooking([
        'idempotency_key' => 'admin-booking-over-meal',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'attendee_count' => $booking->attendee_count,
            'sender_name' => $booking->payment?->sender_name,
            'virtual_account_number' => $booking->payment?->virtual_account_number,
            'transferred_amount' => '2000000',
            'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
            'referral_source' => $booking->referral_source,
            'agent_name' => $booking->agent_name,
            'vegetarian_quantity' => 111,
            'non_vegetarian_quantity' => 0,
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Tan Ah Kok',
                    'mandarin_name' => '林珖月',
                ],
            ],
            'incense_name' => null,
        ])
        ->assertSessionHasErrors([
            'vegetarian_quantity',
            'non_vegetarian_quantity',
        ]);
});

it('allows admin to change virtual account on pending booking and releases the old one', function () {
    AppSetting::putMany([
        'virtual_account_mode' => 'POOL',
    ]);

    VirtualAccount::query()->create([
        'package_code' => PackageCode::Prayer,
        'account_number' => '900002',
        'status' => VirtualAccountStatus::Available,
    ]);

    $booking = createPendingBooking([
        'idempotency_key' => 'admin-booking-change-va',
        'use_manual_virtual_account' => '1',
        'manual_virtual_account_number' => '900001',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'attendee_count' => $booking->attendee_count,
            'sender_name' => $booking->payment?->sender_name,
            'virtual_account_number' => '900002',
            'transferred_amount' => '2000000',
            'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
            'referral_source' => $booking->referral_source,
            'agent_name' => $booking->agent_name,
            'vegetarian_quantity' => $booking->meal?->vegetarian_quantity,
            'non_vegetarian_quantity' => $booking->meal?->non_vegetarian_quantity,
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Tan Ah Kok',
                    'mandarin_name' => 'æž—ç–æœˆ',
                ],
            ],
            'incense_name' => null,
        ])
        ->assertRedirect();

    expect($booking->fresh()?->payment?->virtual_account_number)->toBe('900002')
        ->and(VirtualAccount::query()->where('account_number', '900001')->value('status'))->toBe(VirtualAccountStatus::Available)
        ->and(VirtualAccount::query()->where('account_number', '900001')->value('booking_id'))->toBeNull()
        ->and(VirtualAccount::query()->where('account_number', '900002')->value('status'))->toBe(VirtualAccountStatus::Assigned)
        ->and(VirtualAccount::query()->where('account_number', '900002')->value('booking_id'))->toBe($booking->id);
});

it('rejects admin virtual account change when the number belongs to another package', function () {
    $booking = createPendingBooking([
        'idempotency_key' => 'admin-booking-invalid-va',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.bookings.show', $booking))
        ->put(route('admin.bookings.update', $booking), [
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'attendee_count' => $booking->attendee_count,
            'sender_name' => $booking->payment?->sender_name,
            'virtual_account_number' => '910001',
            'transferred_amount' => '2000000',
            'transfer_date' => optional($booking->payment?->transfer_date)->toDateString(),
            'referral_source' => $booking->referral_source,
            'agent_name' => $booking->agent_name,
            'vegetarian_quantity' => $booking->meal?->vegetarian_quantity,
            'non_vegetarian_quantity' => $booking->meal?->non_vegetarian_quantity,
            'deceased_names' => [
                [
                    'position' => 1,
                    'indonesian_name' => 'Tan Ah Kok',
                    'mandarin_name' => 'æž—ç–æœˆ',
                ],
            ],
            'incense_name' => null,
        ])
        ->assertRedirect(route('admin.bookings.show', $booking))
        ->assertSessionHasErrors('virtual_account_number');
});
