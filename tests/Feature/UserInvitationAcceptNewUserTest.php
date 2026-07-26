
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\UserInvitation;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

function ensureAcceptNewUserTeamUserTable(): void
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
    DB::table('users')->where('email', 'like', '%@invite-us006.test')->delete();
    UserInvitation::query()->delete();
    Auth::logout();
});

test('GET request with a valid invitation and no existing user renders the accept-invite form', function () {
    $invitation = UserInvitation::create([
        'email' => 'newuser@invite-us006.test',
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->get('/crm/users/invitations/'.$invitation->code.'/accept');

    $response->assertStatus(200);
    $response->assertViewIs('laravel-crm::users.accept-invite');
    $response->assertSee('newuser@invite-us006.test');
    $response->assertSee('name="name"', escape: false);
    $response->assertSee('name="password"', escape: false);
    $response->assertSee('name="password_confirmation"', escape: false);
});

test('POST with valid name and password creates the user, sets accepted_at, logs them in, and redirects to the dashboard (no teams config)', function () {
    config()->set('laravel-crm.teams', false);

    $invitation = UserInvitation::create([
        'email' => 'brand-new@invite-us006.test',
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->post('/crm/users/invitations/'.$invitation->code.'/accept', [
        'name' => 'Brand New User',
        'password' => 'secretpassword',
        'password_confirmation' => 'secretpassword',
    ]);

    $response->assertRedirect(route('laravel-crm.dashboard'));

    $user = User::query()->where('email', 'brand-new@invite-us006.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Brand New User')
        ->and($user->crm_access)->toBe(1)
        ->and(Hash::check('secretpassword', $user->password))->toBeTrue();

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

test('POST with valid data and teams config on creates user, inserts team_user pivot, and sets current_team_id', function () {
    config()->set('laravel-crm.teams', true);
    ensureAcceptNewUserTeamUserTable();
    DB::table('team_user')->delete();

    $invitation = UserInvitation::create([
        'email' => 'teamnew@invite-us006.test',
        'team_id' => 77,
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->post('/crm/users/invitations/'.$invitation->code.'/accept', [
        'name' => 'Team New',
        'password' => 'secretpassword',
        'password_confirmation' => 'secretpassword',
    ]);

    $response->assertRedirect(route('laravel-crm.dashboard'));

    $user = User::query()->where('email', 'teamnew@invite-us006.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->crm_access)->toBe(1)
        ->and($user->current_team_id)->toBe(77);

    expect(DB::table('team_user')->where('user_id', $user->id)->where('team_id', 77)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('POST with missing name fails validation and does NOT create a user', function () {
    $invitation = UserInvitation::create([
        'email' => 'noname@invite-us006.test',
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->from('/crm/users/invitations/'.$invitation->code.'/accept')
        ->post('/crm/users/invitations/'.$invitation->code.'/accept', [
            'password' => 'secretpassword',
            'password_confirmation' => 'secretpassword',
        ]);

    $response->assertSessionHasErrors('name');

    expect(User::query()->where('email', 'noname@invite-us006.test')->exists())->toBeFalse();
    expect($invitation->fresh()->accepted_at)->toBeNull();
    expect(Auth::check())->toBeFalse();
});

test('POST with password shorter than 8 chars fails validation', function () {
    $invitation = UserInvitation::create([
        'email' => 'shortpass@invite-us006.test',
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->from('/crm/users/invitations/'.$invitation->code.'/accept')
        ->post('/crm/users/invitations/'.$invitation->code.'/accept', [
            'name' => 'Test User',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

    $response->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'shortpass@invite-us006.test')->exists())->toBeFalse();
});

test('POST with mismatched password confirmation fails validation', function () {
    $invitation = UserInvitation::create([
        'email' => 'mismatch@invite-us006.test',
        'expires_at' => now()->addWeek(),
    ]);

    $response = $this->from('/crm/users/invitations/'.$invitation->code.'/accept')
        ->post('/crm/users/invitations/'.$invitation->code.'/accept', [
            'name' => 'Test User',
            'password' => 'secretpassword',
            'password_confirmation' => 'different',
        ]);

    $response->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'mismatch@invite-us006.test')->exists())->toBeFalse();
});

test('POST to an expired invitation renders the invalid-invite view and does NOT create a user', function () {
    $invitation = UserInvitation::create([
        'email' => 'expired@invite-us006.test',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->post('/crm/users/invitations/'.$invitation->code.'/accept', [
        'name' => 'Test User',
        'password' => 'secretpassword',
        'password_confirmation' => 'secretpassword',
    ]);

    $response->assertStatus(404);
    $response->assertViewIs('laravel-crm::users.invalid-invite');

    expect(User::query()->where('email', 'expired@invite-us006.test')->exists())->toBeFalse();
});

test('POST to an already-accepted invitation renders the invalid-invite view', function () {
    $invitation = UserInvitation::create([
        'email' => 'used@invite-us006.test',
        'expires_at' => now()->addWeek(),
        'accepted_at' => now(),
    ]);

    $response = $this->post('/crm/users/invitations/'.$invitation->code.'/accept', [
        'name' => 'Test User',
        'password' => 'secretpassword',
        'password_confirmation' => 'secretpassword',
    ]);

    $response->assertStatus(404);
    $response->assertViewIs('laravel-crm::users.invalid-invite');

    expect(User::query()->where('email', 'used@invite-us006.test')->exists())->toBeFalse();
});
