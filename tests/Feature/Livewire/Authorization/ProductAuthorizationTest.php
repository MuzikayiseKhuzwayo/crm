<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Products\ProductCreate;
use VentureDrake\LaravelCrm\Livewire\Products\ProductEdit;
use VentureDrake\LaravelCrm\Livewire\Products\ProductIndex;
use VentureDrake\LaravelCrm\Models\Product;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzProductIndex extends ProductIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzProductCreate extends ProductCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzProductEdit extends ProductEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a product without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm products']);
    $record = Product::create(['name' => 'Authz product']);

    Livewire::test(AuthzProductIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Product::find($record->id))->not->toBeNull();
});

it('allows deleting a product with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm products', 'delete crm products']);
    $record = Product::create(['name' => 'Authz product']);

    Livewire::test(AuthzProductIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Product::find($record->id))->toBeNull();
});

it('forbids creating a product without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm products']);

    Livewire::test(AuthzProductCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the product create guard', function () {
    $this->actingAsUserWithPermissions(['view crm products', 'create crm products']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzProductCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids saving a product edit without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm products']);
    $record = Product::create(['name' => 'Authz product']);

    Livewire::test(AuthzProductEdit::class, ['product' => $record])
        ->call('save')
        ->assertForbidden();
});

it('allows saving a product edit with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm products', 'edit crm products']);
    $record = Product::create(['name' => 'Authz product']);

    Livewire::test(AuthzProductEdit::class, ['product' => $record])
        ->call('save')
        ->assertOk();
});
