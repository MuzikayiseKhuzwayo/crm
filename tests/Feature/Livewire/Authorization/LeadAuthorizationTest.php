<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadBoard;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadCreate;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadEdit;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadIndex;
use VentureDrake\LaravelCrm\Models\Lead;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzLeadIndex extends LeadIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLeadCreate extends LeadCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLeadEdit extends LeadEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLeadBoard extends LeadBoard
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a lead without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm leads']);
    $record = Lead::create(['title' => 'Authz lead']);

    Livewire::test(AuthzLeadIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Lead::find($record->id))->not->toBeNull();
});

it('allows deleting a lead with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm leads', 'delete crm leads']);
    $record = Lead::create(['title' => 'Authz lead']);

    Livewire::test(AuthzLeadIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Lead::find($record->id))->toBeNull();
});

it('forbids creating a lead without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm leads']);

    Livewire::test(AuthzLeadCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the lead create guard', function () {
    $this->actingAsUserWithPermissions(['view crm leads', 'create crm leads']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzLeadCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids saving a lead edit without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm leads']);
    $record = Lead::create(['title' => 'Authz lead']);

    Livewire::test(AuthzLeadEdit::class, ['lead' => $record])
        ->call('save')
        ->assertForbidden();
});

it('allows saving a lead edit with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm leads', 'edit crm leads']);
    $record = Lead::create(['title' => 'Authz lead']);

    Livewire::test(AuthzLeadEdit::class, ['lead' => $record])
        ->call('save')
        ->assertOk();
});

it('forbids reordering the lead board without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm leads']);
    $record = Lead::create(['title' => 'Authz lead']);

    Livewire::test(AuthzLeadBoard::class)
        ->call('onStageChanged', $record->id, 1, [], [])
        ->assertForbidden();

    expect($record->fresh()->pipeline_stage_id)->toBeNull();
});

it('allows reordering the lead board with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm leads', 'edit crm leads']);
    $record = Lead::create(['title' => 'Authz lead']);

    Livewire::test(AuthzLeadBoard::class)
        ->call('onStageChanged', $record->id, 1, [], [])
        ->assertOk();

    expect($record->fresh()->pipeline_stage_id)->toBe(1);
});
