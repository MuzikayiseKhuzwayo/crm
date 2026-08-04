<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Deliveries\DeliveryCreate;
use VentureDrake\LaravelCrm\Livewire\Deliveries\DeliveryEdit;
use VentureDrake\LaravelCrm\Livewire\Invoices\InvoiceCreate;
use VentureDrake\LaravelCrm\Livewire\Invoices\InvoiceEdit;
use VentureDrake\LaravelCrm\Livewire\Orders\OrderCreate;
use VentureDrake\LaravelCrm\Livewire\Orders\OrderEdit;
use VentureDrake\LaravelCrm\Livewire\PurchaseOrders\PurchaseOrderCreate;
use VentureDrake\LaravelCrm\Livewire\PurchaseOrders\PurchaseOrderEdit;
use VentureDrake\LaravelCrm\Livewire\Quotes\QuoteCreate;
use VentureDrake\LaravelCrm\Livewire\Quotes\QuoteEdit;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

/*
 * The per-record PDF template picker on the 5 document create/edit forms.
 *
 * Contract under test:
 *   - the picker holds the record's own template and nothing else, so ''
 *     (the blank option, labelled with the template it resolves to) means
 *     "follow Settings → Templates";
 *   - creating  → the picker starts blank;
 *   - editing   → the picker starts on whatever the record itself carries,
 *                 and blank when the record predates the picker (null
 *                 `pdf_template`);
 *   - saving    → a picked slug lands on the record and every later PDF
 *                 render of that record resolves through it, while a blank
 *                 pick leaves (or sets) the column null so the record keeps
 *                 following settings.
 *
 * The blades reach for activity/contact tables the minimal TestSchema does
 * not ship, so each component is mounted through a render-stub subclass —
 * the same discipline as the Authorization suite. mount() (and therefore
 * mountPdfTemplate) still runs for real.
 */

class PdfTplInvoiceCreate extends InvoiceCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplInvoiceEdit extends InvoiceEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplOrderCreate extends OrderCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplOrderEdit extends OrderEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplQuoteCreate extends QuoteCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplQuoteEdit extends QuoteEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplPurchaseOrderCreate extends PurchaseOrderCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplPurchaseOrderEdit extends PurchaseOrderEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplDeliveryCreate extends DeliveryCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class PdfTplDeliveryEdit extends DeliveryEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    $this->actingAsUserWithPermissions([
        'view crm invoices', 'create crm invoices', 'edit crm invoices',
        'view crm orders', 'create crm orders', 'edit crm orders',
        'view crm quotes', 'create crm quotes', 'edit crm quotes',
        'view crm purchase orders', 'create crm purchase orders', 'edit crm purchase orders',
        'view crm deliveries', 'create crm deliveries', 'edit crm deliveries',
    ]);

    Setting::updateOrCreate(['name' => 'currency'], ['value' => 'USD']);
    // PurchaseOrder's mountCommon() dereferences the 'team' settings row for
    // its delivery-address list.
    Setting::updateOrCreate(['name' => 'team'], ['value' => 'related']);

    foreach ([Quote::class, Order::class, Invoice::class, PurchaseOrder::class, Delivery::class] as $model) {
        $pipeline = Pipeline::create([
            'external_id' => Str::uuid()->toString(),
            'name' => class_basename($model).' Pipeline',
            'model' => $model,
        ]);

        $pipeline->pipelineStages()->create([
            'external_id' => Str::uuid()->toString(),
            'name' => 'Draft',
            'order' => 0,
        ]);
    }

    app('laravel-crm.settings')->forgetCache();
});

/**
 * @return array{0:string, 1:string, 2:class-string, 3:class-string, 4:string, 5:callable}
 */
dataset('pdf_template_documents', [
    'invoice' => [
        'invoice', 'pdf_template_invoice',
        PdfTplInvoiceCreate::class, PdfTplInvoiceEdit::class, 'invoice',
        fn (?string $slug) => Invoice::create([
            'invoice_id' => 'INV-PICK',
            'currency' => 'USD',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'pdf_template' => $slug,
        ]),
    ],
    'order' => [
        'order', 'pdf_template_order',
        PdfTplOrderCreate::class, PdfTplOrderEdit::class, 'order',
        fn (?string $slug) => Order::create([
            'reference' => 'ORD-PICK',
            'currency' => 'USD',
            'pdf_template' => $slug,
        ]),
    ],
    'quote' => [
        'quote', 'pdf_template_quote',
        PdfTplQuoteCreate::class, PdfTplQuoteEdit::class, 'quote',
        fn (?string $slug) => Quote::create([
            'title' => 'Quote pick',
            'currency' => 'USD',
            'issue_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(30)->toDateTimeString(),
            'pdf_template' => $slug,
        ]),
    ],
    'purchase-order' => [
        'purchase-order', 'pdf_template_purchase-order',
        PdfTplPurchaseOrderCreate::class, PdfTplPurchaseOrderEdit::class, 'purchaseOrder',
        fn (?string $slug) => PurchaseOrder::create([
            'reference' => 'PO-PICK',
            'currency' => 'USD',
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(7)->toDateString(),
            'pdf_template' => $slug,
        ]),
    ],
    'delivery' => [
        'delivery', 'pdf_template_delivery',
        PdfTplDeliveryCreate::class, PdfTplDeliveryEdit::class, 'delivery',
        fn (?string $slug) => Delivery::create([
            'delivery_expected' => now()->toDateTimeString(),
            'delivered_on' => now()->addDay()->toDateTimeString(),
            'pdf_template' => $slug,
        ]),
    ],
]);

it('starts the create form on the blank follow-the-settings-default option', function (
    string $docType,
    string $settingKey,
    string $createComponent,
    string $editComponent,
    string $property,
    callable $make
) {
    app('laravel-crm.settings')->set($settingKey, 'bold');
    app('laravel-crm.settings')->forgetCache();

    // Not 'bold': seeding the picker with the resolved default would pin
    // that template onto the record on save, cutting it loose from
    // settings without anyone asking for it.
    Livewire::test($createComponent)->assertSet('pdf_template', '');
})->with('pdf_template_documents');

it('labels the blank option with the template settings currently resolves to', function (
    string $docType,
    string $settingKey,
    string $createComponent,
    string $editComponent,
    string $property,
    callable $make
) {
    expect(PdfTemplateRegistry::defaultOptionLabel($docType))->toBe('Default (Modern)');

    app('laravel-crm.settings')->set($settingKey, 'bold');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfTemplateRegistry::defaultOptionLabel($docType))->toBe('Default (Bold)');
})->with('pdf_template_documents');

it('starts the edit form on the template already saved against the record', function (
    string $docType,
    string $settingKey,
    string $createComponent,
    string $editComponent,
    string $property,
    callable $make
) {
    app('laravel-crm.settings')->set($settingKey, 'bold');
    app('laravel-crm.settings')->forgetCache();

    $record = $make('compact');

    Livewire::test($editComponent, [$property => $record])
        ->assertSet('pdf_template', 'compact');
})->with('pdf_template_documents');

it('starts the edit form on the blank option for records saved before the picker existed', function (
    string $docType,
    string $settingKey,
    string $createComponent,
    string $editComponent,
    string $property,
    callable $make
) {
    app('laravel-crm.settings')->set($settingKey, 'professional');
    app('laravel-crm.settings')->forgetCache();

    $record = $make(null);

    expect($record->pdf_template)->toBeNull();

    Livewire::test($editComponent, [$property => $record])
        ->assertSet('pdf_template', '');
})->with('pdf_template_documents');

it('persists the picked template when an invoice is created', function () {
    Livewire::test(PdfTplInvoiceCreate::class)
        ->set('organization_name', 'Template Co')
        ->set('pdf_template', 'classic')
        ->call('save')
        ->assertHasNoErrors();

    expect(Invoice::latest('id')->first()->pdf_template)->toBe('classic');
});

it('persists a changed template when an invoice is edited', function () {
    $invoice = Invoice::create([
        'invoice_id' => 'INV-EDIT',
        'currency' => 'USD',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'pdf_template' => 'compact',
    ]);

    Livewire::test(PdfTplInvoiceEdit::class, ['invoice' => $invoice])
        ->set('organization_name', 'Template Co')
        ->set('pdf_template', 'professional')
        ->call('save')
        ->assertHasNoErrors();

    expect($invoice->fresh()->pdf_template)->toBe('professional');
});

it('leaves a record that predates the picker tracking settings when it is edited', function () {
    app('laravel-crm.settings')->set('pdf_template_invoice', 'bold');
    app('laravel-crm.settings')->forgetCache();

    $invoice = Invoice::create([
        'invoice_id' => 'INV-LEGACY',
        'currency' => 'USD',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    // An edit that never touches the picker must not pin a template.
    Livewire::test(PdfTplInvoiceEdit::class, ['invoice' => $invoice])
        ->set('organization_name', 'Template Co')
        ->call('save')
        ->assertHasNoErrors();

    expect($invoice->fresh()->pdf_template)->toBeNull();

    // So the record still follows a later change of the settings default.
    app('laravel-crm.settings')->set('pdf_template_invoice', 'compact');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfTemplateRegistry::viewForModel('invoice', $invoice->fresh()))
        ->toBe('laravel-crm::pdfs.compact.invoice');
});

it('hands a pinned record back to settings when the blank option is picked', function () {
    app('laravel-crm.settings')->set('pdf_template_invoice', 'bold');
    app('laravel-crm.settings')->forgetCache();

    $invoice = Invoice::create([
        'invoice_id' => 'INV-UNPIN',
        'currency' => 'USD',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'pdf_template' => 'classic',
    ]);

    Livewire::test(PdfTplInvoiceEdit::class, ['invoice' => $invoice])
        ->assertSet('pdf_template', 'classic')
        ->set('organization_name', 'Template Co')
        ->set('pdf_template', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($invoice->fresh()->pdf_template)->toBeNull()
        ->and(PdfTemplateRegistry::viewForModel('invoice', $invoice->fresh()))
        ->toBe('laravel-crm::pdfs.bold.invoice');
});

it('rejects a template slug this package does not ship', function () {
    Livewire::test(PdfTplInvoiceCreate::class)
        ->set('organization_name', 'Template Co')
        ->set('pdf_template', 'retired-template')
        ->call('save')
        ->assertHasErrors('pdf_template');

    expect(Invoice::where('invoice_id', '!=', null)->count())->toBe(0);
});
