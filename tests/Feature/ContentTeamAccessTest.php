<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('allows active content team users to log in and redirects them to their dashboard', function () {
    $contentTeam = User::factory()->contentTeam()->create([
        'email' => 'content@chaodu.test',
        'password' => 'rahasia123',
    ]);

    $this->post('/masuk', [
        'email' => $contentTeam->email,
        'password' => 'rahasia123',
    ])->assertRedirect(route('content.dashboard'));

    $this->assertAuthenticatedAs($contentTeam);
});

it('shows the content team dashboard only to content team users', function () {
    $contentTeam = User::factory()->contentTeam()->create();

    $this->actingAs($contentTeam)
        ->get(route('content.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('content/dashboard')
            ->where('auth.user.id', $contentTeam->id)
            ->where('auth.user.role', 'CONTENT_TEAM'));
});

it('redirects guests from the content team dashboard to login', function () {
    $this->get('/content')
        ->assertRedirect(route('login'));
});

it('blocks other staff roles from the content team dashboard', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)
        ->get('/content')
        ->assertForbidden();
})->with(['admin', 'checker', 'printer']);

it('blocks content team users from other staff dashboards', function (string $routeName) {
    $contentTeam = User::factory()->contentTeam()->create();

    $this->actingAs($contentTeam)
        ->get(route($routeName))
        ->assertForbidden();
})->with(['admin.dashboard', 'checker.dashboard', 'printer.dashboard']);

it('does not allow inactive content team users to log in', function () {
    $contentTeam = User::factory()->contentTeam()->create([
        'email' => 'content-nonaktif@chaodu.test',
        'password' => 'rahasia123',
        'is_active' => false,
    ]);

    $this->post('/masuk', [
        'email' => $contentTeam->email,
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
