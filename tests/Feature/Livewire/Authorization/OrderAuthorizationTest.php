<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Orders\OrderCreate;
use VentureDrake\LaravelCrm\Livewire\Orders\OrderEdit;
use VentureDrake\LaravelCrm\Livewire\Orders\OrderIndex;
use VentureDrake\LaravelCrm\Models\Order;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzOrderIndex extends OrderIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzOrderCreate extends OrderCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzOrderEdit extends OrderEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a order without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm orders']);
    $record = Order::create(['reference' => 'Authz order']);

    Livewire::test(AuthzOrderIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Order::find($record->id))->not->toBeNull();
});

it('allows deleting a order with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm orders', 'delete crm orders']);
    $record = Order::create(['reference' => 'Authz order']);

    Livewire::test(AuthzOrderIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Order::find($record->id))->toBeNull();
});

it('forbids creating a order without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm orders']);

    Livewire::test(AuthzOrderCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the order create guard', function () {
    $this->actingAsUserWithPermissions(['view crm orders', 'create crm orders']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzOrderCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids saving an order edit without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm orders']);
    $record = Order::create(['reference' => 'Authz order']);

    Livewire::test(AuthzOrderEdit::class, ['order' => $record])
        ->call('save')
        ->assertForbidden();
});

it('allows saving an order edit with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm orders', 'edit crm orders']);
    $record = Order::create(['reference' => 'Authz order']);

    Livewire::test(AuthzOrderEdit::class, ['order' => $record])
        ->call('save')
        ->assertOk();
});
