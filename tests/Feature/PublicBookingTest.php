<?php

declare(strict_types=1);

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\IncenseSlot;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\VirtualAccount;
use App\Services\VirtualAccountService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase3.submit_rate_limit_max_attempts', 6);
    config()->set('phase3.submit_rate_limit_decay_seconds', 60);
    config()->set('phase3.virtual_account_hold_minutes', 60);
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase4.ocr_rate_limit_max_attempts', 6);
    config()->set('phase4.ocr_rate_limit_decay_seconds', 60);
    config()->set('phase4.ocr_timeout_seconds', 10);
    config()->set('phase4.ocr_retry_times', 1);
    config()->set('phase4.ocr_endpoint', '/v1/api/documents/extract');
    config()->set('phase4.ocr_type', 'ocr');
    config()->set('phase4.ocr_lang', 'chi');
    config()->set('phase4.ocr_retain', false);
    config()->set('services.two_ocr.api_key', 'two-ocr-secret');
    config()->set('services.two_ocr.base_url', 'https://backend.scandocflow.com');
    Storage::fake('booking-private');

    $this->seed();
    seedVirtualAccounts();
});

function seedVirtualAccounts(): void
{
    foreach ([
        [PackageCode::Prayer, ['900001', '900002', '900003']],
        [PackageCode::Incense, ['910001', '910002']],
        [PackageCode::Combo, ['920001', '920002', '920003']],
    ] as [$packageCode, $numbers]) {
        foreach ($numbers as $number) {
            VirtualAccount::query()->create([
                'package_code' => $packageCode,
                'account_number' => $number,
            ]);
        }
    }
}

function activatePackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function bookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'booking-key-1',
        'customer_name' => 'Budi Santoso',
        'customer_phone_local' => '81234567890',
        'customer_email' => 'customer@gmail.com',
        'attendee_count' => '2',
        'package_code' => PackageCode::Prayer->value,
        'deceased_names' => [
            [
                'indonesian_name' => 'Tan Ah Kok',
                'mandarin_name' => '',
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
        'transfer_date' => now()->toDateString(),
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
        'referral_source' => 'TEMAN',
        'agent_name' => '',
        'confirmation_checked' => '1',
        'captcha_token' => '',
    ], $overrides);
}

function reserveVirtualAccountForPayload(array $payload): void
{
    app(VirtualAccountService::class)->reserve(
        PackageCode::from((string) $payload['package_code']),
        (string) $payload['idempotency_key'],
    );
}

it('creates a prayer booking and reserves the first table slot', function () {
    activatePackage(PackageCode::Prayer);
    $payload = bookingPayload();
    reserveVirtualAccountForPayload($payload);

    $response = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['booking_number', 'success_url']);

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->customer_phone)->toBe('+6281234567890')
        ->and($booking->package_code_snapshot)->toBe(PackageCode::Prayer->value)
        ->and($booking->names()->count())->toBe(1)
        ->and($booking->meal?->vegetarian_quantity)->toBe(1)
        ->and($booking->meal?->non_vegetarian_quantity)->toBe(1)
        ->and($booking->payment?->proof_path)->toStartWith('booking-files/booking-key-1/')
        ->and($booking->payment?->virtual_account_number)->toBe('900001')
        ->and(TableSlot::query()->where('booking_id', $booking->id)->value('code'))->toBe('A18')
        ->and(TableSlot::query()->where('booking_id', $booking->id)->value('status'))->toBe(SlotStatus::Reserved)
        ->and(IncenseSlot::query()->where('booking_id', $booking->id)->exists())->toBeFalse();

    Storage::disk('booking-private')->assertExists($booking->payment?->proof_path ?? '');
});

it('creates a combo booking with table, incense, and both name groups', function () {
    activatePackage(PackageCode::Combo, '3500000');

    $payload = bookingPayload([
        'idempotency_key' => 'combo-key-1',
        'package_code' => PackageCode::Combo->value,
        'attendee_count' => '4',
        'deceased_names' => [
            [
                'indonesian_name' => 'Nama Satu',
                'mandarin_name' => '',
            ],
            [
                'indonesian_name' => '',
                'mandarin_name' => 'åå­—äºŒ',
            ],
        ],
        'incense_name' => [
            'indonesian_name' => 'Keluarga Tan',
            'mandarin_name' => '',
        ],
        'vegetarian_quantity' => '2',
        'non_vegetarian_quantity' => '2',
    ]);
    reserveVirtualAccountForPayload($payload);

    $response = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    $booking = Booking::query()->firstOrFail();

    expect($booking->names()
        ->where('category', BookingNameCategory::Deceased->value)
        ->count())->toBe(2)
        ->and($booking->names()
            ->where('category', BookingNameCategory::Incense->value)
            ->count())->toBe(1)
        ->and($booking->payment?->virtual_account_number)->toBe('920001')
        ->and(TableSlot::query()->where('booking_id', $booking->id)->value('code'))->toBe('A18')
        ->and(IncenseSlot::query()->where('booking_id', $booking->id)->value('number'))->toBe(1);
});

it('allows zero meal quantities in public booking', function () {
    activatePackage(PackageCode::Prayer);

    $payload = bookingPayload([
        'idempotency_key' => 'booking-zero-meal',
        'vegetarian_quantity' => '0',
        'non_vegetarian_quantity' => '0',
    ]);
    reserveVirtualAccountForPayload($payload);

    $response = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    $booking = Booking::query()->latest('id')->firstOrFail();

    expect($booking->meal?->vegetarian_quantity)->toBe(0)
        ->and($booking->meal?->non_vegetarian_quantity)->toBe(0);
});

it('does not create a duplicate booking when the same form is sent twice', function () {
    activatePackage(PackageCode::Prayer);

    $payload = bookingPayload();
    reserveVirtualAccountForPayload($payload);

    $first = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);
    $second = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);

    $first->assertCreated();
    $second->assertCreated();

    expect(Booking::query()->count())->toBe(1)
        ->and(TableSlot::query()->whereNotNull('booking_id')->count())->toBe(1)
        ->and($first->json('booking_number'))->toBe($second->json('booking_number'));
});

it('rejects booking when the selected package is sold out', function () {
    activatePackage(PackageCode::Prayer);
    reserveVirtualAccountForPayload(bookingPayload());

    TableSlot::query()->update([
        'status' => SlotStatus::Reserved->value,
    ]);

    $this->post(route('api.public.bookings.store'), bookingPayload(), [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('package_code');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects invalid proof file types', function () {
    activatePackage(PackageCode::Prayer);

    $payload = bookingPayload([
        'proof' => UploadedFile::fake()->create('bukti.txt', 10, 'text/plain'),
    ]);
    reserveVirtualAccountForPayload($payload);

    $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('proof');
});

it('reads mandarin text from a photo', function () {
    Http::fake([
        'https://backend.scandocflow.com/*' => Http::response([
            'requestId' => 'abc-123',
            'status' => 'success',
            'documents' => [
                [
                    'plainTextBase64' => base64_encode('æž—ç–æœˆ'),
                ],
            ],
        ], 200),
    ]);

    $this->post(route('api.public.ocr.store'), [
        'source_image' => UploadedFile::fake()->image('nama.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJson([
            'text' => 'æž—ç–æœˆ',
        ])
        ->assertJsonMissingPath('api_key');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://backend.scandocflow.com/v1/api/documents/extract')
            && str_contains($request->url(), 'access_token=two-ocr-secret')
            && $request->hasFile('files', filename: 'nama.jpg')
            && str_contains($request->body(), 'name="type"')
            && str_contains($request->body(), 'ocr')
            && str_contains($request->body(), 'name="lang"')
            && str_contains($request->body(), 'chi')
            && str_contains($request->body(), 'name="retain"')
            && str_contains($request->body(), 'false');
    });
});

it('rejects invalid photo types for name reading', function () {
    $this->post(route('api.public.ocr.store'), [
        'source_image' => UploadedFile::fake()->create('nama.txt', 10, 'text/plain'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_image');
});

it('stores separate source photos for each name', function () {
    activatePackage(PackageCode::Combo, '3500000');

    $payload = bookingPayload([
        'idempotency_key' => 'combo-photos-1',
        'package_code' => PackageCode::Combo->value,
        'attendee_count' => '4',
        'deceased_names' => [
            [
                'indonesian_name' => 'Nama Satu',
                'mandarin_name' => 'åå­—ä¸€',
                'source_image' => UploadedFile::fake()->image('deceased-1.jpg'),
            ],
            [
                'indonesian_name' => 'Nama Dua',
                'mandarin_name' => 'åå­—äºŒ',
                'source_image' => UploadedFile::fake()->image('deceased-2.jpg'),
            ],
        ],
        'incense_name' => [
            'indonesian_name' => 'Keluarga Tan',
            'mandarin_name' => 'é™³å®¶',
            'source_image' => UploadedFile::fake()->image('incense.jpg'),
        ],
        'vegetarian_quantity' => '2',
        'non_vegetarian_quantity' => '2',
    ]);
    reserveVirtualAccountForPayload($payload);

    $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    $booking = Booking::query()->firstOrFail();

    $firstDeceased = $booking->names()
        ->where('category', BookingNameCategory::Deceased->value)
        ->where('position', 1)
        ->firstOrFail();
    $secondDeceased = $booking->names()
        ->where('category', BookingNameCategory::Deceased->value)
        ->where('position', 2)
        ->firstOrFail();
    $incense = $booking->names()
        ->where('category', BookingNameCategory::Incense->value)
        ->where('position', 1)
        ->firstOrFail();

    expect($firstDeceased->mandarin_source_image_path)->not->toBeNull()
        ->and($secondDeceased->mandarin_source_image_path)->not->toBeNull()
        ->and($incense->mandarin_source_image_path)->not->toBeNull()
        ->and($firstDeceased->mandarin_source_image_path)->not->toBe($secondDeceased->mandarin_source_image_path)
        ->and($firstDeceased->mandarin_source_image_path)->not->toBe($incense->mandarin_source_image_path);

    Storage::disk('booking-private')->assertExists((string) $firstDeceased->mandarin_source_image_path);
    Storage::disk('booking-private')->assertExists((string) $secondDeceased->mandarin_source_image_path);
    Storage::disk('booking-private')->assertExists((string) $incense->mandarin_source_image_path);
});

it('limits repeated submit attempts from the same sender in a short time', function () {
    activatePackage(PackageCode::Prayer);

    config()->set('phase3.submit_rate_limit_max_attempts', 1);

    $firstPayload = bookingPayload([
        'idempotency_key' => 'limit-key-1',
    ]);
    reserveVirtualAccountForPayload($firstPayload);

    $secondPayload = bookingPayload([
        'idempotency_key' => 'limit-key-2',
    ]);
    reserveVirtualAccountForPayload($secondPayload);

    $this->post(route('api.public.bookings.store'), $firstPayload, [
        'Accept' => 'application/json',
    ])
        ->assertCreated();

    $this->post(route('api.public.bookings.store'), $secondPayload, [
        'Accept' => 'application/json',
    ])
        ->assertTooManyRequests();
});

it('reserves a virtual account for the selected package', function () {
    $response = $this->postJson(route('api.public.virtual-accounts.reserve'), [
        'idempotency_key' => 'reserve-key-1',
        'package_code' => PackageCode::Prayer->value,
    ]);

    $response->assertOk()
        ->assertJsonPath('account_number', '900001')
        ->assertJsonPath('package_code', PackageCode::Prayer->value);
});

it('reuses the same held virtual account while it is still active', function () {
    $first = $this->postJson(route('api.public.virtual-accounts.reserve'), [
        'idempotency_key' => 'reserve-key-2',
        'package_code' => PackageCode::Prayer->value,
    ]);
    $second = $this->postJson(route('api.public.virtual-accounts.reserve'), [
        'idempotency_key' => 'reserve-key-2',
        'package_code' => PackageCode::Prayer->value,
    ]);

    expect($first->json('account_number'))->toBe('900001')
        ->and($second->json('account_number'))->toBe('900001');
});

it('releases the old virtual account when the package changes', function () {
    $this->postJson(route('api.public.virtual-accounts.reserve'), [
        'idempotency_key' => 'reserve-key-3',
        'package_code' => PackageCode::Prayer->value,
    ])->assertOk();

    $response = $this->postJson(route('api.public.virtual-accounts.reserve'), [
        'idempotency_key' => 'reserve-key-3',
        'package_code' => PackageCode::Combo->value,
    ]);

    $response->assertOk()
        ->assertJsonPath('account_number', '920001');

    expect(
        VirtualAccount::query()
            ->where('package_code', PackageCode::Prayer)
            ->where('account_number', '900001')
            ->value('status'),
    )->toBe(\App\Enums\VirtualAccountStatus::Available);
});

it('rejects booking submit when the virtual account has expired', function () {
    activatePackage(PackageCode::Prayer);

    $payload = bookingPayload([
        'idempotency_key' => 'expired-va-key',
    ]);
    reserveVirtualAccountForPayload($payload);

    VirtualAccount::query()
        ->where('hold_reference', 'expired-va-key')
        ->update([
            'hold_expires_at' => now()->subMinute(),
        ]);

    $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('package_code');
});
