<?php

use Illuminate\Filesystem\Filesystem;
use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

/**
 * A PDF customised the pre-picker way keeps rendering.
 *
 * Before the template registry existed, the only way to restyle a document was
 * to `vendor:publish --tag=views` and edit
 * `resources/views/vendor/laravel-crm/invoices/pdf.blade.php`. Switching the
 * default to `modern` would have thrown that work away on upgrade, silently,
 * on a host that never asked for a new template.
 *
 * The signal has to be "published *and edited*", not "published": publishing
 * copies the whole views directory, so a host that published last week has a
 * byte-identical copy it never intended as an override.
 */
function publishedPdfPath(string $docType): string
{
    $relative = str_replace('.', '/', PdfTemplateRegistry::LEGACY_VIEWS[$docType]).'.blade.php';

    return resource_path('views/vendor/laravel-crm/'.$relative);
}

function shippedPdfPath(string $docType): string
{
    $relative = str_replace('.', '/', PdfTemplateRegistry::LEGACY_VIEWS[$docType]).'.blade.php';

    return dirname(__DIR__, 3).'/resources/views/'.$relative;
}

/** Write a published copy of a doc type's legacy view, then clean it up. */
function withPublishedPdfView(string $docType, string $contents, callable $callback): void
{
    $path = publishedPdfPath($docType);

    (new Filesystem)->ensureDirectoryExists(dirname($path));
    file_put_contents($path, $contents);

    PdfTemplateRegistry::forgetPublishedOverrides();

    try {
        $callback();
    } finally {
        unlink($path);
        PdfTemplateRegistry::forgetPublishedOverrides();
    }
}

beforeEach(function () {
    PdfTemplateRegistry::forgetPublishedOverrides();

    foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
        app('laravel-crm.settings')->set(PdfTemplateRegistry::settingKey($docType), null);
    }

    app('laravel-crm.settings')->forgetCache();
});

test('with nothing published the shipped default renders', function () {
    expect(PdfTemplateRegistry::viewForModel('invoice'))
        ->toBe('laravel-crm::pdfs.modern.invoice');
});

test('an edited published view wins over the shipped default', function () {
    withPublishedPdfView('invoice', '<p>My own invoice layout</p>', function () {
        expect(PdfTemplateRegistry::viewForModel('invoice'))
            ->toBe('laravel-crm::invoices.pdf');
    });
});

test('an untouched publish is not an override', function () {
    // vendor:publish copies every view. Treating presence as intent would pin
    // a host that published last week to the old layout forever.
    $identical = file_get_contents(shippedPdfPath('invoice'));

    withPublishedPdfView('invoice', $identical, function () {
        expect(PdfTemplateRegistry::viewForModel('invoice'))
            ->toBe('laravel-crm::pdfs.modern.invoice');
    });
});

test('a template saved in settings beats the published view', function () {
    withPublishedPdfView('invoice', '<p>My own invoice layout</p>', function () {
        app('laravel-crm.settings')->set(PdfTemplateRegistry::settingKey('invoice'), 'bold');
        app('laravel-crm.settings')->forgetCache();

        expect(PdfTemplateRegistry::viewForModel('invoice'))
            ->toBe('laravel-crm::pdfs.bold.invoice');
    });
});

test('a template picked on the record beats everything', function () {
    withPublishedPdfView('quote', '<p>My own quote layout</p>', function () {
        app('laravel-crm.settings')->set(PdfTemplateRegistry::settingKey('quote'), 'bold');
        app('laravel-crm.settings')->forgetCache();

        $record = (object) ['pdf_template' => 'compact'];

        expect(PdfTemplateRegistry::viewForModel('quote', $record))
            ->toBe('laravel-crm::pdfs.compact.quote');
    });
});

test('an unknown slug on the record does not defeat the published view', function () {
    // sanitize() rejects it, so the record has made no valid choice and the
    // host's own view is still the best answer available.
    withPublishedPdfView('order', '<p>My own order layout</p>', function () {
        $record = (object) ['pdf_template' => 'a-template-that-was-removed'];

        expect(PdfTemplateRegistry::viewForModel('order', $record))
            ->toBe('laravel-crm::orders.pdf');
    });
});

test('the fallback covers every doc type', function () {
    foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
        withPublishedPdfView($docType, '<p>Customised '.$docType.'</p>', function () use ($docType) {
            expect(PdfTemplateRegistry::viewForModel($docType))
                ->toBe('laravel-crm::'.PdfTemplateRegistry::LEGACY_VIEWS[$docType]);
        });
    }
});

test('choosing classic keeps honouring a published view', function () {
    // The documented way to keep your own layout and opt into the picker:
    // classic is a thin @include of exactly these files.
    foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
        expect(file_get_contents(dirname(__DIR__, 3).'/resources/views/pdfs/classic/'.$docType.'.blade.php'))
            ->toContain("@include('laravel-crm::".PdfTemplateRegistry::LEGACY_VIEWS[$docType]."')");
    }
});

test('every doc type has a legacy view mapping that exists', function () {
    foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
        expect(PdfTemplateRegistry::LEGACY_VIEWS)->toHaveKey($docType)
            ->and(is_file(shippedPdfPath($docType)))->toBeTrue();
    }
});
