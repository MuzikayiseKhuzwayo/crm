<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use VentureDrake\LaravelCrm\Livewire\Users\UserIndex;
use VentureDrake\LaravelCrm\Models\UserInvitation;
use VentureDrake\LaravelCrm\Notifications\UserInvitationNotification;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

// UserIndex references App\Models\User via `use App\Models\User;`. The core
// CRM's test bootstrap only aliases App\User, so alias App\Models\User too
// so the class resolves when instantiated. Guarded to be idempotent across
// test runs sharing a single PHP process.
if (! class_exists('App\\Models\\User')) {
    class_alias(User::class, 'App\\Models\\User');
}

function ensureUserIndexRolesTable(): void
{
    if (! Schema::hasTable('roles')) {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('description')->nullable();
            $table->boolean('crm_role')->default(0);
            $table->timestamps();
        });
    }
}

beforeEach(function () {
    ensureUserIndexRolesTable();

    Route::get('crm/users/invitations/{code}/accept', fn () => 'ok')
        ->name('laravel-crm.users.invitations.accept');

    $this->actingAsUser();
    UserInvitation::query()->forceDelete();

    // Grant the create-user policy check so the row actions execute.
    Gate::before(fn () => true);
});

test('tab property defaults to users and carries a #[Url] attribute', function () {
    $component = new UserIndex;

    expect($component->tab)->toBe('users');

    $reflection = new ReflectionProperty(UserIndex::class, 'tab');
    $attributes = $reflection->getAttributes(Url::class);

    expect($attributes)->not->toBeEmpty();
});

test('invitations property returns only pending invitations ordered by last_sent_at desc', function () {
    UserInvitation::create([
        'email' => 'recent@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    UserInvitation::create([
        'email' => 'older@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now()->subDay(),
    ]);

    // Accepted and expired invitations should be excluded.
    UserInvitation::create([
        'email' => 'accepted@example.test',
        'accepted_at' => now(),
        'last_sent_at' => now(),
    ]);
    UserInvitation::create([
        'email' => 'expired@example.test',
        'expires_at' => now()->subDay(),
        'last_sent_at' => now(),
    ]);

    $component = new UserIndex;
    $invitations = $component->invitations;

    expect($invitations->total())->toBe(2)
        ->and($invitations->first()->email)->toBe('recent@example.test')
        ->and($invitations->items()[1]->email)->toBe('older@example.test');
});

test('resendInvitation re-sends the notification and updates last_sent_at', function () {
    Notification::fake();

    $invitation = UserInvitation::create([
        'email' => 'resend@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now()->subDays(3),
    ]);

    $originalStamp = $invitation->last_sent_at->toDateTimeString();

    (new UserIndex)->resendInvitation($invitation->id);

    $invitation->refresh();
    expect($invitation->last_sent_at->toDateTimeString())->not->toBe($originalStamp)
        ->and($invitation->last_sent_at->greaterThan(now()->subMinute()))->toBeTrue();

    Notification::assertSentOnDemand(
        UserInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'resend@example.test'
    );
});

test('resendInvitation is a no-op when Gate denies create User', function () {
    Notification::fake();

    // Simulate a session where Gate::allows('create', User::class) returns false.
    auth()->logout();

    $invitation = UserInvitation::create([
        'email' => 'noauth@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now()->subDays(3),
    ]);

    $originalStamp = $invitation->last_sent_at->toDateTimeString();

    (new UserIndex)->resendInvitation($invitation->id);

    $invitation->refresh();
    expect($invitation->last_sent_at->toDateTimeString())->toBe($originalStamp);

    Notification::assertNothingSent();
});

test('deleteInvitation soft-deletes the row without hard-deleting', function () {
    $invitation = UserInvitation::create([
        'email' => 'delete@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    (new UserIndex)->deleteInvitation($invitation->id);

    expect(UserInvitation::query()->find($invitation->id))->toBeNull()
        ->and(UserInvitation::withTrashed()->find($invitation->id))->not->toBeNull()
        ->and(UserInvitation::withTrashed()->find($invitation->id)->deleted_at)->not->toBeNull();
});

test('deleteInvitation is a no-op when Gate denies create User', function () {
    // Simulate a session where Gate::allows('create', User::class) returns false.
    auth()->logout();

    $invitation = UserInvitation::create([
        'email' => 'noauth-del@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    (new UserIndex)->deleteInvitation($invitation->id);

    expect(UserInvitation::query()->find($invitation->id))->not->toBeNull();
});

test('invitations query is team-scoped when teams config is enabled', function () {
    config(['laravel-crm.teams' => true]);

    // The acting user's team resolves to null in tests, so scope narrows to
    // rows with team_id === null. Seed one null-team row (visible) and one
    // with an explicit team_id (hidden).
    UserInvitation::create([
        'email' => 'null-team@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
        'team_id' => null,
    ]);

    UserInvitation::create([
        'email' => 'other-team@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
        'team_id' => 99,
    ]);

    $invitations = (new UserIndex)->invitations;

    expect($invitations->total())->toBe(1)
        ->and($invitations->first()->email)->toBe('null-team@example.test');
});
