<?php

declare(strict_types=1);

use App\Enums\ApprovalIntegrationStatus;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Models\ApprovalIntegration;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Package;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    Storage::fake('booking-private');
    Storage::fake('prayer-paper-files');

    $this->seed();
    seedCheckerVirtualAccounts();
});

function seedCheckerVirtualAccounts(): void
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

function activateCheckerPackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function checkerBookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'checker-module8-key-1',
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

/**
 * @return array{booking:Booking, token:string}
 */
function createCheckerBooking(BookingStatus $status, array $overrides = []): array
{
    $payload = checkerBookingPayload($overrides);
    activateCheckerPackage(PackageCode::from($payload['package_code']));

    test()->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    $booking = Booking::query()->latest('id')->firstOrFail();

    $booking->forceFill([
        'status' => $status,
        'approved_at' => $status === BookingStatus::Approved ? now() : null,
        'rejected_at' => $status === BookingStatus::Rejected ? now() : null,
        'rejection_reason' => $status === BookingStatus::Rejected ? 'Ditolak.' : null,
    ])->save();

    if ($status === BookingStatus::Approved) {
        $booking->tableSlots()->update(['status' => 'ASSIGNED']);
        $booking->incenseSlots()->update(['status' => 'ASSIGNED']);
    }

    $token = strtolower(bin2hex(random_bytes(32)));

    ApprovalIntegration::query()->updateOrCreate(
        ['booking_id' => $booking->id],
        [
            'qr_status' => ApprovalIntegrationStatus::Succeeded,
            'qr_token_hash' => hash('sha256', $token),
            'qr_token_encrypted' => Crypt::encryptString($token),
            'drive_status' => ApprovalIntegrationStatus::Succeeded,
            'notion_status' => ApprovalIntegrationStatus::Succeeded,
            'approval_email_status' => ApprovalIntegrationStatus::Succeeded,
        ],
    );

    return [
        'booking' => $booking->fresh(['meal', 'tableSlots', 'incenseSlots']) ?? $booking,
        'token' => $token,
    ];
}

it('shows approved booking data from manual booking number lookup', function () {
    $data = createCheckerBooking(BookingStatus::Approved);
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('checker.dashboard', ['kode' => $data['booking']->booking_number]))
        ->assertOk()
        ->assertSee($data['booking']->booking_number)
        ->assertSee($data['booking']->customer_name)
        ->assertSee('SIAP_MASUK')
        ->assertDontSee('bukti-transfer');
});

it('finds approved booking from qr token lookup', function () {
    $data = createCheckerBooking(BookingStatus::Approved, [
        'idempotency_key' => 'checker-module8-key-2',
    ]);
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('checker.dashboard', ['kode' => $data['token']]))
        ->assertOk()
        ->assertSee($data['booking']->booking_number)
        ->assertSee($data['booking']->customer_name);
});

it('rejects lookup for pending or rejected booking', function () {
    $pending = createCheckerBooking(BookingStatus::Pending, [
        'idempotency_key' => 'checker-module8-key-3',
    ]);
    $rejected = createCheckerBooking(BookingStatus::Rejected, [
        'idempotency_key' => 'checker-module8-key-4',
    ]);
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('checker.dashboard', ['kode' => $pending['booking']->booking_number]))
        ->assertOk()
        ->assertSee('Booking ini belum bisa check-in.');

    $this->actingAs($checker)
        ->get(route('checker.dashboard', ['kode' => $rejected['booking']->booking_number]))
        ->assertOk()
        ->assertSee('Booking ini belum bisa check-in.');
});

it('records check in once and shows existing status afterwards', function () {
    $data = createCheckerBooking(BookingStatus::Approved, [
        'idempotency_key' => 'checker-module8-key-5',
    ]);
    $checker = User::factory()->checker()->create([
        'name' => 'Petugas Pintu',
    ]);

    $this->actingAs($checker)
        ->post(route('checker.check-in', $data['booking']))
        ->assertRedirect(route('checker.dashboard', ['kode' => $data['booking']->booking_number]));

    $this->actingAs($checker)
        ->post(route('checker.check-in', $data['booking']))
        ->assertRedirect(route('checker.dashboard', ['kode' => $data['booking']->booking_number]));

    expect(CheckIn::query()->where('booking_id', $data['booking']->id)->count())->toBe(1);

    $this->actingAs($checker)
        ->get(route('checker.dashboard', ['kode' => $data['booking']->booking_number]))
        ->assertOk()
        ->assertSee('SUDAH_MASUK')
        ->assertSee('Petugas Pintu');
});

it('shows not found message for invalid code', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('checker.dashboard', ['kode' => 'tidak-ada']))
        ->assertOk()
        ->assertSee('Kode tidak ditemukan.');
});
