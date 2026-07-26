<?php

use Illuminate\Support\Facades\Route;
use VentureDrake\LaravelCrm\Models\UserInvitation;

test('accept-invitation routes are registered with the AC-named names and are NOT behind auth.laravel-crm', function () {
    $show = Route::getRoutes()->getByName('laravel-crm.users.invitations.accept');
    $post = Route::getRoutes()->getByName('laravel-crm.users.invitations.accept.store');

    expect($show)->not->toBeNull()
        ->and($show->methods())->toContain('GET')
        ->and($show->uri())->toBe('crm/users/invitations/{code}/accept')
        ->and($show->middleware())->not->toContain('auth.laravel-crm');

    expect($post)->not->toBeNull()
        ->and($post->methods())->toContain('POST')
        ->and($post->uri())->toBe('crm/users/invitations/{code}/accept')
        ->and($post->middleware())->not->toContain('auth.laravel-crm');
});

test('visiting the show route with a missing code renders the invalid-invite view without errors', function () {
    $response = $this->get('/crm/users/invitations/does-not-exist/accept');

    $response->assertStatus(404);
    $response->assertViewIs('laravel-crm::users.invalid-invite');
});

test('visiting the show route with an expired invitation renders the invalid-invite view', function () {
    $invitation = UserInvitation::create([
        'email' => 'expired@example.test',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertStatus(404);
    $response->assertViewIs('laravel-crm::users.invalid-invite');
});

test('visiting the show route with an already-accepted invitation renders the invalid-invite view', function () {
    $invitation = UserInvitation::create([
        'email' => 'accepted@example.test',
        'expires_at' => now()->addWeek(),
        'accepted_at' => now(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertStatus(404);
    $response->assertViewIs('laravel-crm::users.invalid-invite');
});

test('visiting the show route with a valid invitation renders the accept-invite placeholder view while logged out', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertStatus(200);
    $response->assertViewIs('laravel-crm::users.accept-invite');
});
