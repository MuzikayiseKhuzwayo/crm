<?php

namespace VentureDrake\LaravelCrm\Support;

/**
 * Central registry describing the shipped PDF templates.
 *
 * Provides a single source of truth for the 5 template variants that any of
 * the 5 document types (invoice, order, purchase-order, delivery, quote)
 * can be rendered against. Downstream code resolves a Blade view path via
 * `viewFor($docType, $slug)` — unknown slugs fall back to `defaultSlug()`
 * gracefully so a persisted template preference that references a removed
 * variant never 500s the download route.
 */
class PdfTemplateRegistry
{
    public const DEFAULT_SLUG = 'modern';

    /**
     * The 5 document types PDFs can be rendered for.
     *
     * @var array<int, string>
     */
    public const DOC_TYPES = [
        'invoice',
        'order',
        'purchase-order',
        'delivery',
        'quote',
    ];

    /**
     * The 5 shipped template slugs, in picker display order.
     *
     * @var array<int, string>
     */
    public const SLUGS = [
        'modern',
        'classic',
        'bold',
        'compact',
        'professional',
    ];

    /**
     * Public-relative directory holding the picker thumbnails.
     */
    public const THUMBNAIL_DIR = 'vendor/laravel-crm/img/pdf-templates';

    /**
     * The default template slug — always resolvable via `viewFor(...)` for
     * every doc type, and used as the fallback when a caller passes an
     * unknown slug.
     */
    public static function defaultSlug(): string
    {
        return self::DEFAULT_SLUG;
    }

    /**
     * Metadata for every shipped template.
     *
     * Keyed by slug; each entry carries `slug`, `label`, `description`, and
     * a `thumbnail` path (relative to the host's public/ directory) suitable
     * for rendering a preview picker.
     *
     * @return array<string, array{slug:string, label:string, description:string, thumbnail:string}>
     */
    public static function all(): array
    {
        $entries = [];

        foreach (self::SLUGS as $slug) {
            $entries[$slug] = [
                'slug' => $slug,
                'label' => ucfirst(__('laravel-crm::lang.pdf_template_'.$slug)),
                'description' => __('laravel-crm::lang.pdf_template_'.$slug.'_description'),
                'thumbnail' => self::THUMBNAIL_DIR.'/'.$slug.'.svg',
            ];
        }

        return $entries;
    }

    /**
     * Resolve the on-disk path of a template's picker thumbnail, or null
     * when the slug is unknown / the file is missing.
     *
     * Checks the host's published copy first so an app that has overridden
     * the shipped artwork keeps its override, then falls back to the copy
     * inside the package. The fallback is what makes the picker work on a
     * host whose `vendor:publish --tag=assets` predates this artwork — the
     * thumbnails ship with the package, so requiring a re-publish just to
     * see them silently degraded the picker to text-only placeholders.
     */
    public static function thumbnailFile(string $slug): ?string
    {
        if (! in_array($slug, self::SLUGS, true)) {
            return null;
        }

        $relative = self::THUMBNAIL_DIR.'/'.$slug.'.svg';

        $candidates = [
            public_path($relative),
            __DIR__.'/../../public/'.$relative,
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Resolve the Blade view path for `$docType` rendered with template
     * `$slug`. Unknown slugs fall back to `defaultSlug()`.
     *
     * @param  string  $docType  one of self::DOC_TYPES
     * @param  string  $slug  one of the keys returned by all()
     */
    public static function viewFor(string $docType, string $slug): string
    {
        $resolved = array_key_exists($slug, self::all()) ? $slug : self::defaultSlug();

        return 'laravel-crm::pdfs.'.$resolved.'.'.$docType;
    }
}
