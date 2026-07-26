<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Users\UserInvite;
use VentureDrake\LaravelCrm\Models\UserInvitation;
use VentureDrake\LaravelCrm\Notifications\UserInvitationNotification;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

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

beforeEach(function () {
    ensureInviteRolesTable();
    DB::table('roles')->delete();

    Route::get('crm/users/invitations/{code}/accept', fn () => 'ok')
        ->name('laravel-crm.users.invitations.accept');

    $this->actingAsUser();
});

test('submitting the form creates an invitation row and queues a notification', function () {
    Notification::fake();

    $roleId = makeCrmRole('Editor');

    Livewire::test(UserInvite::class)
        ->set('email', 'invitee@example.test')
        ->set('role_id', $roleId)
        ->call('save')
        ->assertHasNoErrors();

    $invitation = UserInvitation::query()->where('email', 'invitee@example.test')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->role_id)->toBe($roleId)
        ->and($invitation->invited_by)->toBe(auth()->id())
        ->and($invitation->code)->not->toBeEmpty()
        ->and($invitation->external_id)->not->toBeEmpty()
        ->and($invitation->last_sent_at)->not->toBeNull();

    Notification::assertSentOnDemand(
        UserInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invitee@example.test'
    );
});

test('email is required and must be a valid email address', function () {
    $roleId = makeCrmRole('Editor');

    Livewire::test(UserInvite::class)
        ->set('email', '')
        ->set('role_id', $roleId)
        ->call('save')
        ->assertHasErrors(['email' => 'required']);

    Livewire::test(UserInvite::class)
        ->set('email', 'not-an-email')
        ->set('role_id', $roleId)
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('email is rejected when an existing user already has it', function () {
    $roleId = makeCrmRole('Editor');
    User::create([
        'name' => 'Existing',
        'email' => 'taken@example.test',
        'password' => bcrypt('secret'),
    ]);

    Livewire::test(UserInvite::class)
        ->set('email', 'taken@example.test')
        ->set('role_id', $roleId)
        ->call('save')
        ->assertHasErrors('email');

    expect(UserInvitation::query()->count())->toBe(0);
});

test('email is rejected when a pending invitation already exists', function () {
    $roleId = makeCrmRole('Editor');

    UserInvitation::create([
        'email' => 'pending@example.test',
        'role_id' => $roleId,
    ]);

    Livewire::test(UserInvite::class)
        ->set('email', 'pending@example.test')
        ->set('role_id', $roleId)
        ->call('save')
        ->assertHasErrors('email');

    expect(UserInvitation::query()->where('email', 'pending@example.test')->count())->toBe(1);
});

test('role_id is required and must reference a CRM role', function () {
    Livewire::test(UserInvite::class)
        ->set('email', 'invitee2@example.test')
        ->set('role_id', null)
        ->call('save')
        ->assertHasErrors(['role_id' => 'required']);

    // Non-CRM role (crm_role = 0) — not a valid target.
    $nonCrmId = DB::table('roles')->insertGetId([
        'name' => 'System',
        'guard_name' => 'web',
        'crm_role' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(UserInvite::class)
        ->set('email', 'invitee3@example.test')
        ->set('role_id', $nonCrmId)
        ->call('save')
        ->assertHasErrors('role_id');
});
