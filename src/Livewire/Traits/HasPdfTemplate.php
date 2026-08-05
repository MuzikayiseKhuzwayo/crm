<?php

namespace VentureDrake\LaravelCrm\Livewire\Traits;

use Illuminate\Validation\Rule;
use VentureDrake\LaravelCrm\Support\PdfTemplateRegistry;

/**
 * Adds the per-record "PDF template" picker to a document's create/edit
 * form.
 *
 * The picker holds the record's own `pdf_template` column, and nothing
 * else: '' means the record has not pinned a template and so follows the
 * admin-chosen default in Settings → Templates, which is what the blank
 * option is labelled with (see
 * `PdfTemplateRegistry::defaultOptionLabel()`). Seeding it with the
 * resolved default instead would pin that template onto every record the
 * moment its form was saved — including records that predate the picker
 * and every unrelated edit — quietly cutting them loose from settings.
 *
 * A pinned slug is persisted by the matching service so every later
 * download, send, and portal render of that record uses it — see
 * `PdfTemplateRegistry::viewForModel()`. Picking the blank option again
 * clears the column and hands the record back to settings.
 */
trait HasPdfTemplate
{
    public $pdf_template = '';

    /**
     * The doc type this component's records render as — one of
     * `PdfTemplateRegistry::DOC_TYPES`.
     *
     * Public so the form blade can reach it: the picker's blank option is
     * labelled per doc type, and having the blade ask the component keeps
     * that one literal in the component rather than repeating it in every
     * partial that renders the picker.
     */
    abstract public function pdfTemplateDocType(): string;

    /**
     * Seed the picker. Pass the record when editing; omit it when
     * creating, where there is nothing pinned yet.
     *
     * @param  object|null  $model
     */
    protected function mountPdfTemplate($model = null): void
    {
        $this->pdf_template = PdfTemplateRegistry::sanitize($model->pdf_template ?? null) ?? '';
    }

    /**
     * The picker's options — the shipped templates, in display order.
     *
     * @return array<int, array{id:string, name:string}>
     */
    public function pdfTemplateOptions(): array
    {
        return PdfTemplateRegistry::options();
    }

    /**
     * Label for the picker's blank option — the one that leaves the record
     * tracking Settings → Templates, named for the template that choice
     * currently resolves to.
     */
    public function pdfTemplateDefaultLabel(): string
    {
        return PdfTemplateRegistry::defaultOptionLabel($this->pdfTemplateDocType());
    }

    /**
     * '' is a valid pick — it is the blank "follow the settings default"
     * option, which the services turn back into a null column.
     *
     * @return array<string, mixed>
     */
    protected function pdfTemplateRules(): array
    {
        return [
            'pdf_template' => ['nullable', 'string', Rule::in(array_merge([''], PdfTemplateRegistry::SLUGS))],
        ];
    }
}
