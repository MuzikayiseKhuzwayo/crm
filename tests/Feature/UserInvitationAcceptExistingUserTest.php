<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\UserInvitation;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

function ensureTeamUserTable(): void
{
    if (! Schema::hasTable('team_user')) {
        Schema::create('team_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }
}

beforeEach(function () {
    DB::table('users')->where('email', 'like', '%@invite-us005.test')->delete();
    UserInvitation::query()->delete();
});

test('logged-out visitor with an existing invitee is redirected to login with ?invitation={code}', function () {
    $existing = User::create([
        'name' => 'Existing Invitee',
        'email' => 'existing@invite-us005.test',
        'password' => bcrypt('secret'),
    ]);

    $invitation = UserInvitation::create([
        'email' => $existing->email,
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertRedirect(route('laravel-crm.login').'?invitation='.$invitation->code);
});

test('logged-in user with a different email sees the invalid-invite view with a please-log-out message', function () {
    $viewer = $this->actingAsUser(['email' => 'viewer@invite-us005.test']);

    User::create([
        'name' => 'Invited Person',
        'email' => 'invitee@invite-us005.test',
        'password' => bcrypt('secret'),
    ]);

    $invitation = UserInvitation::create([
        'email' => 'invitee@invite-us005.test',
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertStatus(403);
    $response->assertViewIs('laravel-crm::users.invalid-invite');
    $response->assertSee('invitee@invite-us005.test');
    $response->assertSee('log out', escape: false);

    // Invitation still pending — nothing persisted.
    expect($invitation->fresh()->accepted_at)->toBeNull();
    expect($viewer->fresh()->crm_access)->toBe(1); // pre-existing default
});

test('logged-in invitee accepts the invitation (no teams config) and lands on the CRM dashboard', function () {
    config()->set('laravel-crm.teams', false);

    $invitee = User::create([
        'name' => 'Direct Invitee',
        'email' => 'accept@invite-us005.test',
        'password' => bcrypt('secret'),
        'crm_access' => 0,
    ]);

    $this->actingAs($invitee);

    $invitation = UserInvitation::create([
        'email' => $invitee->email,
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertRedirect(route('laravel-crm.dashboard'));

    expect($invitee->fresh()->crm_access)->toBe(1);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('logged-in invitee accepts the invitation (teams config on) and gets the team_user pivot + current_team_id set', function () {
    config()->set('laravel-crm.teams', true);
    ensureTeamUserTable();
    DB::table('team_user')->delete();

    $invitee = User::create([
        'name' => 'Team Invitee',
        'email' => 'teams@invite-us005.test',
        'password' => bcrypt('secret'),
        'crm_access' => 0,
    ]);

    $this->actingAs($invitee);

    $invitation = UserInvitation::create([
        'email' => $invitee->email,
        'team_id' => 42,
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertRedirect(route('laravel-crm.dashboard'));

    expect($invitee->fresh()->crm_access)->toBe(1);
    expect($invitee->fresh()->current_team_id)->toBe(42);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    expect(DB::table('team_user')->where('user_id', $invitee->id)->where('team_id', 42)->exists())->toBeTrue();
});
