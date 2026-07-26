<?php

use VentureDrake\LaravelCrm\Models\UserInvitation;

test('invitation uses prefixed table name', function () {
    expect((new UserInvitation)->getTable())->toBe('crm_user_invitations');
});

test('invitation uses code as its route key', function () {
    expect((new UserInvitation)->getRouteKeyName())->toBe('code');
});

test('creating an invitation stamps a uuid external_id and a 64-char code', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
    ]);

    expect($invitation->external_id)
        ->toBeString()
        ->and($invitation->external_id)
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i')
        ->and(strlen($invitation->code))->toBe(64);
});

test('a pre-supplied code is preserved by the observer', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'code' => str_repeat('a', 64),
    ]);

    expect($invitation->code)->toBe(str_repeat('a', 64));
});

test('isPending is true when neither accepted nor expired', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'expires_at' => now()->addDay(),
    ]);

    expect($invitation->isPending())->toBeTrue()
        ->and($invitation->isExpired())->toBeFalse()
        ->and($invitation->isAccepted())->toBeFalse()
        ->and($invitation->isValid())->toBeTrue();
});

test('isExpired is true when expires_at is in the past', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'expires_at' => now()->subMinute(),
    ]);

    expect($invitation->isExpired())->toBeTrue()
        ->and($invitation->isPending())->toBeFalse()
        ->and($invitation->isValid())->toBeFalse();
});

test('isAccepted is true when accepted_at is set', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'expires_at' => now()->addDay(),
        'accepted_at' => now(),
    ]);

    expect($invitation->isAccepted())->toBeTrue()
        ->and($invitation->isPending())->toBeFalse()
        ->and($invitation->isValid())->toBeFalse();
});
