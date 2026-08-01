<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Organizations\OrganizationCreate;
use VentureDrake\LaravelCrm\Livewire\Organizations\OrganizationEdit;
use VentureDrake\LaravelCrm\Livewire\Organizations\OrganizationImport;
use VentureDrake\LaravelCrm\Livewire\Organizations\OrganizationIndex;
use VentureDrake\LaravelCrm\Livewire\Organizations\OrganizationShow;
use VentureDrake\LaravelCrm\Models\Organization;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzOrganizationIndex extends OrganizationIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzOrganizationShow extends OrganizationShow
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzOrganizationCreate extends OrganizationCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzOrganizationEdit extends OrganizationEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzOrganizationImport extends OrganizationImport
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting an organization without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm organizations']);
    $record = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzOrganizationIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Organization::find($record->id))->not->toBeNull();
});

it('allows deleting an organization with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'delete crm organizations']);
    $record = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzOrganizationIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Organization::find($record->id))->toBeNull();
});

it('forbids deleting an organization from the show page without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations']);
    $record = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzOrganizationShow::class, ['organization' => $record])
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Organization::find($record->id))->not->toBeNull();
});

it('forbids creating an organization without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations']);

    Livewire::test(AuthzOrganizationCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the organization create guard', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'create crm organizations']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzOrganizationCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids updating an organization without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations']);
    $record = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzOrganizationEdit::class, ['organization' => $record])
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the edit permission past the organization update guard', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'edit crm organizations']);
    $record = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzOrganizationEdit::class, ['organization' => $record])
        ->call('save')
        ->assertOk();
});

it('forbids importing organizations without the create permission and creates no records', function () {
    $this->actingAsUserWithPermissions(['view crm organizations']);
    $before = Organization::count();

    Livewire::test(AuthzOrganizationImport::class)
        ->call('processNextChunk')
        ->assertForbidden();

    expect(Organization::count())->toBe($before);
});

it('allows a user holding the create permission past the organization import guard', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'create crm organizations']);

    Livewire::test(AuthzOrganizationImport::class)
        ->call('startImport')
        ->assertOk();
});
