<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\PurchaseOrders\PurchaseOrderCreate;
use VentureDrake\LaravelCrm\Livewire\PurchaseOrders\PurchaseOrderEdit;
use VentureDrake\LaravelCrm\Livewire\PurchaseOrders\PurchaseOrderIndex;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzPurchaseOrderIndex extends PurchaseOrderIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzPurchaseOrderCreate extends PurchaseOrderCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzPurchaseOrderEdit extends PurchaseOrderEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a purchase order without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm purchase orders']);
    $record = PurchaseOrder::create(['reference' => 'Authz purchase order', 'issue_date' => now(), 'delivery_date' => now()]);

    Livewire::test(AuthzPurchaseOrderIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(PurchaseOrder::find($record->id))->not->toBeNull();
});

it('allows deleting a purchase order with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm purchase orders', 'delete crm purchase orders']);
    $record = PurchaseOrder::create(['reference' => 'Authz purchase order', 'issue_date' => now(), 'delivery_date' => now()]);

    Livewire::test(AuthzPurchaseOrderIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(PurchaseOrder::find($record->id))->toBeNull();
});

it('forbids creating a purchase order without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm purchase orders']);

    Livewire::test(AuthzPurchaseOrderCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the purchase order create guard', function () {
    $this->actingAsUserWithPermissions(['view crm purchase orders', 'create crm purchase orders']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzPurchaseOrderCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids saving a purchase order edit without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm purchase orders']);
    $record = PurchaseOrder::create(['reference' => 'Authz purchase order', 'issue_date' => now(), 'delivery_date' => now()]);

    Livewire::test(AuthzPurchaseOrderEdit::class, ['purchaseOrder' => $record])
        ->call('save')
        ->assertForbidden();
});

it('allows saving a purchase order edit with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm purchase orders', 'edit crm purchase orders']);
    $record = PurchaseOrder::create(['reference' => 'Authz purchase order', 'issue_date' => now(), 'delivery_date' => now()]);

    Livewire::test(AuthzPurchaseOrderEdit::class, ['purchaseOrder' => $record])
        ->call('save')
        ->assertOk();
});
