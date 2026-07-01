<?php

use App\Models\User;

it('shows the Indonesian home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('manifest.webmanifest')
        ->assertSee('Chao Du Booking');
});

it('allows an admin to log in from the single login page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@chaodu.test',
        'password' => 'rahasia123',
    ]);

    $this->post('/masuk', [
        'email' => $admin->email,
        'password' => 'rahasia123',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});

it('allows a checker to log in from the single login page', function () {
    $checker = User::factory()->checker()->create([
        'email' => 'checker@chaodu.test',
        'password' => 'rahasia123',
    ]);

    $this->post('/masuk', [
        'email' => $checker->email,
        'password' => 'rahasia123',
    ])->assertRedirect(route('checker.dashboard'));

    $this->assertAuthenticatedAs($checker);
});

it('blocks checker users from the admin dashboard', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('blocks admin users from the checker dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('checker.dashboard'))
        ->assertForbidden();
});
