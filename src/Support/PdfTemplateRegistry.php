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

    /**
     * The settings key holding the admin-chosen default template for
     * `$docType`. Hyphenated doc types keep their hyphen
     * (`pdf_template_purchase-order`) — the convention the settings
     * screen writes with.
     */
    public static function settingKey(string $docType): string
    {
        return 'pdf_template_'.$docType;
    }

    /**
     * The admin-chosen default template slug for `$docType`, falling back
     * to `defaultSlug()` when nothing has been saved in settings (or the
     * saved value references a template that no longer ships).
     */
    public static function defaultFor(string $docType): string
    {
        $slug = app('laravel-crm.settings')->get(self::settingKey($docType), self::defaultSlug());

        return array_key_exists($slug, self::all()) ? $slug : self::defaultSlug();
    }

    /**
     * Narrow an untrusted slug down to one this package actually ships,
     * or null. Used on the write path so a record never persists a
     * template that can't be rendered.
     *
     * A null return is meaningful rather than a failure: it is how a
     * record says "follow the Settings → Templates default".
     *
     * @param  mixed  $slug
     */
    public static function sanitize($slug): ?string
    {
        return (is_string($slug) && array_key_exists($slug, self::all())) ? $slug : null;
    }

    /**
     * The `pdf_template` to persist when updating a record.
     *
     * Only the document forms carry the picker. Every other writer — the
     * REST API, the legacy controllers, the multi-purchase-order split —
     * submits nothing at all for this field, and must leave whatever the
     * record already carries alone rather than silently clearing it.
     *
     * A form that submits the blank option sends '' (not null), which is
     * an explicit request to go back to tracking the settings default, so
     * that clears the column.
     *
     * @param  mixed  $submitted  the submitted value, or null when the writer has no picker
     */
    public static function resolveUpdate($submitted, ?string $current): ?string
    {
        return $submitted === null ? $current : self::sanitize($submitted);
    }

    /**
     * Resolve the template slug for a single record: the template picked
     * on the record itself when it has one, otherwise the settings
     * default for `$docType`. Records created before the per-record
     * picker existed carry a null `pdf_template` and so keep tracking
     * whatever settings says.
     *
     * @param  object|null  $model  an Invoice/Order/PurchaseOrder/Delivery/Quote
     */
    public static function slugFor(string $docType, $model = null): string
    {
        $slug = $model->pdf_template ?? null;

        if ($slug && array_key_exists($slug, self::all())) {
            return $slug;
        }

        return self::defaultFor($docType);
    }

    /**
     * Resolve the Blade view path for a single record — the per-record
     * template when set, else the settings default for `$docType`.
     *
     * @param  object|null  $model  an Invoice/Order/PurchaseOrder/Delivery/Quote
     */
    public static function viewForModel(string $docType, $model = null): string
    {
        return self::viewFor($docType, self::slugFor($docType, $model));
    }

    /**
     * Template list shaped for a `<x-mary-select>` — `id`/`name` pairs in
     * the registry's display order.
     *
     * The blank "follow the settings default" choice is not in here; it is
     * rendered as the select's placeholder option, labelled by
     * `defaultOptionLabel()`.
     *
     * @return array<int, array{id:string, name:string}>
     */
    public static function options(): array
    {
        return array_values(array_map(fn (array $template) => [
            'id' => $template['slug'],
            'name' => $template['label'],
        ], self::all()));
    }

    /**
     * Label for the picker's blank option — the one that leaves a record
     * tracking Settings → Templates. Names the template that choice
     * currently resolves to, e.g. "Default (Bold)", so the picker still
     * tells you what you are going to get.
     */
    public static function defaultOptionLabel(string $docType): string
    {
        return ucfirst(__('laravel-crm::lang.pdf_template_use_default', [
            'template' => self::all()[self::defaultFor($docType)]['label'],
        ]));
    }
}
