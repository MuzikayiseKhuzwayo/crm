<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Settings\TemplateSettings;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

beforeEach(function () {
    $this->actingAsUser(['crm_access' => 1]);
    // Grant the settings-permission gate so the middleware chain lets the
    // request reach the controller / component. Matches the pattern from
    // TemplatePreviewControllerTest + UserIndexTabsTest.
    Gate::before(fn () => true);
    Setting::query()->delete();
    app('laravel-crm.settings')->forgetCache();
});

test('route laravel-crm.settings.templates.edit is registered under the settings-permission middleware group', function () {
    $route = Route::getRoutes()->getByName('laravel-crm.settings.templates.edit');

    expect($route)->not->toBeNull();
    expect($route->uri())->toContain('settings/templates');
    expect($route->methods())->toContain('GET');
    expect($route->middleware())->toContain('auth.laravel-crm');
    expect($route->middleware())->toContain('can:update,VentureDrake\LaravelCrm\Models\Setting');
});

test('TemplateSettings mount hydrates selected with defaults when no settings are persisted', function () {
    Livewire::test(TemplateSettings::class)
        ->assertSet('selected.invoice', 'modern')
        ->assertSet('selected.order', 'modern')
        ->assertSet('selected.purchase-order', 'modern')
        ->assertSet('selected.delivery', 'modern')
        ->assertSet('selected.quote', 'modern');
});

test('TemplateSettings mount reads persisted values via SettingService::get()', function () {
    app('laravel-crm.settings')->set('pdf_template_invoice', 'classic');
    app('laravel-crm.settings')->set('pdf_template_order', 'bold');
    app('laravel-crm.settings')->set('pdf_template_purchase-order', 'compact');
    app('laravel-crm.settings')->set('pdf_template_delivery', 'professional');
    app('laravel-crm.settings')->set('pdf_template_quote', 'modern');
    app('laravel-crm.settings')->forgetCache();

    Livewire::test(TemplateSettings::class)
        ->assertSet('selected.invoice', 'classic')
        ->assertSet('selected.order', 'bold')
        ->assertSet('selected.purchase-order', 'compact')
        ->assertSet('selected.delivery', 'professional')
        ->assertSet('selected.quote', 'modern');
});

test('TemplateSettings select() updates selected for a valid docType + slug', function () {
    Livewire::test(TemplateSettings::class)
        ->call('select', 'invoice', 'bold')
        ->assertSet('selected.invoice', 'bold')
        ->call('select', 'quote', 'compact')
        ->assertSet('selected.quote', 'compact')
        ->assertSet('selected.invoice', 'bold');
});

test('TemplateSettings select() is a no-op for unknown docType', function () {
    Livewire::test(TemplateSettings::class)
        ->call('select', 'nonexistent-doc', 'bold')
        ->assertSet('selected.invoice', 'modern')
        ->assertSet('selected.quote', 'modern');
});

test('TemplateSettings select() is a no-op for unknown slug', function () {
    Livewire::test(TemplateSettings::class)
        ->call('select', 'invoice', 'this-slug-does-not-exist')
        ->assertSet('selected.invoice', 'modern');
});

test('TemplateSettings save() persists all 5 keys via SettingService::set()', function () {
    Livewire::test(TemplateSettings::class)
        ->set('selected.invoice', 'classic')
        ->set('selected.order', 'bold')
        ->set('selected.purchase-order', 'compact')
        ->set('selected.delivery', 'professional')
        ->set('selected.quote', 'modern')
        ->call('save')
        ->assertHasNoErrors();

    app('laravel-crm.settings')->forgetCache();

    expect(app('laravel-crm.settings')->get('pdf_template_invoice'))->toBe('classic');
    expect(app('laravel-crm.settings')->get('pdf_template_order'))->toBe('bold');
    expect(app('laravel-crm.settings')->get('pdf_template_purchase-order'))->toBe('compact');
    expect(app('laravel-crm.settings')->get('pdf_template_delivery'))->toBe('professional');
    expect(app('laravel-crm.settings')->get('pdf_template_quote'))->toBe('modern');
});

test('TemplateSettings save() defaults unknown persisted slug back to modern', function () {
    Livewire::test(TemplateSettings::class)
        ->set('selected.invoice', 'this-slug-does-not-exist')
        ->call('save');

    app('laravel-crm.settings')->forgetCache();

    expect(app('laravel-crm.settings')->get('pdf_template_invoice'))->toBe('modern');
});

test('TemplateSettings save() round-trip works — persisted values re-hydrate on next mount', function () {
    Livewire::test(TemplateSettings::class)
        ->set('selected.invoice', 'bold')
        ->set('selected.quote', 'classic')
        ->call('save');

    app('laravel-crm.settings')->forgetCache();

    // Second mount reads back the persisted values.
    Livewire::test(TemplateSettings::class)
        ->assertSet('selected.invoice', 'bold')
        ->assertSet('selected.quote', 'classic')
        ->assertSet('selected.order', 'modern');
});

test('TemplateSettings render exposes docTypes and templates', function () {
    Livewire::test(TemplateSettings::class)
        ->assertViewIs('laravel-crm::livewire.settings.template-settings')
        ->assertViewHas('docTypes', PdfTemplateRegistry::DOC_TYPES)
        ->assertViewHas('templates', PdfTemplateRegistry::all());
});

test('TemplateSettings blade view contains the 5 doc-type tabs and 5-card grid markup', function () {
    $bladePath = __DIR__.'/../../../../resources/views/livewire/settings/template-settings.blade.php';

    expect(file_exists($bladePath))->toBeTrue();

    $blade = file_get_contents($bladePath);

    // Uses raw DaisyUI `tabs tabs-lift` radio inputs (same style as
    // /crm/users), bound to the Livewire `$tab` property via
    // wire:model.live. Regression guard against a future refactor
    // reverting to the pre-existing mary-tabs shape.
    expect($blade)->toContain('class="tabs tabs-lift"');
    expect($blade)->toContain('role="tab"');
    expect($blade)->toContain('role="tabpanel"');
    expect($blade)->toContain('wire:model.live="tab"');
    expect($blade)->toContain('name="template-tabs"');
    expect($blade)->toContain('foreach ($docTypes as $docType)');
    expect($blade)->toContain('foreach ($templates as $slug => $template)');
    expect($blade)->toContain("route('laravel-crm.settings.templates.preview'");
    expect($blade)->toContain('wire:submit="save"');
    expect($blade)->toContain('wire:click="select');
});

test('TemplateSettings mount + save preserve the selection for the AC-named 5 setting keys', function () {
    // AC lists: pdf_template_invoice, pdf_template_order, pdf_template_purchase_order,
    // pdf_template_delivery, pdf_template_quote. Actual persisted keys use the
    // hyphenated docType names from PdfTemplateRegistry::DOC_TYPES matching the
    // reader convention from US-005 (pdf_template_purchase-order not
    // pdf_template_purchase_order).
    $expectedKeys = [
        'pdf_template_invoice',
        'pdf_template_order',
        'pdf_template_purchase-order',
        'pdf_template_delivery',
        'pdf_template_quote',
    ];

    Livewire::test(TemplateSettings::class)
        ->set('selected.invoice', 'classic')
        ->set('selected.order', 'classic')
        ->set('selected.purchase-order', 'classic')
        ->set('selected.delivery', 'classic')
        ->set('selected.quote', 'classic')
        ->call('save');

    foreach ($expectedKeys as $key) {
        expect(Setting::where('name', $key)->first())
            ->not->toBeNull()
            ->and(Setting::where('name', $key)->value('value'))->toBe('classic');
    }
});
