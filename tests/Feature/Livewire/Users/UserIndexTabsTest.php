<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Users\UserIndex;
use VentureDrake\LaravelCrm\Livewire\Users\UserInvite;
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

// Reuse ensureInviteRolesTable() from UserInviteTest.php when available.
// When only this file loads (e.g. `pest --filter=UserIndexTabsTest`), the
// sibling helper isn't defined yet, so we define it here under a
// function_exists guard so we can honor the AC's "reuse" directive
// without triggering a redeclaration fatal in the full-suite run.
if (! function_exists('ensureInviteRolesTable')) {
    function ensureInviteRolesTable(): void
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
}

if (! function_exists('makeCrmRole')) {
    function makeCrmRole(string $name = 'Editor'): int
    {
        return DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'crm_role' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

beforeEach(function () {
    ensureInviteRolesTable();
    DB::table('roles')->delete();
    UserInvitation::query()->forceDelete();

    Route::get('crm/users/invitations/{code}/accept', fn () => 'ok')
        ->name('laravel-crm.users.invitations.accept');

    $this->actingAsUser();

    // Grant the create-user policy check so resend/delete row actions execute.
    Gate::before(fn () => true);
});

test('Livewire mount with ?tab=invitations sets tab state and exposes pending invitations', function () {
    $invitation = UserInvitation::create([
        'email' => 'pending-mount@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    // Simulate URL-driven mount by setting the #[Url] property to 'invitations'
    // and reading back through the same magic-property path the Blade uses.
    $component = new UserIndex;
    $component->tab = 'invitations';
    $component->mount();

    expect($component->tab)->toBe('invitations')
        ->and($component->invitations->total())->toBe(1)
        ->and($component->invitations->first()->email)->toBe($invitation->email);
});

test('getInvitationsProperty excludes accepted, expired, and soft-deleted rows and scopes by team when enabled', function () {
    // Pending (visible)
    UserInvitation::create([
        'email' => 'pending@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    // Accepted (excluded)
    UserInvitation::create([
        'email' => 'accepted@example.test',
        'expires_at' => now()->addWeek(),
        'accepted_at' => now(),
        'last_sent_at' => now(),
    ]);

    // Expired (excluded)
    UserInvitation::create([
        'email' => 'expired@example.test',
        'expires_at' => now()->subDay(),
        'last_sent_at' => now(),
    ]);

    // Soft-deleted (excluded via SoftDeletes global scope)
    $trashed = UserInvitation::create([
        'email' => 'trashed@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);
    $trashed->delete();

    $invitations = (new UserIndex)->invitations;

    expect($invitations->total())->toBe(1)
        ->and($invitations->first()->email)->toBe('pending@example.test');

    // With teams enabled, scoping narrows to auth user's currentTeam id
    // (null in tests). Row with an explicit team_id should be hidden.
    config(['laravel-crm.teams' => true]);

    UserInvitation::create([
        'email' => 'other-team-tab@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
        'team_id' => 42,
    ]);

    $teamScoped = (new UserIndex)->invitations;

    expect($teamScoped->total())->toBe(1)
        ->and($teamScoped->pluck('email')->all())->not->toContain('other-team-tab@example.test');
});

test('resendInvitation dispatches UserInvitationNotification and updates last_sent_at', function () {
    Notification::fake();

    $invitation = UserInvitation::create([
        'email' => 'resend-tab@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now()->subDays(5),
    ]);

    $originalStamp = $invitation->last_sent_at->toDateTimeString();

    (new UserIndex)->resendInvitation($invitation->id);

    $invitation->refresh();

    expect($invitation->last_sent_at->toDateTimeString())->not->toBe($originalStamp)
        ->and($invitation->last_sent_at->greaterThan(now()->subMinute()))->toBeTrue();

    Notification::assertSentOnDemand(
        UserInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'resend-tab@example.test'
    );
});

test('deleteInvitation soft-deletes the row (default scope hides it, withTrashed reveals deleted_at)', function () {
    $invitation = UserInvitation::create([
        'email' => 'delete-tab@example.test',
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    $id = $invitation->id;

    (new UserIndex)->deleteInvitation($id);

    expect(UserInvitation::find($id))->toBeNull()
        ->and(UserInvitation::withTrashed()->find($id)?->deleted_at)->not->toBeNull();
});

test('after deleteInvitation a new UserInvite::save() for the same email succeeds (soft-deleted does not block)', function () {
    Notification::fake();

    $roleId = makeCrmRole('Editor');

    // Seed a pending invitation and delete it (soft-delete stamps deleted_at).
    $invitation = UserInvitation::create([
        'email' => 'recycle@example.test',
        'role_id' => $roleId,
        'expires_at' => now()->addWeek(),
        'last_sent_at' => now(),
    ]);

    (new UserIndex)->deleteInvitation($invitation->id);

    // Regression guard: soft-deleted row shouldn't block a re-invite. The
    // duplicate-pending validation rule filters via the default query which
    // Eloquent's SoftDeletes global scope hides.
    Livewire::test(UserInvite::class)
        ->set('email', 'recycle@example.test')
        ->set('role_id', $roleId)
        ->call('save')
        ->assertHasNoErrors();

    // New pending invitation persisted; old row remains soft-deleted.
    $newInvitation = UserInvitation::query()->where('email', 'recycle@example.test')->first();

    expect($newInvitation)->not->toBeNull()
        ->and($newInvitation->id)->not->toBe($invitation->id)
        ->and($newInvitation->accepted_at)->toBeNull()
        ->and(UserInvitation::withTrashed()->find($invitation->id)?->deleted_at)->not->toBeNull();

    Notification::assertSentOnDemand(
        UserInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'recycle@example.test'
    );
});
