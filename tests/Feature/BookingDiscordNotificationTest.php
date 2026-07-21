<?php

declare(strict_types=1);

use App\Enums\PackageCode;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ApprovalEmailService;
use App\Services\BookingPaymentLinkService;
use App\Services\GoogleDriveClient;
use App\Services\NotionClient;
use App\Services\VirtualAccountService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase3.submit_rate_limit_max_attempts', 6);
    config()->set('phase3.submit_rate_limit_decay_seconds', 60);
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.enabled', true);
    config()->set('phase5.storage_disk', 'booking-private');
    config()->set('services.discord.general_booking_webhook_url', 'https://discord.test/general');
    config()->set('services.discord.agent_booking_webhook_url', 'https://discord.test/agent');
    Storage::fake('booking-private');

    $this->seed();
    seedDiscordVirtualAccounts();
    AppSetting::putMany([
        'bank_name' => 'BCA',
        'bank_account_holder' => 'Lestari',
        'virtual_account_mode' => VirtualAccountService::MODE_FIXED,
    ]);
});

function seedDiscordVirtualAccounts(): void
{
    foreach ([
        [PackageCode::Prayer, '900001'],
        [PackageCode::Incense, '910001'],
        [PackageCode::Combo, '920001'],
    ] as [$packageCode, $number]) {
        VirtualAccount::query()->create([
            'package_code' => $packageCode,
            'account_number' => $number,
        ]);
    }
}

function activateDiscordPackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function discordBookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'discord-booking-key-1',
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
        'vegetarian_quantity' => '0',
        'non_vegetarian_quantity' => '0',
        'sender_name' => 'Budi',
        'transfer_date' => now()->toDateString(),
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
        'referral_source' => 'TEMAN',
        'agent_name' => '',
        'confirmation_checked' => '1',
        'captcha_token' => '',
    ], $overrides);
}

it('sends general discord notification for every successful booking', function () {
    activateDiscordPackage(PackageCode::Prayer);

    Http::fake([
        'https://discord.test/general' => Http::response(['ok' => true], 204),
        'https://discord.test/agent' => Http::response(['ok' => true], 204),
    ]);

    $response = $this->post(route('api.public.bookings.store'), discordBookingPayload(), [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/general');
});

it('sends complete customer data after payment is submitted with the booking', function () {
    activateDiscordPackage(PackageCode::Combo, '3000000');

    Http::fake([
        'https://discord.test/general' => Http::response(['ok' => true], 204),
    ]);

    $response = $this->post(route('api.public.bookings.store'), discordBookingPayload([
        'idempotency_key' => 'discord-booking-created-without-payment',
        'package_code' => PackageCode::Combo->value,
        'deceased_names' => [
            [
                'indonesian_name' => 'Tan Ah Kok',
                'mandarin_name' => '陳亞國',
                'source_image' => null,
            ],
            [
                'indonesian_name' => 'Lim Mei Mei',
                'mandarin_name' => '林美美',
                'source_image' => null,
            ],
        ],
        'incense_name' => [
            'indonesian_name' => 'Keluarga Tan',
            'mandarin_name' => '陳氏家族',
            'source_image' => null,
        ],
        'vegetarian_quantity' => '1',
        'non_vegetarian_quantity' => '3',
        'referral_source' => 'AGENT',
        'agent_name' => 'Budi Agent',
    ]), [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    Http::assertSent(function ($request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $fields = collect($embed['fields'] ?? [])->keyBy('name');

        return $request->url() === 'https://discord.test/general'
            && ($embed['title'] ?? null) === '🆕 Booking Baru Masuk'
            && ($fields['👤 Nama Customer']['value'] ?? null) === 'Budi Santoso'
            && ($fields['📱 Nomor Telepon']['value'] ?? null) === '+6281234567890'
            && ($fields['✉️ Email']['value'] ?? null) === 'customer@gmail.com'
            && ($fields['📦 Paket']['value'] ?? null) === 'Combo — Rp3.000.000'
            && ($fields['👥 Jumlah Hadir']['value'] ?? null) === '2 orang'
            && str_contains((string) ($fields['🙏 Nama Mendiang 1']['value'] ?? ''), '陳亞國')
            && str_contains((string) ($fields['🙏 Nama Mendiang 2']['value'] ?? ''), 'Lim Mei Mei')
            && str_contains((string) ($fields['🧧 Nama Hio']['value'] ?? ''), '陳氏家族')
            && ($fields['🥬 Vegetarian']['value'] ?? null) === '1 porsi'
            && ($fields['🍗 Nonvegetarian']['value'] ?? null) === '3 porsi'
            && ($fields['🪑 Nomor Meja']['value'] ?? null) !== '-'
            && ($fields['🧧 Nomor Hio']['value'] ?? null) !== '-'
            && ($fields['📣 Sumber']['value'] ?? null) === 'Agent'
            && ($fields['🧑‍💼 Nama Agent']['value'] ?? null) === 'Budi Agent'
            && ($fields['💳 Nomor VA']['value'] ?? null) === '920001'
            && ($fields['🧾 Status Pembayaran']['value'] ?? null) === 'Bukti pembayaran sudah diunggah'
            && ($fields['🏦 Nama Pengirim']['value'] ?? null) === 'Budi'
            && ($fields['📅 Tanggal Transfer']['value'] ?? null) === now()->format('d-m-Y');
    });
});

it('waits to send the general notification until payment is submitted later', function () {
    activateDiscordPackage(PackageCode::Prayer);

    Http::fake([
        'https://discord.test/general' => Http::response(['ok' => true], 204),
    ]);

    $this->post(route('api.public.bookings.store'), discordBookingPayload([
        'idempotency_key' => 'discord-booking-pay-later',
        'sender_name' => null,
        'transfer_date' => null,
        'proof' => null,
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    Http::assertNothingSent();

    $booking = Booking::query()->where('idempotency_key', 'discord-booking-pay-later')->firstOrFail();
    $token = parse_url(app(BookingPaymentLinkService::class)->paymentUrl($booking), PHP_URL_QUERY);
    parse_str(is_string($token) ? $token : '', $query);

    $this->post(route('public.booking.payment.store', $booking), [
        'token' => $query['token'] ?? '',
        'sender_name' => 'Budi Santoso',
        'transfer_date' => now()->toDateString(),
        'proof' => UploadedFile::fake()->image('bukti-susulan.jpg'),
    ], [
        'Accept' => 'application/json',
    ])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/general');
});

it('also sends agent discord notification for agent booking', function () {
    activateDiscordPackage(PackageCode::Prayer);

    Http::fake([
        'https://discord.test/general' => Http::response(['ok' => true], 204),
        'https://discord.test/agent' => Http::response(['ok' => true], 204),
    ]);

    $response = $this->post(route('api.public.bookings.store'), discordBookingPayload([
        'idempotency_key' => 'discord-booking-agent-1',
        'referral_source' => 'AGENT',
        'agent_name' => 'Budi Agent',
    ]), [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();
    $booking = Booking::query()->firstOrFail();
    $admin = User::factory()->admin()->create();
    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldReceive('ensureFolder')->once()->andReturn([
        'id' => 'drive-'.$booking->booking_number,
        'url' => 'https://drive.test/'.$booking->booking_number,
    ]);
    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldReceive('ensureBookingPage')->once()->andReturn([
        'id' => 'notion-'.$booking->booking_number,
        'url' => 'https://notion.test/'.$booking->booking_number,
    ]);
    $emailService = Mockery::mock(ApprovalEmailService::class);
    $emailService->shouldReceive('sendApprovedEmail')->once();
    app()->instance(GoogleDriveClient::class, $driveClient);
    app()->instance(NotionClient::class, $notionClient);
    app()->instance(ApprovalEmailService::class, $emailService);

    $this->actingAs($admin)
        ->post(route('admin.bookings.approve', $booking))
        ->assertRedirect();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/general');
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/agent');
});

it('does not fail booking when discord notification fails', function () {
    activateDiscordPackage(PackageCode::Prayer);

    Http::fake([
        'https://discord.test/general' => Http::response(['error' => 'down'], 500),
        'https://discord.test/agent' => Http::response(['error' => 'down'], 500),
    ]);

    $response = $this->post(route('api.public.bookings.store'), discordBookingPayload([
        'idempotency_key' => 'discord-booking-failed-1',
    ]), [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    expect(Booking::query()->count())->toBe(1);
});

it('does not create duplicate booking or duplicate discord when retried with same idempotency key', function () {
    activateDiscordPackage(PackageCode::Prayer);

    Http::fake([
        'https://discord.test/general' => Http::response(['ok' => true], 204),
        'https://discord.test/agent' => Http::response(['ok' => true], 204),
    ]);

    $payload = discordBookingPayload([
        'idempotency_key' => 'discord-booking-retry-1',
    ]);

    $first = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);
    $second = $this->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ]);

    $first->assertCreated();
    $second->assertCreated();

    expect(Booking::query()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('sends discord card message content', function () {
    activateDiscordPackage(PackageCode::Prayer);

    Http::fake([
        'https://discord.test/general' => Http::response(['ok' => true], 204),
    ]);

    $this->post(route('api.public.bookings.store'), discordBookingPayload([
        'customer_name' => 'testing discord',
    ]), [
        'Accept' => 'application/json',
    ])->assertCreated();

    Http::assertSent(function ($request) {
        $data = $request->data();
        $embed = $data['embeds'][0] ?? null;
        $fields = collect($embed['fields'] ?? []);

        return $request->url() === 'https://discord.test/general'
            && ($embed['title'] ?? null) === '🆕 Booking Baru Masuk'
            && ($embed['author']['name'] ?? null) === 'Chao Du Booking'
            && $fields->contains(fn ($field) => ($field['name'] ?? null) === '🎫 Nomor Booking')
            && $fields->contains(fn ($field) => ($field['name'] ?? null) === '👤 Nama Customer' && ($field['value'] ?? null) === 'testing discord')
            && $fields->contains(fn ($field) => ($field['name'] ?? null) === '💳 Nomor VA' && ($field['value'] ?? null) === '900001' && ($field['inline'] ?? null) === false);
    });
});
