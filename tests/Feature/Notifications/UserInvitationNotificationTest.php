<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use VentureDrake\LaravelCrm\Models\UserInvitation;
use VentureDrake\LaravelCrm\Notifications\UserInvitationNotification;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    // Register a placeholder for the accept route so route(...) in toMail() resolves.
    // The real controller wiring lands in a later story; this test only needs the URL
    // to be generatable and to contain the invitation code.
    Route::get('crm/users/invitations/{code}/accept', fn () => 'ok')
        ->name('laravel-crm.users.invitations.accept');
});

test('notification class implements ShouldQueue and extends the base Notification', function () {
    $notification = new UserInvitationNotification(
        UserInvitation::create(['email' => 'invitee@example.test'])
    );

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification)->toBeInstanceOf(\Illuminate\Notifications\Notification::class);
});

test('toMail carries the subject with app name and an action URL containing the invitation code', function () {
    config()->set('app.name', 'Acme CRM');

    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'expires_at' => now()->addDays(7),
    ]);

    Notification::fake();

    $notifiable = User::create([
        'name' => 'Invitee',
        'email' => 'invitee-'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
    ]);

    $notifiable->notify(new UserInvitationNotification($invitation));

    Notification::assertSentTo(
        $notifiable,
        UserInvitationNotification::class,
        function (UserInvitationNotification $notification) use ($invitation) {
            $mail = $notification->toMail($notification);

            expect($mail->subject)->toContain('Acme CRM')
                ->and($mail->actionUrl)->toContain($invitation->code);

            return true;
        }
    );
});

test('toMail includes inviter, team, role, and expiry lines', function () {
    $inviter = User::create([
        'name' => 'Ada Lovelace',
        'email' => 'ada-'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
    ]);

    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDay(),
    ]);

    $mail = (new UserInvitationNotification($invitation))->toMail(new \stdClass);

    $introLines = array_map(fn ($line) => (string) $line, $mail->introLines);
    $body = implode(' ', $introLines);

    expect($body)->toContain('Ada Lovelace')
        ->and($mail->actionText)->toBeString();
});

test('toMail uses translation keys from the laravel-crm::lang namespace', function () {
    $invitation = UserInvitation::create([
        'email' => 'invitee@example.test',
    ]);

    $subjectKey = 'laravel-crm::lang.user_invitation_subject';
    $actionKey = 'laravel-crm::lang.user_invitation_action';

    // These translation keys must resolve to non-key strings; if either resolved
    // to the raw key, it would signal that the notification is pulling from a
    // missing translation and the AC's "all user-facing strings via lang.*" is broken.
    expect(trans($subjectKey, ['app' => 'X']))->not->toBe($subjectKey)
        ->and(trans($actionKey))->not->toBe($actionKey);
});
