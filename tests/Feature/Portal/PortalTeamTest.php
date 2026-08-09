<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Portal\Features\PublicFeatureBoard;
use VentureDrake\LaravelCrm\Models\Feature;
use VentureDrake\LaravelCrm\Models\FeatureStatus;
use VentureDrake\LaravelCrm\Support\PortalTeam;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

/**
 * Which team's public board a portal request is looking at.
 *
 * The regression this file guards: the portal used to read one team id out of
 * `laravel-crm.portal.team_id` and 404 everything else, so on a teams install
 * every team but the configured one had no portal, and an install that had not
 * set the key had none at all.
 */
beforeEach(function () {
    config()->set('laravel-crm.modules', ['features']);
    config()->set('laravel-crm.teams', true);
    config()->set('laravel-crm.portal.team_id', null);

    FeatureStatus::firstOrCreate(['name' => 'New'], ['is_default' => true, 'order' => 1, 'color' => '#6c757d']);
});

/** A public feature belonging to a given team, written past the team scope. */
function teamFeature(int $teamId, string $title): Feature
{
    $feature = Feature::create(['title' => $title, 'is_public' => true]);

    $feature->forceFill(['team_id' => $teamId])->saveQuietly();

    return $feature->fresh();
}

// -----------------------------------------------------------------------
// The team-addressable board
// -----------------------------------------------------------------------

test('each team has its own board at a shareable URL', function () {
    teamFeature(1, 'Team one idea');
    teamFeature(2, 'Team two idea');

    $this->get('/p/features/team/1')
        ->assertStatus(200)
        ->assertSee('Team one idea')
        ->assertDontSee('Team two idea');

    $this->get('/p/features/team/2')
        ->assertStatus(200)
        ->assertSee('Team two idea')
        ->assertDontSee('Team one idea');
});

test('the team board works for a guest with no session and no config', function () {
    // The whole point: the people who read a roadmap are the team's customers.
    teamFeature(7, 'Customer visible');

    expect(config('laravel-crm.portal.team_id'))->toBeNull();

    $this->get('/p/features/team/7')
        ->assertStatus(200)
        ->assertSee('Customer visible');
});

test('visiting a team board is remembered for the rest of the session', function () {
    teamFeature(3, 'Remembered idea');
    teamFeature(4, 'Other team idea');

    $this->get('/p/features/team/3')->assertStatus(200);

    // The bare board now follows them onto the one they arrived at, so "back
    // to the board" from a feature page does not land somewhere else.
    $this->get('/p/features')
        ->assertStatus(200)
        ->assertSee('Remembered idea')
        ->assertDontSee('Other team idea');
});

// -----------------------------------------------------------------------
// Resolving the bare board
// -----------------------------------------------------------------------

test('a single-team install needs no configuration and no team in the URL', function () {
    teamFeature(9, 'The only board');

    $this->get('/p/features')
        ->assertStatus(200)
        ->assertSee('The only board');
});

test('the bare board 404s when more than one team has one and nothing says which', function () {
    // Guessing here would show one team's roadmap to another team's customers.
    teamFeature(1, 'One');
    teamFeature(2, 'Two');

    $this->get('/p/features')->assertStatus(404);
});

test('a signed-in user falls back to their own current team', function () {
    teamFeature(1, 'Mine');
    teamFeature(2, 'Not mine');

    $user = User::create([
        'name' => 'Staff',
        'email' => 'staff@example.com',
        'password' => bcrypt('secret'),
        'current_team_id' => 1,
    ]);

    $this->actingAs($user)
        ->get('/p/features')
        ->assertStatus(200)
        ->assertSee('Mine')
        ->assertDontSee('Not mine');
});

// -----------------------------------------------------------------------
// Individual features
// -----------------------------------------------------------------------

test('a public feature is reachable by its own link whichever team owns it', function () {
    $feature = teamFeature(5, 'Shared by link');

    $this->get('/p/features/'.$feature->external_id)
        ->assertStatus(200)
        ->assertSee('Shared by link');
});

test('opening a feature moves the visitor onto that team board', function () {
    $feature = teamFeature(6, 'Arrived here');
    teamFeature(8, 'Somewhere else');

    $this->get('/p/features/'.$feature->external_id)->assertStatus(200);

    $this->get('/p/features')
        ->assertStatus(200)
        ->assertSee('Arrived here')
        ->assertDontSee('Somewhere else');
});

// -----------------------------------------------------------------------
// The configured lock still locks
// -----------------------------------------------------------------------

test('portal.team_id still pins the portal to one team', function () {
    config()->set('laravel-crm.portal.team_id', 1);

    teamFeature(1, 'Locked in');
    $outside = teamFeature(2, 'Locked out');

    $this->get('/p/features')
        ->assertStatus(200)
        ->assertSee('Locked in')
        ->assertDontSee('Locked out');

    $this->get('/p/features/'.$outside->external_id)->assertStatus(404);
});

test('the lock beats a team named in the URL', function () {
    config()->set('laravel-crm.portal.team_id', 1);

    teamFeature(1, 'Locked in');
    teamFeature(2, 'Locked out');

    $this->get('/p/features/team/2')
        ->assertStatus(200)
        ->assertSee('Locked in')
        ->assertDontSee('Locked out');
});

test('the lock is re-applied on the board component, not trusted from the mount', function () {
    // portalTeamId is a public Livewire property and so is whatever the client
    // sends back. Harmless in general — every public board is reachable at its
    // own URL — but a portal.team_id install has said it wants exactly one.
    config()->set('laravel-crm.portal.team_id', 1);

    teamFeature(1, 'Locked in');
    teamFeature(2, 'Locked out');

    Livewire::test(PublicFeatureBoard::class, ['portalTeamId' => 2])
        ->assertSee('Locked in')
        ->assertDontSee('Locked out');
});

test('the board component stays on the team it was mounted with', function () {
    // Sessions are shared across tabs; two boards open at once must not drag
    // each other around on the next Livewire update.
    teamFeature(1, 'Tab one');
    teamFeature(2, 'Tab two');

    session()->put(PortalTeam::SESSION_KEY, 1);

    Livewire::test(PublicFeatureBoard::class, ['portalTeamId' => 2])
        ->assertSee('Tab two')
        ->assertDontSee('Tab one');
});

// -----------------------------------------------------------------------
// Submitting
// -----------------------------------------------------------------------

test('a portal registrant with no host team can still submit', function () {
    // The bug: the submit path required the submitter's currentTeam to equal
    // the board's team, so everyone who signed up through /p/register — which
    // is who the portal is for — got a 403.
    teamFeature(4, 'Existing');

    $user = User::create([
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($user)
        ->get('/p/features/team/4')
        ->assertStatus(200);

    $this->actingAs($user)
        ->post('/p/features/submit', ['title' => 'From a customer'])
        ->assertRedirect();

    $submitted = Feature::withoutGlobalScopes()->where('title', 'From a customer')->first();

    expect($submitted)->not->toBeNull()
        ->and((int) $submitted->team_id)->toBe(4);
});

test('the submit form 404s rather than offering a form with no board to post to', function () {
    teamFeature(1, 'One');
    teamFeature(2, 'Two');

    $user = User::create([
        'name' => 'Customer',
        'email' => 'nowhere@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($user)->get('/p/features/submit')->assertStatus(404);
});

// -----------------------------------------------------------------------
// Teams off
// -----------------------------------------------------------------------

test('nothing is team-scoped when teams are disabled', function () {
    config()->set('laravel-crm.teams', false);

    Feature::create(['title' => 'Single tenant', 'is_public' => true]);

    expect(PortalTeam::scoped())->toBeFalse()
        ->and(PortalTeam::resolve())->toBeNull();

    $this->get('/p/features')
        ->assertStatus(200)
        ->assertSee('Single tenant');
});
