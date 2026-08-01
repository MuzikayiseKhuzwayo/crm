<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Invoices\InvoiceCreate;
use VentureDrake\LaravelCrm\Livewire\Invoices\InvoiceIndex;
use VentureDrake\LaravelCrm\Livewire\Invoices\InvoicePay;
use VentureDrake\LaravelCrm\Models\Invoice;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzInvoiceIndex extends InvoiceIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzInvoiceCreate extends InvoiceCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzInvoicePay extends InvoicePay
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a invoice without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm invoices']);
    $record = Invoice::create(['reference' => 'Authz invoice']);

    Livewire::test(AuthzInvoiceIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Invoice::find($record->id))->not->toBeNull();
});

it('allows deleting a invoice with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm invoices', 'delete crm invoices']);
    $record = Invoice::create(['reference' => 'Authz invoice']);

    Livewire::test(AuthzInvoiceIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Invoice::find($record->id))->toBeNull();
});

it('forbids creating a invoice without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm invoices']);

    Livewire::test(AuthzInvoiceCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the invoice create guard', function () {
    $this->actingAsUserWithPermissions(['view crm invoices', 'create crm invoices']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzInvoiceCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids paying an invoice without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm invoices']);
    $record = Invoice::create(['reference' => 'Authz invoice']);

    Livewire::test(AuthzInvoicePay::class, ['invoice' => $record])
        ->call('pay')
        ->assertForbidden();
});

it('allows paying an invoice with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm invoices', 'edit crm invoices']);
    $record = Invoice::create(['reference' => 'Authz invoice']);

    Livewire::test(AuthzInvoicePay::class, ['invoice' => $record])
        ->call('pay')
        ->assertOk();
});
