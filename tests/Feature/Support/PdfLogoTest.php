<?php

use Dompdf\Image\Cache;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Support\PdfLogo;

/*
 * The brand logo on generated PDFs.
 *
 * DomPDF will not fetch an http(s) URL unless the host opts into
 * `dompdf.enable_remote`, so passing the templates the raw storage path
 * (which they wrap in `asset('storage/...')`) produced a broken-image box
 * on every PDF. PdfLogo inlines the bytes instead, which needs no host
 * configuration.
 */

beforeEach(function () {
    Storage::fake('public');
    Setting::query()->delete();
    app('laravel-crm.settings')->forgetCache();
});

/**
 * Put a real 1x1 PNG on the public disk at $path and return its bytes.
 */
function seedLogoFile(string $path): string
{
    $bytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    Storage::disk('public')->put($path, $bytes);

    return $bytes;
}

test('src returns null when no logo is configured', function () {
    expect(PdfLogo::src(null))->toBeNull()
        ->and(PdfLogo::src(''))->toBeNull();
});

test('src inlines the file as a base64 data URI', function () {
    $bytes = seedLogoFile('laravel-crm/acme.png');

    $src = PdfLogo::src('laravel-crm/acme.png');

    expect($src)->toStartWith('data:')
        ->and($src)->toContain(';base64,')
        ->and(base64_decode(explode(';base64,', $src, 2)[1]))->toBe($bytes);
});

test('src returns null when the logo file has gone missing', function () {
    // No asset() fallback: a URL DomPDF cannot fetch renders as a broken
    // image box, whereas null lets the template fall back to the
    // organisation name as text.
    expect(PdfLogo::src('laravel-crm/deleted.png'))->toBeNull();
});

test('src handles a filename containing a space', function () {
    // The unencoded space in an asset() URL was one half of the original
    // failure; inlining sidesteps URL encoding entirely.
    seedLogoFile('laravel-crm/my company logo.png');

    expect(PdfLogo::src('laravel-crm/my company logo.png'))->toStartWith('data:');
});

test('src resolves a team-scoped logo path', function () {
    // Teams mode stores the upload under `laravel-crm/{teamId}/`, and the
    // logo_file setting carries that prefix.
    seedLogoFile('laravel-crm/7/acme.png');

    expect(PdfLogo::src('laravel-crm/7/acme.png'))->toStartWith('data:');
});

test('fromSettings reads the logo_file setting', function () {
    seedLogoFile('laravel-crm/acme.png');

    app('laravel-crm.settings')->set('logo_file', 'laravel-crm/acme.png');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfLogo::fromSettings())->toStartWith('data:');
});

test('fromSettings returns null when the setting is unset', function () {
    expect(PdfLogo::fromSettings())->toBeNull();
});

test('no PDF render site passes the raw logo_file path', function () {
    // Static regression guard. The raw path is correct for the portal's
    // on-screen `show` views and wrong for anything handed to DomPDF, and
    // the two sit a few lines apart in the same controllers — so a new PDF
    // route is easy to wire up the browser way by mistake. For every
    // `'logo' => app(...)->get('logo_file')` we look backwards for the
    // nearest enclosing render call; if it is a `loadView(` (DomPDF) rather
    // than a `view(` (Blade), the site is broken.
    $raw = "'logo' => app('laravel-crm.settings')->get('logo_file'";
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../../src', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

        foreach ($lines as $i => $line) {
            if (! str_contains($line, $raw)) {
                continue;
            }

            for ($back = $i; $back >= 0 && $back > $i - 40; $back--) {
                if (str_contains($lines[$back], 'loadView(')) {
                    $offenders[] = basename($file->getPathname()).':'.($i + 1);
                    break;
                }

                if (preg_match('/(?<!load)view\(/', $lines[$back])) {
                    break;
                }
            }
        }
    }

    expect($offenders)->toBe([], 'These PDF renders pass the raw logo path instead of PdfLogo::fromSettings(): '.implode(', ', $offenders));
});

test('DomPDF resolves the inlined logo to the real bytes with remote fetching off', function () {
    // The end of the chain, and the whole reason PdfLogo exists. Every
    // other test here asserts the *shape* of the string; this one hands it
    // to the DomPDF code that decides "embed this" vs "draw a broken-image
    // box" and checks which way it goes. `data://` carries no rules in
    // DomPDF's allowed-protocol table, so it resolves even with
    // `enable_remote` off — which is what makes the inlining work on a
    // stock host, and what would silently regress if anyone swapped the
    // data URI back for an asset() URL.
    $bytes = seedLogoFile('laravel-crm/acme.png');

    app('laravel-crm.settings')->set('logo_file', 'laravel-crm/acme.png');
    app('laravel-crm.settings')->forgetCache();

    $options = new Options;
    $options->setIsRemoteEnabled(false);

    [$resolved, $type, $message] = Cache::resolve_url(
        PdfLogo::fromSettings(),
        '',
        '',
        '',
        $options
    );

    expect($message)->toBeNull()
        ->and($resolved)->not->toBe(Cache::$broken_image)
        ->and($type)->toBe('png')
        ->and(file_get_contents($resolved))->toBe($bytes);
});

test('the resolved src is what an uploaded logo round-trips to', function () {
    // Mirrors both logo writers — SettingEdit::save() and the legacy
    // SettingController::update() — which now share the same call: the
    // upload lands on the `public` disk and the setting records the
    // disk-relative path that PdfLogo reads back.
    UploadedFile::fake()->image('brand.png')->storePubliclyAs(
        path: 'laravel-crm',
        name: 'brand.png',
        options: 'public'
    );

    app('laravel-crm.settings')->set('logo_file', 'laravel-crm/brand.png');
    app('laravel-crm.settings')->forgetCache();

    expect(PdfLogo::fromSettings())->toStartWith('data:image/');
});
