<?php

namespace VentureDrake\LaravelCrm\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Resolves the uploaded brand logo into a form DomPDF can embed.
 *
 * The logo is uploaded to the `public` disk and the `logo_file` setting
 * holds its disk-relative path (e.g. `laravel-crm/acme.png`). A browser
 * page can render that straight through `asset('storage/'.$logo)`, but a
 * PDF cannot: DomPDF refuses to fetch http(s) URLs unless the host sets
 * `dompdf.enable_remote`, which is false by default — so every PDF came
 * out with a broken-image box where the logo should be. An unencoded
 * space in the filename compounds the failure.
 *
 * Inlining the file bytes as a `data:` URI side-steps both issues and
 * needs no host configuration. The PDF templates detect the leading
 * `data:` prefix and use the value as-is instead of prefixing it with
 * `storage/`.
 *
 * Only the PDF render paths go through here. The portal's on-screen
 * `show` views keep passing the raw path, because a browser fetches
 * `asset('storage/...')` perfectly well and inlining there would bloat
 * the HTML on every page load.
 */
class PdfLogo
{
    /**
     * Resolve the logo currently configured in settings, ready to drop
     * into a PDF view's `logo` variable.
     */
    public static function fromSettings(): ?string
    {
        return self::src(app('laravel-crm.settings')->get('logo_file', null));
    }

    /**
     * Inline `$logo` (a path relative to the `public` disk) as a data URI.
     *
     * Returns null when there is no logo, when the file has gone missing,
     * or when the disk cannot be read — the templates then fall back to
     * rendering the organisation name as text, which reads better than a
     * broken-image box.
     */
    public static function src(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($logo)) {
                return null;
            }

            $contents = $disk->get($logo);

            if ($contents === null || $contents === '') {
                return null;
            }

            $mime = $disk->mimeType($logo) ?: 'image/png';
        } catch (Throwable $e) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
