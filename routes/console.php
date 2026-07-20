<?php

use App\Enums\ApprovalIntegrationComponent;
use App\Models\Booking;
use App\Services\ApprovalIntegrationService;
use App\Services\BookingExpiryService;
use App\Services\DirectorDiscordRecapService;
use App\Services\PrayerPaperGenerationService;
use App\Services\VirtualAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('storage:r2-check {--path=healthcheck.txt}', function () {
    $config = config('filesystems.disks.r2');

    if (! is_array($config)) {
        $this->error('Konfigurasi disk R2 tidak ditemukan.');

        return 1;
    }

    $required = ['key', 'secret', 'bucket', 'endpoint'];

    foreach ($required as $field) {
        if (blank($config[$field] ?? null)) {
            $this->error("Konfigurasi R2 belum lengkap pada field: {$field}.");

            return 1;
        }
    }

    $bucket = $config['bucket'];
    $endpoint = $config['endpoint'];
    $region = $config['region'] ?? 'auto';

    if (! is_string($bucket) || ! is_string($endpoint) || ! is_string($region)) {
        $this->error('Tipe konfigurasi R2 tidak valid.');

        return 1;
    }

    $this->line("Bucket   : {$bucket}");
    $this->line("Endpoint : {$endpoint}");
    $this->line("Region   : {$region}");

    $path = $this->option('path');

    if (! is_string($path)) {
        $this->error('Nilai opsi path tidak valid.');

        return 1;
    }

    try {
        $exists = Storage::disk('r2')->exists($path);
    } catch (Throwable $throwable) {
        $this->error('Koneksi R2 gagal diuji.');
        $this->line($throwable->getMessage());

        return 1;
    }

    $this->info(
        $exists
            ? 'Koneksi R2 berhasil. Path uji ditemukan.'
            : 'Koneksi R2 berhasil. Path uji belum ada, tetapi disk dapat diakses.',
    );

    return 0;
})->purpose('Memeriksa koneksi Cloudflare R2 tanpa menampilkan secret.');

Artisan::command('prayer-papers:retry {booking? : Nomor booking}', function (
    PrayerPaperGenerationService $generationService,
) {
    $query = Booking::query()->with(['names', 'prayerPapers']);

    if ($bookingNumber = $this->argument('booking')) {
        $query->where('booking_number', $bookingNumber);
    } else {
        $query->whereIn('prayer_paper_status', ['FAILED', 'PENDING']);
    }

    $bookings = $query->get();

    foreach ($bookings as $booking) {
        $generationService->retry($booking);
        $this->line('Diproses: '.$booking->booking_number);
    }

    return Command::SUCCESS;
})->purpose('Mengulang pembuatan file final kertas doa yang gagal atau belum jadi.');

Artisan::command('approval-integrations:retry {booking : Nomor booking} {component? : qr|drive|notion|approval_email}', function (
    ApprovalIntegrationService $approvalIntegrationService,
) {
    $booking = Booking::query()
        ->with(['approvalIntegration', 'tableSlots', 'incenseSlots', 'payment'])
        ->where('booking_number', $this->argument('booking'))
        ->firstOrFail();

    $component = $this->argument('component');

    if (is_string($component) && $component !== '') {
        $approvalIntegrationService->retry($booking, ApprovalIntegrationComponent::from($component));
        $this->line('Retry komponen dijalankan: '.$component);

        return Command::SUCCESS;
    }

    $approvalIntegrationService->runAfterApproval($booking);
    $this->line('Semua komponen approval dijalankan ulang sesuai status saat ini.');

    return Command::SUCCESS;
})->purpose('Mengulang integrasi approval untuk booking yang sudah disetujui.');

Artisan::command('virtual-accounts:release-expired', function (
    VirtualAccountService $virtualAccountService,
) {
    $count = $virtualAccountService->releaseExpired();
    $this->line("Nomor VA yang dilepas: {$count}");

    return Command::SUCCESS;
})->purpose('Melepas nomor VA yang lewat batas waktu.');

Artisan::command('bookings:expire-unpaid', function (
    BookingExpiryService $bookingExpiryService,
) {
    $count = $bookingExpiryService->expireUnpaidBookings();
    $this->line("Booking hangus: {$count}");

    return Command::SUCCESS;
})->purpose('Menghanguskan booking yang belum kirim pembayaran setelah batas waktu.');

Artisan::command('discord:send-director-recap {--date= : Tanggal akhir periode dalam format YYYY-MM-DD}', function (
    DirectorDiscordRecapService $directorDiscordRecapService,
) {
    $date = $this->option('date');
    $periodEnd = null;

    if (is_string($date) && $date !== '') {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Tanggal harus memakai format YYYY-MM-DD, contoh 2026-07-14.');

            return Command::FAILURE;
        }

        $periodEnd = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            (string) config('app.timezone'),
        );

        if (! $periodEnd || $periodEnd->format('Y-m-d') !== $date) {
            $this->error('Tanggal harus memakai format YYYY-MM-DD, contoh 2026-07-14.');

            return Command::FAILURE;
        }

        $periodEnd = $periodEnd->setTime(18, 0);
    }

    $status = $periodEnd
        ? $directorDiscordRecapService->sendForPeriod($periodEnd)
        : $directorDiscordRecapService->sendLatest();

    return match ($status) {
        DirectorDiscordRecapService::STATUS_SENT => tap(Command::SUCCESS, fn () => $this->info('Rekapan direksi berhasil dikirim.')),
        DirectorDiscordRecapService::STATUS_NO_NEW_BOOKING => tap(Command::SUCCESS, fn () => $this->line('Tidak ada booking baru yang disetujui. Rekapan tidak dikirim.')),
        DirectorDiscordRecapService::STATUS_ALREADY_SENT => tap(Command::SUCCESS, fn () => $this->line('Rekapan untuk periode ini sudah pernah dikirim.')),
        DirectorDiscordRecapService::STATUS_NOT_CONFIGURED => tap(Command::SUCCESS, fn () => $this->warn('Webhook Discord direksi belum diisi.')),
        default => tap(Command::FAILURE, fn () => $this->error('Rekapan direksi gagal dikirim.')),
    };
})->purpose('Mengirim rekapan booking ke Discord direksi jika ada persetujuan baru.');

Artisan::command('discord:send-director-daily', function (
    DirectorDiscordRecapService $directorDiscordRecapService,
) {
    $status = $directorDiscordRecapService->sendCurrentSnapshot();

    return match ($status) {
        DirectorDiscordRecapService::STATUS_SENT => tap(Command::SUCCESS, fn () => $this->info('Rekapan seluruh booking yang disetujui berhasil dikirim.')),
        DirectorDiscordRecapService::STATUS_NOT_CONFIGURED => tap(Command::SUCCESS, fn () => $this->warn('Webhook Discord direksi belum diisi.')),
        default => tap(Command::FAILURE, fn () => $this->error('Rekapan seluruh booking yang disetujui gagal dikirim.')),
    };
})->purpose('Mengirim rekapan manual seluruh booking yang sudah disetujui.');
