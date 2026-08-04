<?php

use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

test('defaultSlug returns modern', function () {
    expect(PdfTemplateRegistry::defaultSlug())->toBe('modern');
});

test('all() returns 5 template entries with slug label description thumbnail', function () {
    $templates = PdfTemplateRegistry::all();

    expect($templates)
        ->toBeArray()
        ->toHaveCount(5)
        ->toHaveKeys(['modern', 'classic', 'bold', 'compact', 'professional']);

    foreach ($templates as $slug => $entry) {
        expect($entry)
            ->toHaveKeys(['slug', 'label', 'description', 'thumbnail'])
            ->and($entry['slug'])->toBe($slug)
            ->and($entry['label'])->toBeString()->not->toBe('')
            ->and($entry['description'])->toBeString()->not->toBe('')
            ->and($entry['thumbnail'])->toBeString()->toContain($slug);
    }
});

test('viewFor resolves a known slug to laravel-crm::pdfs.{slug}.{docType}', function () {
    expect(PdfTemplateRegistry::viewFor('invoice', 'modern'))->toBe('laravel-crm::pdfs.modern.invoice');
});

test('viewFor resolves every doc type against every known slug', function () {
    foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
        foreach (array_keys(PdfTemplateRegistry::all()) as $slug) {
            expect(PdfTemplateRegistry::viewFor($docType, $slug))
                ->toBe('laravel-crm::pdfs.'.$slug.'.'.$docType);
        }
    }
});

test('viewFor falls back to defaultSlug when the slug is unknown', function () {
    expect(PdfTemplateRegistry::viewFor('invoice', 'this-slug-does-not-exist'))
        ->toBe('laravel-crm::pdfs.modern.invoice')
        ->and(PdfTemplateRegistry::viewFor('quote', ''))
        ->toBe('laravel-crm::pdfs.modern.quote');
});

test('settingKey keeps the hyphen the settings screen writes with', function () {
    expect(PdfTemplateRegistry::settingKey('invoice'))->toBe('pdf_template_invoice')
        ->and(PdfTemplateRegistry::settingKey('purchase-order'))->toBe('pdf_template_purchase-order');
});

test('defaultFor reads the settings choice and falls back to the default slug', function () {
    expect(PdfTemplateRegistry::defaultFor('invoice'))->toBe('modern');

    app('laravel-crm.settings')->set('pdf_template_invoice', 'bold');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfTemplateRegistry::defaultFor('invoice'))->toBe('bold');

    // A setting pointing at a template that no longer ships must not leak
    // through to viewFor() — it degrades to the default instead.
    app('laravel-crm.settings')->set('pdf_template_invoice', 'retired-template');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfTemplateRegistry::defaultFor('invoice'))->toBe('modern');
});

test('sanitize narrows an untrusted slug to a shipped one or null', function () {
    expect(PdfTemplateRegistry::sanitize('classic'))->toBe('classic')
        ->and(PdfTemplateRegistry::sanitize('not-a-template'))->toBeNull()
        ->and(PdfTemplateRegistry::sanitize(null))->toBeNull()
        ->and(PdfTemplateRegistry::sanitize(''))->toBeNull()
        ->and(PdfTemplateRegistry::sanitize(42))->toBeNull();
});

test('resolveUpdate keeps the current template for writers that have no picker', function () {
    // The REST API and the legacy controllers submit nothing for this
    // field — they must not clear a template the form pinned earlier.
    expect(PdfTemplateRegistry::resolveUpdate(null, 'classic'))->toBe('classic')
        ->and(PdfTemplateRegistry::resolveUpdate(null, null))->toBeNull();
});

test('resolveUpdate clears the template when the blank option is submitted', function () {
    expect(PdfTemplateRegistry::resolveUpdate('', 'classic'))->toBeNull()
        ->and(PdfTemplateRegistry::resolveUpdate('bold', 'classic'))->toBe('bold')
        ->and(PdfTemplateRegistry::resolveUpdate('retired-template', 'classic'))->toBeNull();
});

test('defaultOptionLabel names the template the blank option resolves to', function () {
    expect(PdfTemplateRegistry::defaultOptionLabel('invoice'))->toBe('Default (Modern)');

    app('laravel-crm.settings')->set('pdf_template_purchase-order', 'compact');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfTemplateRegistry::defaultOptionLabel('purchase-order'))->toBe('Default (Compact)')
        ->and(PdfTemplateRegistry::defaultOptionLabel('invoice'))->toBe('Default (Modern)');
});

test('slugFor prefers the record template over the settings default', function () {
    app('laravel-crm.settings')->set('pdf_template_invoice', 'bold');
    app('laravel-crm.settings')->forgetCache();

    $invoice = Invoice::create(['invoice_id' => 'INV-TPL-1', 'pdf_template' => 'compact']);

    expect(PdfTemplateRegistry::slugFor('invoice', $invoice))->toBe('compact')
        ->and(PdfTemplateRegistry::viewForModel('invoice', $invoice))
        ->toBe('laravel-crm::pdfs.compact.invoice');
});

test('slugFor falls back to the settings default for records with no template of their own', function () {
    app('laravel-crm.settings')->set('pdf_template_invoice', 'bold');
    app('laravel-crm.settings')->forgetCache();

    $invoice = Invoice::create(['invoice_id' => 'INV-TPL-2']);

    expect(PdfTemplateRegistry::slugFor('invoice', $invoice))->toBe('bold')
        ->and(PdfTemplateRegistry::slugFor('invoice', null))->toBe('bold')
        ->and(PdfTemplateRegistry::viewForModel('invoice', $invoice))
        ->toBe('laravel-crm::pdfs.bold.invoice');
});

test('slugFor ignores a record template that references a removed variant', function () {
    $invoice = Invoice::create(['invoice_id' => 'INV-TPL-3', 'pdf_template' => 'retired-template']);

    expect(PdfTemplateRegistry::slugFor('invoice', $invoice))->toBe('modern');
});

test('options returns id/name pairs in the registry display order', function () {
    $options = PdfTemplateRegistry::options();

    expect($options)->toHaveCount(5)
        ->and(array_column($options, 'id'))->toBe(PdfTemplateRegistry::SLUGS);

    foreach ($options as $option) {
        expect($option)->toHaveKeys(['id', 'name'])
            ->and($option['name'])->toBeString()->not->toBe('');
    }
});

test('translation keys resolve to non-empty strings for every template', function () {
    foreach (['modern', 'classic', 'bold', 'compact', 'professional'] as $slug) {
        expect(__('laravel-crm::lang.pdf_template_'.$slug))
            ->toBeString()
            ->not->toBe('laravel-crm::lang.pdf_template_'.$slug)
            ->and(__('laravel-crm::lang.pdf_template_'.$slug.'_description'))
            ->toBeString()
            ->not->toBe('laravel-crm::lang.pdf_template_'.$slug.'_description');
    }

    expect(__('laravel-crm::lang.templates'))
        ->toBeString()
        ->not->toBe('laravel-crm::lang.templates')
        ->and(__('laravel-crm::lang.pdf_template'))
        ->toBeString()
        ->not->toBe('laravel-crm::lang.pdf_template');
});
