<?php

namespace App\Services;

use App\Enums\ApprovalIntegrationComponent;
use App\Enums\ApprovalIntegrationStatus;
use App\Enums\PackageCode;
use App\Models\ApprovalIntegration;
use App\Models\Booking;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApprovalIntegrationService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly GoogleDriveClient $googleDriveClient,
        private readonly NotionClient $notionClient,
        private readonly ApprovalEmailService $approvalEmailService,
    ) {}

    public function ensureRow(Booking $booking): ApprovalIntegration
    {
        return ApprovalIntegration::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'qr_status' => ApprovalIntegrationStatus::Pending,
                'drive_status' => ApprovalIntegrationStatus::Pending,
                'notion_status' => ApprovalIntegrationStatus::Pending,
                'approval_email_status' => ApprovalIntegrationStatus::Pending,
            ],
        );
    }

    public function runAfterApproval(Booking $booking): ApprovalIntegration
    {
        $integration = $this->ensureRow($booking);
        $booking = $this->loadBooking($booking);

        if ($integration->qr_status !== ApprovalIntegrationStatus::Succeeded) {
            $integration = $this->runComponent($booking, $integration, ApprovalIntegrationComponent::Qr);
        }

        if ($integration->drive_status !== ApprovalIntegrationStatus::Succeeded) {
            $integration = $this->runComponent($booking, $integration, ApprovalIntegrationComponent::Drive);
        }

        if ($integration->notion_status !== ApprovalIntegrationStatus::Succeeded) {
            $integration = $this->runComponent($booking, $integration, ApprovalIntegrationComponent::Notion);
        }

        if ($integration->approval_email_status !== ApprovalIntegrationStatus::Succeeded) {
            $integration = $this->runComponent($booking, $integration, ApprovalIntegrationComponent::ApprovalEmail);
        }

        return $integration->fresh() ?? $integration;
    }

    public function retry(Booking $booking, ApprovalIntegrationComponent $component): ApprovalIntegration
    {
        $integration = $this->ensureRow($booking);

        return $this->runComponent($this->loadBooking($booking), $integration, $component);
    }

    public function signedQrUrl(ApprovalIntegration $integration): ?string
    {
        if (blank($integration->qr_image_path)) {
            return null;
        }

        $diskName = (string) config('phase7.storage_disk');
        $disk = Storage::disk($diskName);
        $driver = config('filesystems.disks.'.$diskName.'.driver');

        if ($driver === 's3') {
            return $disk->temporaryUrl($integration->qr_image_path, now()->addMinutes(10));
        }

        return route('admin.bookings.qr.show', $integration->booking_id);
    }

    private function runComponent(
        Booking $booking,
        ApprovalIntegration $integration,
        ApprovalIntegrationComponent $component,
    ): ApprovalIntegration {
        $this->markProcessing($integration, $component);

        try {
            match ($component) {
                ApprovalIntegrationComponent::Qr => $this->generateQr($booking, $integration),
                ApprovalIntegrationComponent::Drive => $this->createDriveFolder($booking, $integration),
                ApprovalIntegrationComponent::Notion => $this->createNotionPage($booking, $integration),
                ApprovalIntegrationComponent::ApprovalEmail => $this->sendApprovalEmail($booking, $integration),
            };

            $this->markSucceeded($integration, $component);
        } catch (\Throwable $throwable) {
            $this->markFailed($integration, $component, $throwable->getMessage());
        }

        return $integration->fresh() ?? $integration;
    }

    private function generateQr(Booking $booking, ApprovalIntegration $integration): void
    {
        $token = $this->readOrCreateQrToken($integration);
        $png = $this->qrCodeService->generatePng($token);
        $path = $integration->qr_image_path ?: 'approval-qr/'.$booking->booking_number.'.png';

        Storage::disk((string) config('phase7.storage_disk'))->put($path, $png, [
            'ContentType' => 'image/png',
        ]);

        $integration->forceFill([
            'qr_image_path' => $path,
        ])->save();
    }

    private function createDriveFolder(Booking $booking, ApprovalIntegration $integration): void
    {
        $folder = $this->googleDriveClient->ensureFolder($booking->booking_number);

        $integration->forceFill([
            'drive_external_id' => $folder['id'],
            'drive_url' => $folder['url'],
        ])->save();
    }

    private function createNotionPage(Booking $booking, ApprovalIntegration $integration): void
    {
        $page = $this->notionClient->ensureBookingPage(
            $booking->booking_number,
            $this->notionLines($booking, $integration),
            $integration->notion_external_id,
        );

        $integration->forceFill([
            'notion_external_id' => $page['id'],
            'notion_url' => $page['url'],
        ])->save();
    }

    private function sendApprovalEmail(Booking $booking, ApprovalIntegration $integration): void
    {
        if (
            $integration->qr_status !== ApprovalIntegrationStatus::Succeeded
            || $integration->drive_status !== ApprovalIntegrationStatus::Succeeded
            || $integration->notion_status !== ApprovalIntegrationStatus::Succeeded
        ) {
            throw new \RuntimeException('Email approval menunggu QR, Google Drive, dan Notion selesai.');
        }

        $qrDisk = Storage::disk((string) config('phase7.storage_disk'));
        $qrContent = $integration->qr_image_path
            ? $qrDisk->get($integration->qr_image_path)
            : null;

        if (! is_string($qrContent) || $qrContent === '') {
            throw new \RuntimeException('File QR belum tersedia.');
        }

        $this->approvalEmailService->sendApprovedEmail($booking, $integration, $qrContent);

        $integration->forceFill([
            'approval_email_sent_at' => now(),
        ])->save();
    }

    private function markProcessing(ApprovalIntegration $integration, ApprovalIntegrationComponent $component): void
    {
        $field = $component->value.'_status';
        $errorField = $component->value.'_error';

        $integration->forceFill([
            $field => ApprovalIntegrationStatus::Processing,
            $errorField => null,
        ])->save();
    }

    private function markSucceeded(ApprovalIntegration $integration, ApprovalIntegrationComponent $component): void
    {
        $field = $component->value.'_status';
        $errorField = $component->value.'_error';

        $integration->forceFill([
            $field => ApprovalIntegrationStatus::Succeeded,
            $errorField => null,
            'last_error' => $this->latestError($integration->fresh() ?? $integration),
        ])->save();
    }

    private function markFailed(
        ApprovalIntegration $integration,
        ApprovalIntegrationComponent $component,
        string $message,
    ): void {
        $field = $component->value.'_status';
        $errorField = $component->value.'_error';
        $safeMessage = Str::limit($message, 500);

        $integration->forceFill([
            $field => ApprovalIntegrationStatus::Failed,
            $errorField => $safeMessage,
            'last_error' => $safeMessage,
        ])->save();
    }

    private function readOrCreateQrToken(ApprovalIntegration $integration): string
    {
        if (filled($integration->qr_token_encrypted)) {
            return Crypt::decryptString((string) $integration->qr_token_encrypted);
        }

        $token = Str::lower(bin2hex(random_bytes(32)));

        $integration->forceFill([
            'qr_token_hash' => hash('sha256', $token),
            'qr_token_encrypted' => Crypt::encryptString($token),
        ])->save();

        return $token;
    }

    /**
     * @return array<int, string>
     */
    private function notionLines(Booking $booking, ApprovalIntegration $integration): array
    {
        $slotLines = $this->slotLines($booking);

        return array_merge([
            'Nomor booking: '.$booking->booking_number,
            'Nama customer: '.$booking->customer_name,
            'Paket: '.$booking->package_name_snapshot,
            'Jumlah hadir: '.$booking->attendee_count,
            'Google Drive: '.($integration->drive_url ?? '-'),
        ], $slotLines);
    }

    /**
     * @return array<int, string>
     */
    public function slotLines(Booking $booking): array
    {
        $packageCode = PackageCode::from($booking->package_code_snapshot);
        $tableCodes = $booking->tableSlots->pluck('code')->filter()->implode(', ');
        $incenseNumbers = $booking->incenseSlots->pluck('number')->filter()->implode(', ');

        return match ($packageCode) {
            PackageCode::Prayer => [
                'Nomor meja: '.($tableCodes !== '' ? $tableCodes : '-'),
            ],
            PackageCode::Incense => [
                'Nomor hio: '.($incenseNumbers !== '' ? $incenseNumbers : '-'),
            ],
            PackageCode::Combo => [
                'Nomor meja: '.($tableCodes !== '' ? $tableCodes : '-'),
                'Nomor hio: '.($incenseNumbers !== '' ? $incenseNumbers : '-'),
            ],
        };
    }

    private function latestError(ApprovalIntegration $integration): ?string
    {
        foreach ([
            $integration->approval_email_error,
            $integration->notion_error,
            $integration->drive_error,
            $integration->qr_error,
        ] as $message) {
            if (filled($message)) {
                return $message;
            }
        }

        return null;
    }

    private function loadBooking(Booking $booking): Booking
    {
        return $booking->fresh([
            'payment',
            'tableSlots',
            'incenseSlots',
            'approvalIntegration',
        ]) ?? $booking;
    }
}
