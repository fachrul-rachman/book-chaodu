<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PrayerPaper;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    Storage::fake('booking-private');
    Storage::fake('prayer-paper-files');

    $this->seed();

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
});

function activatePrinterPackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function printerBookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'printer-booking-key-1',
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

function createPrinterBooking(array $overrides = []): Booking
{
    $payload = printerBookingPayload($overrides);
    activatePrinterPackage(PackageCode::from($payload['package_code']));

    test()->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    return Booking::query()->latest('id')->firstOrFail()->fresh(['prayerPapers']) ?? Booking::query()->latest('id')->firstOrFail();
}

it('allows a printer to log in from the single login page', function () {
    $printer = User::factory()->printer()->create([
        'email' => 'printer@chaodu.test',
        'password' => 'rahasia123',
    ]);

    $this->post('/masuk', [
        'email' => $printer->email,
        'password' => 'rahasia123',
    ])->assertRedirect(route('printer.dashboard'));

    $this->assertAuthenticatedAs($printer);
});

it('shows only approved bookings on the printer page', function () {
    $approved = createPrinterBooking();
    $approved->forceFill([
        'status' => BookingStatus::Approved,
    ])->save();

    $pending = createPrinterBooking([
        'idempotency_key' => 'printer-booking-key-2',
    ]);

    $printer = User::factory()->printer()->create();

    $response = $this->actingAs($printer)
        ->get(route('printer.dashboard'))
        ->assertOk();

    $bookings = collect($response->viewData('page')['props']['bookings'] ?? []);

    expect($bookings->pluck('booking_number')->all())
        ->toContain($approved->booking_number)
        ->not->toContain($pending->booking_number);
});

it('allows printer to mark booking as printed', function () {
    $booking = createPrinterBooking();
    $booking->forceFill([
        'status' => BookingStatus::Approved,
        'is_printed' => false,
    ])->save();

    $printer = User::factory()->printer()->create();

    $this->actingAs($printer)
        ->put(route('printer.bookings.print', $booking), [
            'is_printed' => true,
        ])->assertRedirect();

    expect($booking->fresh()?->is_printed)->toBeTrue();
});

it('allows printer to open quick prayer paper preview', function () {
    $printer = User::factory()->printer()->create();

    $this->actingAs($printer)
        ->get(route('printer.prayer-paper-preview'))
        ->assertOk()
        ->assertSee('admin\\/prayer-paper-preview\\/index', false)
        ->assertDontSee('Simpan ukuran tulisan');
});

it('blocks checker users from the printer page', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('printer.dashboard'))
        ->assertForbidden();
});

it('blocks printer from updating prayer paper text settings', function () {
    $printer = User::factory()->printer()->create();

    $this->actingAs($printer)
        ->put('/admin/kertas-doa/cek-cepat/pengaturan-tulisan', [
            'prayer' => [
                'vertical' => [
                    'font_scale' => 1,
                    'line_height' => 1.38,
                    'column_gap_scale' => 0.72,
                ],
                'rotated' => [
                    'font_scale' => 1,
                ],
            ],
            'incense' => [
                'vertical' => [
                    'font_scale' => 1,
                    'line_height' => 1.38,
                    'column_gap_scale' => 0.72,
                ],
                'horizontal' => [
                    'font_scale' => 1,
                    'line_height' => 1.28,
                ],
            ],
        ])
        ->assertForbidden();
});

it('lets printer download approved prayer paper files', function () {
    Storage::fake('prayer-paper-files');

    $booking = createPrinterBooking();
    $booking->forceFill([
        'status' => BookingStatus::Approved,
    ])->save();

    $paper = PrayerPaper::query()->where('booking_id', $booking->id)->firstOrFail();
    $paper->forceFill([
        'file_path' => 'papers/test.png',
    ])->save();

    Storage::disk('prayer-paper-files')->put('papers/test.png', 'test-file');

    $printer = User::factory()->printer()->create();

    $this->actingAs($printer)
        ->get(route('printer.prayer-papers.show', $paper))
        ->assertOk();
});
