<?php

use App\Enums\ApprovalIntegrationComponent;
use App\Enums\BookingStatus;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Jobs\ProcessGalleryVideo;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Services\ApprovalIntegrationService;
use App\Services\BookingExpiryService;
use App\Services\DirectorDiscordRecapService;
use App\Services\GalleryArchiveService;
use App\Services\PrayerPaperGallerySyncService;
use App\Services\PrayerPaperGenerationService;
use App\Services\SlotCapacityService;
use App\Services\TableLayoutGallerySyncService;
use App\Services\VirtualAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

Artisan::command('storage:gallery-check {--write}', function () {
    $diskName = config('gallery.storage_disk');

    if (! is_string($diskName) || $diskName === '') {
        $this->error('Disk gallery belum dikonfigurasi.');

        return Command::FAILURE;
    }

    $config = config('filesystems.disks.'.$diskName);

    if (! is_array($config)) {
        $this->error('Konfigurasi disk gallery tidak ditemukan.');

        return Command::FAILURE;
    }

    if (($config['driver'] ?? null) === 's3') {
        foreach (['key', 'secret', 'bucket', 'endpoint'] as $field) {
            if (blank($config[$field] ?? null)) {
                $this->error("Konfigurasi gallery belum lengkap pada field: {$field}.");

                return Command::FAILURE;
            }
        }
    }

    $disk = Storage::disk($diskName);

    try {
        if (! $this->option('write')) {
            $disk->exists('healthchecks/.connection-check');
            $this->info('Koneksi baca gallery berhasil.');

            return Command::SUCCESS;
        }

        $path = 'healthchecks/'.Str::uuid().'.txt';
        $written = false;

        try {
            $written = $disk->put($path, 'gallery-storage-check', [
                'visibility' => 'private',
                'ContentType' => 'text/plain',
            ]);

            if (! $written || ! $disk->exists($path)) {
                $this->error('File pemeriksaan gallery tidak berhasil ditulis.');

                return Command::FAILURE;
            }
        } finally {
            if ($written) {
                $disk->delete($path);
            }
        }
    } catch (Throwable $throwable) {
        report($throwable);
        $this->error('Koneksi gallery gagal diuji. Periksa konfigurasi dan log aplikasi.');

        return Command::FAILURE;
    }

    $this->info('Koneksi tulis dan hapus gallery berhasil.');

    return Command::SUCCESS;
})->purpose('Memeriksa koneksi storage gallery tanpa menampilkan credential.');

Artisan::command('gallery:cleanup-archives', function (GalleryArchiveService $archiveService) {
    $count = $archiveService->cleanupExpired();
    $this->line("Archive gallery yang dibersihkan: {$count}");

    return Command::SUCCESS;
})->purpose('Menghapus ZIP gallery yang sudah melewati masa simpan.');

Artisan::command('gallery:regenerate-video-thumbnails', function () {
    $count = 0;

    GalleryMedia::query()
        ->where('media_type', GalleryMediaType::Video)
        ->where('status', GalleryMediaStatus::Ready)
        ->whereNull('thumbnail_path')
        ->eachById(function (GalleryMedia $media) use (&$count): void {
            ProcessGalleryVideo::dispatch($media->id, true);
            $count++;
        });

    $this->line("Thumbnail video yang dimasukkan ke antrean: {$count}");

    return Command::SUCCESS;
})->purpose('Membuat thumbnail untuk video gallery lama tanpa menyembunyikan videonya.');

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

Artisan::command('gallery:sync-prayer-papers {booking? : Nomor booking approved}', function (
    PrayerPaperGallerySyncService $syncService,
) {
    $query = Booking::query()
        ->where('status', BookingStatus::Approved)
        ->with('prayerPapers')
        ->orderBy('id');

    if ($bookingNumber = $this->argument('booking')) {
        $query->where('booking_number', $bookingNumber);
    }

    $synced = 0;
    $failed = 0;

    $query->chunkById(100, function ($bookings) use ($syncService, &$synced, &$failed): void {
        foreach ($bookings as $booking) {
            try {
                $syncService->syncForBooking($booking);
                $synced++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error("{$booking->booking_number}: {$exception->getMessage()}");
            }
        }
    });

    $this->info("Sinkronisasi selesai: {$synced} booking berhasil, {$failed} gagal.");

    return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Memasukkan kertas doa dan kertas hio booking approved ke gallery customer.');

Artisan::command('gallery:sync-table-layouts {booking? : Nomor booking approved}', function (
    TableLayoutGallerySyncService $syncService,
) {
    $query = Booking::query()
        ->where('status', BookingStatus::Approved)
        ->whereHas('tableSlots')
        ->with('tableSlots')
        ->orderBy('id');

    if ($bookingNumber = $this->argument('booking')) {
        $query->where('booking_number', $bookingNumber);
    }

    $synced = 0;
    $failed = 0;

    $query->chunkById(100, function ($bookings) use ($syncService, &$synced, &$failed): void {
        foreach ($bookings as $booking) {
            if ($syncService->syncSafely($booking)) {
                $synced++;
            } else {
                $failed++;
                $this->error("{$booking->booking_number}: denah meja gagal dibuat.");
            }
        }
    });

    $this->info("Sinkronisasi denah selesai: {$synced} booking berhasil, {$failed} gagal.");

    return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Membuat atau memperbarui denah meja personal untuk gallery booking approved.');

Artisan::command('slots:sync-capacity', function (SlotCapacityService $capacityService) {
    $result = $capacityService->sync();
    $this->info("Sinkronisasi kapasitas selesai. Meja baru: {$result['tables_created']}, hio baru: {$result['incense_created']}.");

    return Command::SUCCESS;
})->purpose('Menambahkan kapasitas meja dan hio tanpa mengubah slot yang sudah terpakai.');

Artisan::command('approval-integrations:retry {booking : Nomor booking} {component? : qr|approval_email}', function (
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

        $periodEnd = $periodEnd->setTime(20, 0);
    }

    $status = $periodEnd
        ? $directorDiscordRecapService->sendForPeriod($periodEnd)
        : $directorDiscordRecapService->sendLatest();

    return match ($status) {
        DirectorDiscordRecapService::STATUS_SENT => tap(Command::SUCCESS, fn () => $this->info('Rekapan direksi berhasil dikirim.')),
        DirectorDiscordRecapService::STATUS_ALREADY_SENT => tap(Command::SUCCESS, fn () => $this->line('Rekapan untuk periode ini sudah pernah dikirim.')),
        DirectorDiscordRecapService::STATUS_NOT_CONFIGURED => tap(Command::SUCCESS, fn () => $this->warn('Webhook Discord direksi belum diisi.')),
        default => tap(Command::FAILURE, fn () => $this->error('Rekapan direksi gagal dikirim.')),
    };
})->purpose('Mengirim rekapan booking terjadwal ke Discord direksi.');

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
