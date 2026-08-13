<?php

declare(strict_types=1);

use App\Enums\ApprovalIntegrationComponent;
use App\Enums\ApprovalIntegrationStatus;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Mail\BookingApprovedMail;
use App\Models\ApprovalIntegration;
use App\Models\Booking;
use App\Models\GalleryMedia;
use App\Models\Package;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ApprovalEmailService;
use App\Services\GoogleDriveClient;
use App\Services\NotionClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('phase3.private_upload_disk', 'booking-private');
    config()->set('phase4.private_upload_disk', 'booking-private');
    config()->set('phase5.storage_disk', 'prayer-paper-files');
    config()->set('phase5.enabled', true);
    config()->set('phase7.storage_disk', 'approval-files');
    config()->set('gallery.storage_disk', 'approval-gallery-files');
    Storage::fake('booking-private');
    Storage::fake('prayer-paper-files');
    Storage::fake('approval-files');
    Storage::fake('approval-gallery-files');
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
        'vegetarian_quantity' => '0',
        'non_vegetarian_quantity' => '2',
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

it('runs QR and album approval email without creating Drive or Notion resources', function () {
    $calls = [
        'email' => 0,
    ];

    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldNotReceive('ensureFolder');

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldNotReceive('ensureBookingPage');

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
        ->and($integration->drive_status->value)->toBe('SKIPPED')
        ->and($integration->notion_status->value)->toBe('SKIPPED')
        ->and($integration->approval_email_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($integration->drive_external_id)->toBeNull()
        ->and($integration->notion_external_id)->toBeNull()
        ->and($integration->approval_email_sent_at)->not->toBeNull()
        ->and($calls['email'])->toBe(1);

    Storage::disk('approval-files')->assertExists('approval-qr/'.$booking->booking_number.'.png');
    $paperMedia = GalleryMedia::query()->where('booking_id', $booking->id)->whereNotNull('source_prayer_paper_id')->firstOrFail();
    expect($paperMedia->source_prayer_paper_id)->not->toBeNull()
        ->and($paperMedia->caption)->toBe('Kertas Doa');
    Storage::disk('approval-gallery-files')->assertExists($paperMedia->original_path);
    $layoutMedia = GalleryMedia::query()->where('source_table_layout_booking_id', $booking->id)->firstOrFail();
    expect($layoutMedia->caption)->toStartWith('Denah Meja Anda: ');
    Storage::disk('approval-gallery-files')->assertExists($layoutMedia->original_path);
});

it('does not rerun approval effects that already succeeded', function () {
    $calls = [
        'email' => 0,
    ];

    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldNotReceive('ensureFolder');

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldNotReceive('ensureBookingPage');

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

    expect($calls['email'])->toBe(1);
});

it('keeps booking approved when email fails and allows retrying only active components', function () {
    $state = [
        'email_fail' => true,
        'email_calls' => 0,
    ];

    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldNotReceive('ensureFolder');

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldNotReceive('ensureBookingPage');

    $emailService = Mockery::mock(ApprovalEmailService::class);
    $emailService->shouldReceive('sendApprovedEmail')
        ->twice()
        ->andReturnUsing(function () use (&$state): void {
            $state['email_calls']++;

            if ($state['email_fail']) {
                throw new RuntimeException('Email sedang gagal.');
            }
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
        ->and($integration->drive_status->value)->toBe('SKIPPED')
        ->and($integration->notion_status->value)->toBe('SKIPPED')
        ->and($integration->approval_email_status)->toBe(ApprovalIntegrationStatus::Failed);

    $this->actingAs($admin)
        ->post(route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::Drive->value]))
        ->assertNotFound();

    $state['email_fail'] = false;
    $this->actingAs($admin)->post(route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::ApprovalEmail->value]))->assertRedirect();

    $integration->refresh();

    expect($integration->approval_email_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($state['email_calls'])->toBe(2);
});

it('reuses the same qr token when qr is retried', function () {
    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldNotReceive('ensureFolder');
    app()->instance(GoogleDriveClient::class, $driveClient);

    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldNotReceive('ensureBookingPage');
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

    app(ApprovalEmailService::class)->sendApprovedEmail($booking, 'png-content');

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

    $html = (new BookingApprovedMail(
        $booking,
        'png-content',
        route('public.gallery.show', ['bookingNumber' => $booking->booking_number]),
    ))->render();

    expect($html)->toContain('Detail booking')
        ->toContain($booking->booking_number)
        ->toContain('Buka album foto dan video')
        ->toContain(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
        ->not->toContain('Google Drive')
        ->not->toContain('Notion')
        ->toContain('Pembayaran terverifikasi');
});

it('shows the album URL in Admin without Drive or Notion retry actions', function () {
    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module9-admin-album',
    ]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.album_url', route('public.gallery.show', [
                'bookingNumber' => $booking->booking_number,
            ]))
            ->missing('booking.approval_integration.drive_status')
            ->missing('booking.approval_integration.notion_status')
            ->has('booking.approval_integration.retry_urls', 2)
            ->where('booking.approval_integration.retry_urls.qr', route('admin.bookings.integrations.retry', [
                $booking,
                ApprovalIntegrationComponent::Qr->value,
            ]))
            ->where('booking.approval_integration.retry_urls.approval_email', route('admin.bookings.integrations.retry', [
                $booking,
                ApprovalIntegrationComponent::ApprovalEmail->value,
            ])));
});

it('preserves legacy Drive and Notion identifiers while an old booking gains an album URL', function () {
    $booking = createApprovalPendingBooking([
        'idempotency_key' => 'approval-module9-legacy-data',
    ]);
    $integration = ApprovalIntegration::query()->create([
        'booking_id' => $booking->id,
        'qr_status' => ApprovalIntegrationStatus::Pending,
        'drive_status' => ApprovalIntegrationStatus::Succeeded,
        'drive_external_id' => 'legacy-drive-id',
        'drive_url' => 'https://drive.test/legacy-folder',
        'notion_status' => ApprovalIntegrationStatus::Succeeded,
        'notion_external_id' => 'legacy-notion-id',
        'notion_url' => 'https://notion.test/legacy-page',
        'approval_email_status' => ApprovalIntegrationStatus::Pending,
    ]);
    $driveClient = Mockery::mock(GoogleDriveClient::class);
    $driveClient->shouldNotReceive('ensureFolder');
    $notionClient = Mockery::mock(NotionClient::class);
    $notionClient->shouldNotReceive('ensureBookingPage');
    app()->instance(GoogleDriveClient::class, $driveClient);
    app()->instance(NotionClient::class, $notionClient);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.bookings.approve', $booking))->assertRedirect();

    expect($integration->fresh()?->drive_external_id)->toBe('legacy-drive-id')
        ->and($integration->fresh()?->drive_url)->toBe('https://drive.test/legacy-folder')
        ->and($integration->fresh()?->notion_external_id)->toBe('legacy-notion-id')
        ->and($integration->fresh()?->notion_url)->toBe('https://notion.test/legacy-page');

    $this->get(route('public.gallery.show', ['bookingNumber' => $booking->booking_number]))
        ->assertOk();
});
