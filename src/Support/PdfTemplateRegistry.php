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

        foreach (['modern', 'classic', 'bold', 'compact', 'professional'] as $slug) {
            $entries[$slug] = [
                'slug' => $slug,
                'label' => ucfirst(__('laravel-crm::lang.pdf_template_'.$slug)),
                'description' => __('laravel-crm::lang.pdf_template_'.$slug.'_description'),
                'thumbnail' => 'vendor/laravel-crm/img/pdf-templates/'.$slug.'.svg',
            ];
        }

        return $entries;
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
