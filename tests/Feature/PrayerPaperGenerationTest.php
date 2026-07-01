<?php

declare(strict_types=1);

use App\Enums\PackageCode;
use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PrayerPaper;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\PrayerPaperRenderer;
use App\Services\VirtualAccountService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase3.submit_rate_limit_max_attempts', 6);
    config()->set('phase3.submit_rate_limit_decay_seconds', 60);
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    config()->set('phase3.virtual_account_hold_minutes', 60);
    Storage::fake('booking-private');
    Storage::fake('prayer-paper-files');

    $this->seed();
    seedPrayerPaperVirtualAccounts();
});

function seedPrayerPaperVirtualAccounts(): void
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

function activatePrayerPaperPackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function prayerPaperBookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'paper-key-1',
        'customer_name' => 'Budi Santoso',
        'customer_phone_local' => '81234567890',
        'customer_email' => 'customer@gmail.com',
        'attendee_count' => '2',
        'package_code' => PackageCode::Prayer->value,
        'deceased_names' => [
            [
                'indonesian_name' => 'Tan Ah Kok',
                'mandarin_name' => 'æž—ç–æœˆ',
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

function reservePrayerPaperVirtualAccount(array $payload): void
{
    app(VirtualAccountService::class)->reserve(
        PackageCode::from((string) $payload['package_code']),
        (string) $payload['idempotency_key'],
    );
}

it('generates the final prayer paper after booking is stored', function () {
    activatePrayerPaperPackage(PackageCode::Prayer);
    $payload = prayerPaperBookingPayload();
    reservePrayerPaperVirtualAccount($payload);

    $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    $booking = Booking::query()->firstOrFail();
    $paper = PrayerPaper::query()
        ->where('booking_id', $booking->id)
        ->where('type', PrayerPaperType::A)
        ->where('sequence', 1)
        ->firstOrFail();

    expect($paper->status)->toBe(PrayerPaperStatus::Ready)
        ->and($paper->version)->toBe(1)
        ->and($paper->generated_at)->not->toBeNull()
        ->and($paper->file_path)->not->toBeNull()
        ->and($booking->prayer_paper_status)->toBe(PrayerPaperStatus::Ready)
        ->and($booking->latest_prayer_paper_generated_at)->not->toBeNull();

    Storage::disk('prayer-paper-files')->assertExists((string) $paper->file_path);
});

it('creates both final paper types for combo bookings', function () {
    activatePrayerPaperPackage(PackageCode::Combo, '3500000');
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-combo-1',
        'package_code' => PackageCode::Combo->value,
    ]);

    $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-combo-1',
        'package_code' => PackageCode::Combo->value,
        'attendee_count' => '4',
        'deceased_names' => [
            [
                'indonesian_name' => 'Nama Satu',
                'mandarin_name' => 'æž—ç–æœˆ',
                'source_image' => null,
            ],
            [
                'indonesian_name' => 'Nama Dua',
                'mandarin_name' => 'é™³ç§€è“®',
                'source_image' => null,
            ],
        ],
        'incense_name' => [
            'indonesian_name' => 'Keluarga Tan',
            'mandarin_name' => 'é™³å®¶',
            'source_image' => null,
        ],
        'vegetarian_quantity' => '2',
        'non_vegetarian_quantity' => '2',
        'transferred_amount' => '3500000',
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    $booking = Booking::query()->firstOrFail();

    expect(PrayerPaper::query()->where('booking_id', $booking->id)->where('type', PrayerPaperType::A)->count())
        ->toBe(2)
        ->and(PrayerPaper::query()->where('booking_id', $booking->id)->where('type', PrayerPaperType::A)->where('sequence', 1)->value('status'))
        ->toBe(PrayerPaperStatus::Ready)
        ->and(PrayerPaper::query()->where('booking_id', $booking->id)->where('type', PrayerPaperType::A)->where('sequence', 2)->value('status'))
        ->toBe(PrayerPaperStatus::Ready)
        ->and(PrayerPaper::query()->where('booking_id', $booking->id)->where('type', PrayerPaperType::B)->where('sequence', 1)->value('status'))
        ->toBe(PrayerPaperStatus::Ready);
});

it('keeps the booking valid when final paper generation fails', function () {
    activatePrayerPaperPackage(PackageCode::Prayer);
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-fail-1',
        'package_code' => PackageCode::Prayer->value,
    ]);

    $renderer = Mockery::mock(PrayerPaperRenderer::class);
    $renderer->shouldReceive('render')->andThrow(new RuntimeException('renderer gagal'));
    app()->instance(PrayerPaperRenderer::class, $renderer);

    $response = $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-fail-1',
    ]), [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    $booking = Booking::query()->firstOrFail();
    $paper = PrayerPaper::query()
        ->where('booking_id', $booking->id)
        ->where('sequence', 1)
        ->firstOrFail();

    expect($booking->booking_number)->not->toBe('')
        ->and($booking->prayer_paper_status)->toBe(PrayerPaperStatus::Failed)
        ->and($paper->status)->toBe(PrayerPaperStatus::Failed)
        ->and($paper->file_path)->toBeNull()
        ->and($paper->error_message)->not->toBeNull();
});

it('retries failed final paper generation without creating duplicate active versions', function () {
    activatePrayerPaperPackage(PackageCode::Prayer);
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-retry-1',
        'package_code' => PackageCode::Prayer->value,
    ]);

    $renderer = Mockery::mock(PrayerPaperRenderer::class);
    $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('renderer gagal'));
    app()->instance(PrayerPaperRenderer::class, $renderer);

    $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-retry-1',
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    app()->forgetInstance(PrayerPaperRenderer::class);

    $booking = Booking::query()->firstOrFail();

    $this->artisan('prayer-papers:retry', [
        'booking' => $booking->booking_number,
    ])->assertExitCode(0);

    $booking->refresh();
    $paper = PrayerPaper::query()
        ->where('booking_id', $booking->id)
        ->where('type', PrayerPaperType::A)
        ->where('sequence', 1)
        ->firstOrFail();

    expect(PrayerPaper::query()->where('booking_id', $booking->id)->where('type', PrayerPaperType::A)->count())->toBe(1)
        ->and($paper->status)->toBe(PrayerPaperStatus::Ready)
        ->and($paper->version)->toBe(1)
        ->and($booking->prayer_paper_status)->toBe(PrayerPaperStatus::Ready);
});

it('shows the latest final paper file on the admin booking detail page', function () {
    activatePrayerPaperPackage(PackageCode::Prayer);
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-admin-1',
        'package_code' => PackageCode::Prayer->value,
    ]);

    $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-admin-1',
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    $booking = Booking::query()->firstOrFail();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertSee($booking->booking_number)
        ->assertSee('READY')
        ->assertSee('admin\\/kertas-doa\\/'.PrayerPaper::query()->firstOrFail()->id, false);
});

it('uses the saved single-name position for Indonesian prayer paper text', function () {
    activatePrayerPaperPackage(PackageCode::Prayer);
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-layout-1',
        'package_code' => PackageCode::Prayer->value,
    ]);

    AppSetting::putMany([
        'prayer_paper_a_marking' => json_encode([
            'canvas_width' => 800,
            'canvas_height' => 1200,
            'markers' => [
                'single' => ['x' => 150, 'y' => 300, 'width' => 100, 'height' => 300],
                'left' => ['x' => 100, 'y' => 300, 'width' => 80, 'height' => 300],
                'right' => ['x' => 300, 'y' => 300, 'width' => 80, 'height' => 300],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-layout-1',
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
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    $paper = PrayerPaper::query()->where('type', PrayerPaperType::A)->where('sequence', 1)->firstOrFail();
    $svg = Storage::disk('prayer-paper-files')->get((string) $paper->file_path);

    expect($svg)
        ->toContain('viewBox="0 0 800 1200"')
        ->toContain('rotate(90 200.00 450.00)')
        ->toContain('fill="#000000"')
        ->toContain('Tan Ah Kok');
});

it('creates two separate prayer papers when two prayer names are filled', function () {
    activatePrayerPaperPackage(PackageCode::Prayer);
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-layout-2',
        'package_code' => PackageCode::Prayer->value,
    ]);

    AppSetting::putMany([
        'prayer_paper_a_marking' => json_encode([
            'canvas_width' => 900,
            'canvas_height' => 1400,
            'markers' => [
                'single' => ['x' => 400, 'y' => 420, 'width' => 120, 'height' => 480],
                'left' => ['x' => 110, 'y' => 420, 'width' => 70, 'height' => 420],
                'right' => ['x' => 310, 'y' => 420, 'width' => 90, 'height' => 420],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-layout-2',
        'deceased_names' => [
            [
                'indonesian_name' => 'Nama Satu',
                'mandarin_name' => 'æž—ç–æœˆ',
                'source_image' => null,
            ],
            [
                'indonesian_name' => 'Nama Dua',
                'mandarin_name' => '',
                'source_image' => null,
            ],
        ],
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    $papers = PrayerPaper::query()
        ->where('type', PrayerPaperType::A)
        ->orderBy('sequence')
        ->get();

    expect($papers)->toHaveCount(2);

    $firstSvg = Storage::disk('prayer-paper-files')->get((string) $papers[0]->file_path);
    $secondSvg = Storage::disk('prayer-paper-files')->get((string) $papers[1]->file_path);

    expect(substr_count($firstSvg, '<text '))
        ->toBeGreaterThan(1)
        ->and($firstSvg)
        ->toContain('fill="#000000"')
        ->not->toContain('Nama Dua');

    expect($secondSvg)
        ->toContain('rotate(90 460.00 660.00)')
        ->toContain('Nama Dua')
        ->toContain('fill="#000000"')
        ->not->toContain('<text x="460.00" y="468.00"');
});

it('uses the saved hio position for incense paper text', function () {
    activatePrayerPaperPackage(PackageCode::Incense);
    reservePrayerPaperVirtualAccount([
        'idempotency_key' => 'paper-layout-hio-1',
        'package_code' => PackageCode::Incense->value,
    ]);

    AppSetting::putMany([
        'prayer_paper_b_marking' => json_encode([
            'canvas_width' => 700,
            'canvas_height' => 1200,
            'markers' => [
                'single' => ['x' => 200, 'y' => 260, 'width' => 90, 'height' => 420],
                'left' => ['x' => 200, 'y' => 260, 'width' => 90, 'height' => 420],
                'right' => ['x' => 200, 'y' => 260, 'width' => 90, 'height' => 420],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    $this->post(route('api.public.bookings.store'), prayerPaperBookingPayload([
        'idempotency_key' => 'paper-layout-hio-1',
        'package_code' => PackageCode::Incense->value,
        'deceased_names' => [
            [
                'indonesian_name' => '',
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
            'indonesian_name' => 'Keluarga Tan',
            'mandarin_name' => '',
            'source_image' => null,
        ],
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    $paper = PrayerPaper::query()->where('type', PrayerPaperType::B)->where('sequence', 1)->firstOrFail();
    $svg = Storage::disk('prayer-paper-files')->get((string) $paper->file_path);

    expect($svg)
        ->toContain('viewBox="0 0 700 1200"')
        ->toContain('textLength="79.20"')
        ->toContain('fill="#E82C2A"')
        ->toContain('Keluarga Tan');
});
