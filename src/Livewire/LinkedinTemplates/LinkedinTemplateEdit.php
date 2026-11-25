<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinTemplates;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Services\LinkedinMessageRenderer;

class LinkedinTemplateEdit extends Component
{
    use AuthorizesRequests;
    use Toast;

    public LinkedinTemplate $template;

    public string $name = '';

    public string $type = LinkedinTemplate::TYPE_CONNECT_NOTE;

    public string $body = '';

    public function mount(LinkedinTemplate $template): void
    {
        $this->template = $template;
        $this->name = (string) $template->name;
        $this->type = (string) $template->type;
        $this->body = (string) $template->body;
    }

    protected function rules(): array
    {
        $maxLength = $this->type === LinkedinTemplate::TYPE_CONNECT_NOTE
            ? LinkedinMessageRenderer::CONNECT_NOTE_MAX_LENGTH
            : LinkedinMessageRenderer::DM_MAX_LENGTH;

        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', LinkedinTemplate::TYPES),
            'body' => 'required|string|max:'.$maxLength,
        ];
    }

    public function types(): array
    {
        return [
            ['id' => LinkedinTemplate::TYPE_CONNECT_NOTE, 'name' => ucfirst(__('laravel-crm::lang.linkedin_template_type_connect_note')).' (300 chars max)'],
            ['id' => LinkedinTemplate::TYPE_DM, 'name' => ucfirst(__('laravel-crm::lang.linkedin_template_type_dm'))],
            ['id' => LinkedinTemplate::TYPE_FOLLOW_UP, 'name' => ucfirst(__('laravel-crm::lang.linkedin_template_type_follow_up'))],
        ];
    }

    public function availableVariables(): array
    {
        return [
            '{{first_name}}' => 'Contact first name',
            '{{last_name}}' => 'Contact last name',
            '{{full_name}}' => 'Contact full name',
            '{{company}}' => 'Organization name',
            '{{title}}' => 'Role / lead title',
            '{{personalized_opener}}' => 'Custom per-lead opener',
        ];
    }

    public function insertVariable(string $variable): void
    {
        $separator = ($this->body !== '' && ! preg_match('/\s$/', $this->body)) ? ' ' : '';
        $this->body .= $separator.$variable;
    }

    public function getCharacterCountProperty(): int
    {
        return mb_strlen($this->body, 'UTF-8');
    }

    public function getMaxLengthProperty(): int
    {
        return $this->type === LinkedinTemplate::TYPE_CONNECT_NOTE
            ? LinkedinMessageRenderer::CONNECT_NOTE_MAX_LENGTH
            : LinkedinMessageRenderer::DM_MAX_LENGTH;
    }

    public function getRenderedPreviewProperty(): array
    {
        $renderer = app(LinkedinMessageRenderer::class);

        return $renderer->renderBody($this->body, [
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'full_name' => 'Sarah Connor',
            'company' => 'Acme Logistics',
            'title' => 'VP of Operations',
            'personalized_opener' => 'Loved your recent post on warehouse automation.',
        ], $this->type);
    }

    public function save()
    {
        $this->authorize('update', $this->template);

        $this->validate();

        $this->template->update([
            'name' => $this->name,
            'type' => $this->type,
            'body' => $this->body,
        ]);

        $this->success(
            ucfirst(__('laravel-crm::lang.linkedin_template')).' '.__('laravel-crm::lang.updated'),
            redirectTo: route('laravel-crm.linkedin-templates.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.linkedin-templates.linkedin-template-edit', [
            'types' => $this->types(),
            'variables' => $this->availableVariables(),
        ]);
    }
}
