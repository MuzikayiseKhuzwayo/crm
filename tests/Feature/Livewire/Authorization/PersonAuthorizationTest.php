<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\People\PersonCreate;
use VentureDrake\LaravelCrm\Livewire\People\PersonEdit;
use VentureDrake\LaravelCrm\Livewire\People\PersonImport;
use VentureDrake\LaravelCrm\Livewire\People\PersonIndex;
use VentureDrake\LaravelCrm\Livewire\People\PersonShow;
use VentureDrake\LaravelCrm\Models\Person;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzPersonIndex extends PersonIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzPersonShow extends PersonShow
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzPersonCreate extends PersonCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzPersonEdit extends PersonEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzPersonImport extends PersonImport
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a person without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm people']);
    $record = Person::create(['first_name' => 'Authz']);

    Livewire::test(AuthzPersonIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Person::find($record->id))->not->toBeNull();
});

it('allows deleting a person with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'delete crm people']);
    $record = Person::create(['first_name' => 'Authz']);

    Livewire::test(AuthzPersonIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Person::find($record->id))->toBeNull();
});

it('forbids deleting a person from the show page without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm people']);
    $record = Person::create(['first_name' => 'Authz']);

    Livewire::test(AuthzPersonShow::class, ['person' => $record])
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Person::find($record->id))->not->toBeNull();
});

it('forbids creating a person without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm people']);

    Livewire::test(AuthzPersonCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the person create guard', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'create crm people']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzPersonCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids updating a person without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm people']);
    $record = Person::create(['first_name' => 'Authz']);

    Livewire::test(AuthzPersonEdit::class, ['person' => $record])
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the edit permission past the person update guard', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'edit crm people']);
    $record = Person::create(['first_name' => 'Authz']);

    Livewire::test(AuthzPersonEdit::class, ['person' => $record])
        ->call('save')
        ->assertOk();
});

it('forbids importing people without the create permission and creates no records', function () {
    $this->actingAsUserWithPermissions(['view crm people']);
    $before = Person::count();

    Livewire::test(AuthzPersonImport::class)
        ->call('processNextChunk')
        ->assertForbidden();

    expect(Person::count())->toBe($before);
});

it('allows a user holding the create permission past the person import guard', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'create crm people']);

    Livewire::test(AuthzPersonImport::class)
        ->call('startImport')
        ->assertOk();
});
