<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Teams\TeamCreate;
use VentureDrake\LaravelCrm\Livewire\Teams\TeamEdit;
use VentureDrake\LaravelCrm\Livewire\Teams\TeamShow;
use VentureDrake\LaravelCrm\Models\Team;

/**
 * Render-stub subclasses -- see NoteAuthorizationTest for the rationale. Only render()
 * is replaced; every guarded action method runs for real against the real TeamPolicy.
 */
class AuthzTeamShow extends TeamShow
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzTeamCreate extends TeamCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzTeamEdit extends TeamEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzTeam(string $name = 'Original name'): Team
{
    return Team::create(['name' => $name, 'user_id' => 1]);
}

/*
 * ---------------------------------------------------------------------------
 * TeamShow::delete
 * ---------------------------------------------------------------------------
 */

it('forbids deleting a team from the show page without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm teams']);
    $team = authzTeam();

    Livewire::test(AuthzTeamShow::class, ['team' => $team])
        ->call('delete', $team->id)
        ->assertForbidden();

    expect(Team::find($team->id))->not->toBeNull();
});

it('deletes a team from the show page with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm teams', 'delete crm teams']);
    $team = authzTeam();

    Livewire::test(AuthzTeamShow::class, ['team' => $team])
        ->call('delete', $team->id)
        ->assertOk();

    expect(Team::find($team->id))->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * TeamCreate::save
 * ---------------------------------------------------------------------------
 */

it('forbids creating a team without the create permission and stores nothing', function () {
    $this->actingAsUserWithPermissions(['view crm teams']);
    $before = Team::count();

    Livewire::test(AuthzTeamCreate::class)
        ->set('name', 'Denied team')
        ->call('save')
        ->assertForbidden();

    expect(Team::count())->toBe($before)
        ->and(Team::where('name', 'Denied team')->exists())->toBeFalse();
});

it('creates a team with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm teams', 'create crm teams']);

    Livewire::test(AuthzTeamCreate::class)
        ->set('name', 'Allowed team')
        ->call('save')
        ->assertOk();

    expect(Team::where('name', 'Allowed team')->exists())->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * TeamEdit::save
 * ---------------------------------------------------------------------------
 */

it('forbids updating a team without the edit permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm teams']);
    $team = authzTeam();

    Livewire::test(AuthzTeamEdit::class, ['team' => $team])
        ->set('name', 'Tampered')
        ->call('save')
        ->assertForbidden();

    expect($team->fresh()->name)->toBe('Original name');
});

it('updates a team with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm teams', 'edit crm teams']);
    $team = authzTeam();

    Livewire::test(AuthzTeamEdit::class, ['team' => $team])
        ->set('name', 'Renamed team')
        ->call('save')
        ->assertOk();

    expect($team->fresh()->name)->toBe('Renamed team');
});
