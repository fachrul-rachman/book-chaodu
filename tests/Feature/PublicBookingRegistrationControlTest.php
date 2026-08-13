<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\User;

beforeEach(function () {
    $this->seed();
});

it('lets an admin close and reopen public booking registration', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.registration.update'), ['is_closed' => true])
        ->assertRedirect(route('admin.dashboard'));

    expect(AppSetting::query()->where('key', 'public_booking_closed')->value('value'))->toBe('1');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('registration.is_closed', true));

    $this->actingAs($admin)
        ->put(route('admin.registration.update'), ['is_closed' => false])
        ->assertRedirect(route('admin.dashboard'));

    expect(AppSetting::query()->where('key', 'public_booking_closed')->value('value'))->toBe('0');
});

it('does not let a checker change public booking registration', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->put(route('admin.registration.update'), ['is_closed' => true])
        ->assertForbidden();

    expect(AppSetting::query()->where('key', 'public_booking_closed')->exists())->toBeFalse();
});

it('shows the closed state on the public form and blocks new booking submissions', function () {
    AppSetting::putMany(['public_booking_closed' => '1']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/booking')
            ->where('registration.is_closed', true));

    $this->postJson(route('api.public.bookings.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('booking');

    expect(Booking::query()->count())->toBe(0);
});

it('blocks new virtual account reservations while registration is closed', function () {
    AppSetting::putMany(['public_booking_closed' => '1']);

    $this->postJson(route('api.public.virtual-accounts.reserve'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('booking');
});
