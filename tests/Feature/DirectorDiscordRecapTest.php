<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Models\Booking;
use App\Models\Package;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed();

    config()->set('cache.default', 'array');
    config()->set('services.discord.director_recap_webhook_url', 'https://discord.test/director');
    config()->set('services.discord.retry_times', 0);
    Cache::flush();
    $this->travelTo(now()->setDate(2026, 7, 20)->setTime(18, 0));
});

function createDirectorRecapBooking(
    string $bookingNumber,
    PackageCode $packageCode,
    string $approvedAt,
    string $amount = '2000000',
): Booking {
    $package = Package::query()->where('code', $packageCode)->firstOrFail();

    $booking = Booking::query()->create([
        'booking_number' => $bookingNumber,
        'idempotency_key' => 'director-'.$bookingNumber,
        'package_id' => $package->id,
        'package_code_snapshot' => $packageCode->value,
        'package_name_snapshot' => $package->name,
        'package_price_snapshot' => $amount,
        'customer_name' => 'Customer Direksi',
        'customer_phone' => '+6281234567890',
        'customer_email' => strtolower($bookingNumber).'@example.com',
        'attendee_count' => 2,
        'referral_source' => 'AGENT',
        'agent_name' => 'Agent Direksi',
        'status' => BookingStatus::Approved,
        'approved_at' => $approvedAt,
    ]);

    $booking->payment()->create([
        'expected_amount' => $amount,
        'sender_name' => 'Customer Direksi',
        'transferred_amount' => $amount,
        'transfer_date' => '2026-07-20',
        'proof_path' => 'proof/director.jpg',
    ]);

    $booking->meal()->create([
        'vegetarian_quantity' => 1,
        'non_vegetarian_quantity' => 1,
    ]);

    return $booking;
}

it('does not send director recap when there is no newly approved booking', function () {
    createDirectorRecapBooking(
        'CD-DIRECTOR-OLD',
        PackageCode::Prayer,
        '2026-07-19 17:59:59',
    );

    $this->artisan('discord:send-director-recap')
        ->assertSuccessful()
        ->expectsOutput('Tidak ada booking baru yang disetujui. Rekapan tidak dikirim.');

    Http::assertNothingSent();
});

it('sends a director recap card with current period and overall totals', function () {
    Http::fake([
        'https://discord.test/director' => Http::response([], 204),
    ]);

    createDirectorRecapBooking(
        'CD-DIRECTOR-PRAYER',
        PackageCode::Prayer,
        '2026-07-19 18:30:00',
        '2000000',
    );
    createDirectorRecapBooking(
        'CD-DIRECTOR-INCENSE',
        PackageCode::Incense,
        '2026-07-20 10:00:00',
        '1000000',
    );
    createDirectorRecapBooking(
        'CD-DIRECTOR-OLD',
        PackageCode::Combo,
        '2026-07-18 10:00:00',
        '3000000',
    );

    $this->artisan('discord:send-director-recap')->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $fields = collect($embed['fields'] ?? [])->keyBy('name');

        return $request->url() === 'https://discord.test/director'
            && ($embed['title'] ?? null) === '📊 Rekapan Booking Chao Du'
            && ($fields['🆕 Booking baru disetujui']['value'] ?? null) === '2'
            && ($fields['✅ Total booking disetujui']['value'] ?? null) === '3'
            && ($fields['💰 Total pemasukan']['value'] ?? null) === 'Rp6.000.000'
            && str_contains((string) ($fields['📦 Rincian paket']['value'] ?? ''), 'Sembahyang: 1')
            && ! str_contains((string) json_encode($request->data()), 'Customer Direksi')
            && collect($embed['fields'] ?? [])->every(
                fn (array $field): bool => ($field['inline'] ?? true) === false,
            );
    });
});

it('does not send the same director recap twice', function () {
    Http::fake([
        'https://discord.test/director' => Http::response([], 204),
    ]);

    createDirectorRecapBooking(
        'CD-DIRECTOR-ONCE',
        PackageCode::Prayer,
        '2026-07-20 09:00:00',
    );

    $this->artisan('discord:send-director-recap')->assertSuccessful();
    $this->artisan('discord:send-director-recap')
        ->assertSuccessful()
        ->expectsOutput('Rekapan untuk periode ini sudah pernah dikirim.');

    Http::assertSentCount(1);
});

it('can send a director recap for a selected date', function () {
    Http::fake([
        'https://discord.test/director' => Http::response([], 204),
    ]);

    createDirectorRecapBooking(
        'CD-DIRECTOR-JULY-14',
        PackageCode::Prayer,
        '2026-07-14 10:00:00',
    );
    createDirectorRecapBooking(
        'CD-DIRECTOR-JULY-15',
        PackageCode::Prayer,
        '2026-07-15 10:00:00',
    );

    $this->artisan('discord:send-director-recap --date=2026-07-14')
        ->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $fields = collect($request->data()['embeds'][0]['fields'] ?? [])->keyBy('name');

        return ($fields['📅 Periode']['value'] ?? null)
                === '13-07-2026 18:00 sampai 14-07-2026 18:00'
            && ($fields['🆕 Booking baru disetujui']['value'] ?? null) === '1'
            && ($fields['✅ Total booking disetujui']['value'] ?? null) === '1';
    });
});

it('rejects an invalid director recap date', function () {
    $this->artisan('discord:send-director-recap --date=14-07-2026')
        ->assertFailed()
        ->expectsOutput('Tanggal harus memakai format YYYY-MM-DD, contoh 2026-07-14.');

    Http::assertNothingSent();
});

it('sends a manual recap containing every approved booking', function () {
    Http::fake([
        'https://discord.test/director' => Http::response([], 204),
    ]);

    createDirectorRecapBooking(
        'CD-DAILY-JULY-13',
        PackageCode::Prayer,
        '2026-07-13 23:59:59',
        '9000000',
    );
    createDirectorRecapBooking(
        'CD-DAILY-JULY-14-A',
        PackageCode::Prayer,
        '2026-07-14 00:00:00',
        '2000000',
    );
    createDirectorRecapBooking(
        'CD-DAILY-JULY-14-B',
        PackageCode::Incense,
        '2026-07-14 23:59:59',
        '1000000',
    );
    $pending = createDirectorRecapBooking(
        'CD-DAILY-PENDING',
        PackageCode::Combo,
        '2026-07-14 12:00:00',
        '5000000',
    );
    $pending->forceFill([
        'status' => BookingStatus::Pending,
        'approved_at' => null,
    ])->save();

    $this->artisan('discord:send-director-daily')
        ->assertSuccessful();

    Http::assertSent(function ($request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $fields = collect($embed['fields'] ?? [])->keyBy('name');

        return ($embed['title'] ?? null) === '📊 Rekapan Keseluruhan Chao Du'
            && ($fields['✅ Booking disetujui']['value'] ?? null) === '3'
            && ($fields['💰 Total pemasukan']['value'] ?? null) === 'Rp12.000.000'
            && ! $fields->has('⏳ Menunggu persetujuan');
    });
});

it('sends an empty manual recap when there are no approved bookings', function () {
    Http::fake([
        'https://discord.test/director' => Http::response([], 204),
    ]);

    $this->artisan('discord:send-director-daily')
        ->assertSuccessful()
        ->expectsOutput('Rekapan seluruh booking yang disetujui berhasil dikirim.');

    Http::assertSent(function ($request): bool {
        $fields = collect($request->data()['embeds'][0]['fields'] ?? [])->keyBy('name');

        return ($fields['✅ Booking disetujui']['value'] ?? null) === '0'
            && ($fields['💰 Total pemasukan']['value'] ?? null) === 'Rp0';
    });
});

it('sends the manual recap every time the command is run', function () {
    Http::fake([
        'https://discord.test/director' => Http::response([], 204),
    ]);

    createDirectorRecapBooking(
        'CD-DAILY-REPEAT',
        PackageCode::Prayer,
        '2026-07-14 10:00:00',
    );

    $this->artisan('discord:send-director-daily')->assertSuccessful();
    $this->artisan('discord:send-director-daily')->assertSuccessful();

    Http::assertSentCount(2);
});

it('does not schedule the manual daily director recap command', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)
        ->toContain('discord:send-director-recap')
        ->not->toContain('discord:send-director-daily');
});

it('can retry a director recap after discord fails', function () {
    createDirectorRecapBooking(
        'CD-DIRECTOR-RETRY',
        PackageCode::Prayer,
        '2026-07-20 09:00:00',
    );

    Http::fake([
        'https://discord.test/director' => Http::sequence()
            ->push(['error' => 'down'], 500)
            ->push([], 204),
    ]);

    $this->artisan('discord:send-director-recap')->assertFailed();

    $this->artisan('discord:send-director-recap')->assertSuccessful();

    Http::assertSentCount(2);
});
