<?php

use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

test('defaultSlug returns modern', function () {
    expect(PdfTemplateRegistry::defaultSlug())->toBe('modern');
});

test('all() returns 5 template entries with slug label description thumbnail', function () {
    $templates = PdfTemplateRegistry::all();

    expect($templates)
        ->toBeArray()
        ->toHaveCount(5)
        ->toHaveKeys(['modern', 'classic', 'minimal', 'compact', 'professional']);

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

test('translation keys resolve to non-empty strings for every template', function () {
    foreach (['modern', 'classic', 'minimal', 'compact', 'professional'] as $slug) {
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
