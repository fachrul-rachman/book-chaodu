<?php

declare(strict_types=1);

use App\Enums\ApprovalIntegrationStatus;
use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\SlotStatus;
use App\Mail\BookingApprovedMail;
use App\Models\Booking;
use App\Models\IncenseSlot;
use App\Models\Package;
use App\Models\TableSlot;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('phase5.enabled', false);
    config()->set('phase7.storage_disk', 'checker-qr');
    config()->set('gallery.storage_disk', 'checker-gallery');
    Storage::fake('checker-qr');
    Storage::fake('checker-gallery');
    Mail::fake();
    $this->seed();
    Package::query()->update(['is_active' => true, 'price' => 100000]);
});

function checkerManualPayload(array $overrides = []): array
{
    $tableId = TableSlot::query()
        ->where('code', 'A38')
        ->value('id');
    $incenseId = IncenseSlot::query()
        ->where('number', 3)
        ->value('id');

    return array_replace_recursive([
        'idempotency_key' => 'checker-manual-key-1',
        'customer_name' => 'Budi Santoso',
        'customer_phone_local' => '81234567890',
        'customer_email' => 'budi@example.com',
        'referral_source' => 'WEBSITE',
        'agent_name' => null,
        'package_code' => 'COMBO',
        'table_slot_id' => $tableId,
        'incense_slot_id' => $incenseId,
        'deceased_names' => [
            [
                'position' => 1,
                'indonesian_name' => null,
                'mandarin_name' => "李樹華\n劉梅清",
            ],
        ],
        'incense_name' => [
            'position' => 1,
            'indonesian_name' => null,
            'mandarin_name' => "李鴻銘\n闔家",
        ],
    ], $overrides);
}

it('shows only available dropdown options to a checker', function () {
    $checker = User::factory()->checker()->create();
    TableSlot::query()->where('code', 'A38')->update(['status' => SlotStatus::Assigned]);
    IncenseSlot::query()->where('number', 3)->update(['status' => SlotStatus::Assigned]);

    $this->actingAs($checker)
        ->get(route('checker.manual-bookings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('checker/manual-bookings/create')
            ->where('packages.0.code', 'PRAYER')
            ->where('table_slots', fn ($slots): bool => $slots->doesntContain('code', 'A18')
                && $slots->doesntContain('code', 'A38'))
            ->where('incense_slots', fn ($slots): bool => $slots->doesntContain('number', 1)
                && $slots->doesntContain('number', 3)));
});

it('creates a directly approved checker booking with the selected table and incense number', function () {
    $checker = User::factory()->checker()->create();
    $payload = checkerManualPayload();

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), $payload)
        ->assertRedirect(route('checker.dashboard'));

    $booking = Booking::query()->where('idempotency_key', 'checker-manual-key-1')->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Approved)
        ->and($booking->referral_source)->toBe('WEBSITE')
        ->and($booking->agent_name)->toBeNull()
        ->and($booking->is_checker_manual)->toBeTrue()
        ->and($booking->approved_by)->toBe($checker->id)
        ->and($booking->attendee_count)->toBeNull()
        ->and($booking->payment)->toBeNull()
        ->and($booking->meal)->toBeNull()
        ->and($booking->tableSlots()->value('id'))->toBe($payload['table_slot_id'])
        ->and($booking->incenseSlots()->value('id'))->toBe($payload['incense_slot_id'])
        ->and($booking->tableSlots()->value('status'))->toBe(SlotStatus::Assigned)
        ->and($booking->incenseSlots()->value('status'))->toBe(SlotStatus::Assigned)
        ->and($booking->names()->where('category', BookingNameCategory::Deceased)->value('mandarin_name'))
        ->toBe("李樹華\n劉梅清")
        ->and($booking->approvalIntegration?->qr_status)->toBe(ApprovalIntegrationStatus::Succeeded)
        ->and($booking->approvalIntegration?->approval_email_status)->toBe(ApprovalIntegrationStatus::Succeeded);

    Mail::assertSent(BookingApprovedMail::class, fn (BookingApprovedMail $mail): bool => $mail->hasTo('budi@example.com'));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('booking.source_label', 'Website'));
});

it('stores a trimmed agent name when manual booking source is agent', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), checkerManualPayload([
            'idempotency_key' => 'checker-manual-agent',
            'referral_source' => 'AGENT',
            'agent_name' => '  Budi Agent  ',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('checker.dashboard'));

    $booking = Booking::query()->where('idempotency_key', 'checker-manual-agent')->firstOrFail();

    expect($booking->referral_source)->toBe('AGENT')
        ->and($booking->agent_name)->toBe('Budi Agent')
        ->and($booking->is_checker_manual)->toBeTrue();
});

it('requires a valid source and agent name for manual agent bookings', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), checkerManualPayload([
            'idempotency_key' => 'checker-manual-no-source',
            'referral_source' => '',
        ]))
        ->assertSessionHasErrors('referral_source');

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), checkerManualPayload([
            'idempotency_key' => 'checker-manual-agent-without-name',
            'referral_source' => 'AGENT',
            'agent_name' => '',
        ]))
        ->assertSessionHasErrors('agent_name');

    expect(Booking::query()->whereIn('idempotency_key', [
        'checker-manual-no-source',
        'checker-manual-agent-without-name',
    ])->exists())->toBeFalse();
});

it('requires only the slot types used by the selected package', function () {
    $checker = User::factory()->checker()->create();

    $prayerPayload = checkerManualPayload([
        'idempotency_key' => 'checker-prayer-only',
        'package_code' => 'PRAYER',
        'incense_slot_id' => null,
        'incense_name' => null,
    ]);

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), $prayerPayload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('checker.dashboard'));

    $incensePayload = checkerManualPayload([
        'idempotency_key' => 'checker-incense-only',
        'package_code' => 'INCENSE',
        'table_slot_id' => null,
        'deceased_names' => [],
        'incense_slot_id' => IncenseSlot::query()->where('number', 5)->value('id'),
    ]);

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), $incensePayload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('checker.dashboard'));
});

it('rejects a slot that was taken before submit without leaving a booking behind', function () {
    $checker = User::factory()->checker()->create();
    $payload = checkerManualPayload();

    TableSlot::query()->whereKey($payload['table_slot_id'])->update([
        'status' => SlotStatus::Assigned,
    ]);

    $this->actingAs($checker)
        ->post(route('checker.manual-bookings.store'), $payload)
        ->assertSessionHasErrors('table_slot_id');

    expect(Booking::query()->where('idempotency_key', 'checker-manual-key-1')->exists())->toBeFalse()
        ->and(IncenseSlot::query()->whereKey($payload['incense_slot_id'])->value('booking_id'))->toBeNull();
});

it('does not let an admin use the checker manual booking routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('checker.manual-bookings.create'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('checker.manual-bookings.store'), checkerManualPayload())
        ->assertForbidden();
});
