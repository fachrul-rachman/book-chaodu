<?php

declare(strict_types=1);

use App\Enums\ApprovalIntegrationComponent;
use App\Enums\ApprovalIntegrationStatus;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Mail\BookingApprovedMail;
use App\Models\ApprovalIntegration;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ApprovalEmailService;
use App\Services\GoogleDriveClient;
use App\Services\NotionClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    config()->set('phase7.storage_disk', 'approval-files');
    Storage::fake('booking-private');
    Storage::fake('prayer-paper-files');
    Storage::fake('approval-files');
    Mail::fake();

    $this->seed();
    seedApprovalVirtualAccounts();
});

function seedApprovalVirtualAccounts(): void
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

function activateApprovalPackage(PackageCode $code, string $price = '2000000'): Package
{
    $package = Package::query()->where('code', $code)->firstOrFail();
    $package->forceFill([
        'price' => $price,
        'image_path' => 'packages/test.jpg',
        'is_active' => true,
    ])->save();

    return $package->fresh() ?? $package;
}

function approvalBookingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'idempotency_key' => 'approval-module7-key-1',
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

function createApprovalPendingBooking(array $overrides = []): Booking
{
    $payload = approvalBookingPayload($overrides);
    activateApprovalPackage(PackageCode::from($payload['package_code']));

    test()->post(route('api.public.bookings.store'), $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    return Booking::query()->latest('id')->firstOrFail();
}

it('runs approval integrations after booking is approved', function () {
    $calls = [
        'drive' => 0,
        'notion' => 0,
        'email' => 0,
    ];

    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldReceive('ensureFolder')
        ->once()
        ->andReturnUsing(function (string $bookingNumber) use (&$calls): array {
            $calls['drive']++;

            return [
                'id' => 'drive-'.$bookingNumber,
                'url' => 'https://drive.test/'.$bookingNumber,
            ];
        });

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldReceive('ensureBookingPage')
        ->once()
        ->andReturnUsing(function (string $bookingNumber) use (&$calls): array {
            $calls['notion']++;

            return [
                'id' => 'notion-'.$bookingNumber,
                'url' => 'https://notion.test/'.$bookingNumber,
            ];
        });

    $emailService = Mockery::mock(ApprovalEmailService::class);
    $emailService->shouldReceive('sendApprovedEmail')
        ->once()
        ->andReturnUsing(function () use (&$calls): void {
            $calls['email']++;
        });

    app()->instance(GoogleDriveClient::class, $driveClient);
    app()->instance(NotionClient::class, $notionClient);
    app()->instance(ApprovalEmailService::class, $emailService);

    $booking = createApprovalPendingBooking();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.approve', $booking))
        ->assertRedirect();

    $booking->refresh();
    $integration = ApprovalIntegration::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Approved)
        ->and($integration->qr_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($integration->drive_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($integration->notion_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($integration->approval_email_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($integration->drive_external_id)->toBe('drive-'.$booking->booking_number)
        ->and($integration->notion_external_id)->toBe('notion-'.$booking->booking_number)
        ->and($integration->approval_email_sent_at)->not->toBeNull()
        ->and($calls['drive'])->toBe(1)
        ->and($calls['notion'])->toBe(1)
        ->and($calls['email'])->toBe(1);

    Storage::disk('approval-files')->assertExists('approval-qr/'.$booking->booking_number.'.png');
});

it('does not rerun approval effects that already succeeded', function () {
    $calls = [
        'drive' => 0,
        'notion' => 0,
        'email' => 0,
    ];

    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldReceive('ensureFolder')
        ->once()
        ->andReturnUsing(function (string $bookingNumber) use (&$calls): array {
            $calls['drive']++;

            return [
                'id' => 'drive-'.$bookingNumber,
                'url' => 'https://drive.test/'.$bookingNumber,
            ];
        });

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldReceive('ensureBookingPage')
        ->once()
        ->andReturnUsing(function (string $bookingNumber) use (&$calls): array {
            $calls['notion']++;

            return [
                'id' => 'notion-'.$bookingNumber,
                'url' => 'https://notion.test/'.$bookingNumber,
            ];
        });

    $emailService = Mockery::mock(ApprovalEmailService::class);
    $emailService->shouldReceive('sendApprovedEmail')
        ->once()
        ->andReturnUsing(function () use (&$calls): void {
            $calls['email']++;
        });

    app()->instance(GoogleDriveClient::class, $driveClient);
    app()->instance(NotionClient::class, $notionClient);
    app()->instance(ApprovalEmailService::class, $emailService);

    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module7-key-2',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();

    expect($calls['drive'])->toBe(1)
        ->and($calls['notion'])->toBe(1)
        ->and($calls['email'])->toBe(1);
});

it('keeps booking approved when integration fails and allows retry per component', function () {
    $state = [
        'drive_fail' => true,
        'email_calls' => 0,
    ];

    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldReceive('ensureFolder')
        ->twice()
        ->andReturnUsing(function (string $bookingNumber) use (&$state): array {
            if ($state['drive_fail']) {
                throw new RuntimeException('Drive sedang gagal.');
            }

            return [
                'id' => 'drive-'.$bookingNumber,
                'url' => 'https://drive.test/'.$bookingNumber,
            ];
        });

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldReceive('ensureBookingPage')
        ->once()
        ->andReturnUsing(fn (string $bookingNumber): array => [
            'id' => 'notion-'.$bookingNumber,
            'url' => 'https://notion.test/'.$bookingNumber,
        ]);

    $emailService = Mockery::mock(ApprovalEmailService::class);
    $emailService->shouldReceive('sendApprovedEmail')
        ->once()
        ->andReturnUsing(function () use (&$state): void {
            $state['email_calls']++;
        });

    app()->instance(GoogleDriveClient::class, $driveClient);
    app()->instance(NotionClient::class, $notionClient);
    app()->instance(ApprovalEmailService::class, $emailService);

    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module7-key-3',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();

    $integration = ApprovalIntegration::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($booking->fresh()?->status)->toBe(BookingStatus::Approved)
        ->and($integration->drive_status)->toBe(ApprovalIntegrationStatus::Failed)
        ->and($integration->approval_email_status)->toBe(ApprovalIntegrationStatus::Failed);

    $state['drive_fail'] = false;

    $this->actingAs($admin)->post(route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::Drive->value]))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::ApprovalEmail->value]))->assertRedirect();

    $integration->refresh();

    expect($integration->drive_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($integration->approval_email_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($state['email_calls'])->toBe(1);
});

it('reuses the same qr token when qr is retried', function () {
    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldReceive('ensureFolder')
        ->once()
        ->andReturnUsing(fn (string $bookingNumber): array => [
            'id' => 'drive-'.$bookingNumber,
            'url' => 'https://drive.test/'.$bookingNumber,
        ]);
    app()->instance(GoogleDriveClient::class, $driveClient);

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldReceive('ensureBookingPage')
        ->once()
        ->andReturnUsing(fn (string $bookingNumber): array => [
            'id' => 'notion-'.$bookingNumber,
            'url' => 'https://notion.test/'.$bookingNumber,
        ]);
    app()->instance(NotionClient::class, $notionClient);

    $emailService = Mockery::mock(ApprovalEmailService::class);
    $emailService->shouldReceive('sendApprovedEmail')->once();
    app()->instance(ApprovalEmailService::class, $emailService);

    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module7-key-4',
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();

    $integration = ApprovalIntegration::query()->where('booking_id', $booking->id)->firstOrFail();
    $hashBefore = $integration->qr_token_hash;

    Storage::disk('approval-files')->delete((string) $integration->qr_image_path);

    $this->actingAs($admin)->post(route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::Qr->value]))->assertRedirect();

    $integration->refresh();

    expect($integration->qr_token_hash)->toBe($hashBefore)
        ->and($integration->qr_status)->toBe(ApprovalIntegrationStatus::Succeeded);
});

it('sends approval email through laravel mailer', function () {
    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module7-key-5',
    ])->fresh(['tableSlots', 'incenseSlots']) ?? Booking::query()->latest('id')->firstOrFail();

    $integration = ApprovalIntegration::query()->create([
        'booking_id' => $booking->id,
        'qr_status' => ApprovalIntegrationStatus::Succeeded,
        'drive_status' => ApprovalIntegrationStatus::Succeeded,
        'notion_status' => ApprovalIntegrationStatus::Succeeded,
        'approval_email_status' => ApprovalIntegrationStatus::Pending,
        'drive_url' => 'https://drive.test/'.$booking->booking_number,
        'notion_url' => 'https://notion.test/'.$booking->booking_number,
    ]);

    app(ApprovalEmailService::class)->sendApprovedEmail($booking, $integration, 'png-content');

    Mail::assertSent(BookingApprovedMail::class, function (BookingApprovedMail $mail) use ($booking): bool {
        return $mail->booking->is($booking);
    });
});

it('renders approval email with the booking template', function () {
    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module7-key-6',
    ])->fresh(['tableSlots', 'incenseSlots']) ?? Booking::query()->latest('id')->firstOrFail();

    $integration = ApprovalIntegration::query()->create([
        'booking_id' => $booking->id,
        'qr_status' => ApprovalIntegrationStatus::Succeeded,
        'drive_status' => ApprovalIntegrationStatus::Succeeded,
        'notion_status' => ApprovalIntegrationStatus::Succeeded,
        'approval_email_status' => ApprovalIntegrationStatus::Pending,
        'drive_url' => 'https://drive.test/'.$booking->booking_number,
        'notion_url' => 'https://notion.test/'.$booking->booking_number,
    ]);

    $html = (new BookingApprovedMail($booking, $integration, 'png-content'))->render();

    expect($html)->toContain('Detail booking')
        ->toContain($booking->booking_number)
        ->toContain('Buka Google Drive')
        ->toContain('Buka Notion')
        ->toContain('Pembayaran terverifikasi');
});
