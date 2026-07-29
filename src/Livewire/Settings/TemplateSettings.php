<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings;

use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

/**
 * Livewire component for the Templates settings page.
 *
 * Renders a tabbed picker (one tab per PDF doc type) whose panels each
 * expose the 5 registered template variants as selectable cards. Persists
 * the admin's per-doc-type choice via `SettingService::set()` under the
 * `pdf_template_{docType}` key (using the same hyphenated docType names
 * as `PdfTemplateRegistry::DOC_TYPES`, matching the reader convention
 * locked in by US-005 of the pdf-templates series — the download
 * controllers, Livewire send components, and portal controllers all read
 * via `pdf_template_purchase-order` etc.).
 */
class TemplateSettings extends Component
{
    use Toast;

    /**
     * Per-doc-type selected template slug.
     *
     * Keyed by docType (invoice/order/purchase-order/delivery/quote);
     * values are the selected template slug (modern/classic/bold/
     * compact/professional).
     *
     * @var array<string, string>
     */
    public array $selected = [];

    /**
     * The currently-visible doc-type tab. Query-string-synced so bookmarks
     * (`?tab=order`) deep-link cleanly, matching the tab-state contract on
     * `/crm/users` established by US-004 of the invite-users lifecycle
     * series.
     */
    #[Url]
    public string $tab = 'invoice';

    public function mount(): void
    {
        $settings = app('laravel-crm.settings');
        $default = PdfTemplateRegistry::defaultSlug();

        foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
            $key = $this->settingKey($docType);
            $this->selected[$docType] = $settings->get($key, $default);
        }
    }

    public function select(string $docType, string $slug): void
    {
        if (! in_array($docType, PdfTemplateRegistry::DOC_TYPES, true)) {
            return;
        }

        if (! array_key_exists($slug, PdfTemplateRegistry::all())) {
            return;
        }

        $this->selected[$docType] = $slug;
    }

    public function save(): void
    {
        $settings = app('laravel-crm.settings');

        foreach (PdfTemplateRegistry::DOC_TYPES as $docType) {
            $slug = $this->selected[$docType] ?? PdfTemplateRegistry::defaultSlug();

            if (! array_key_exists($slug, PdfTemplateRegistry::all())) {
                $slug = PdfTemplateRegistry::defaultSlug();
            }

            $settings->set($this->settingKey($docType), $slug);
        }

        $settings->forgetCache();

        $this->success(
            ucfirst(trans('laravel-crm::lang.settings_updated'))
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.template-settings', [
            'docTypes' => PdfTemplateRegistry::DOC_TYPES,
            'templates' => PdfTemplateRegistry::all(),
        ]);
    }

    /**
     * Build the setting key for the given docType. Matches the hyphenated
     * key convention the download/send/portal readers use, so
     * `pdf_template_purchase-order` (not `pdf_template_purchase_order`)
     * lines up with the reader side.
     */
    protected function settingKey(string $docType): string
    {
        return 'pdf_template_'.$docType;
    }
}
