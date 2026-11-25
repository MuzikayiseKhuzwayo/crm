<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinTemplates;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;

class LinkedinTemplateIndex extends Component
{
    use AuthorizesRequests;
    use Toast;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $type = '';

    #[Url]
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function types(): array
    {
        return [
            ['id' => LinkedinTemplate::TYPE_CONNECT_NOTE, 'name' => ucfirst(__('laravel-crm::lang.linkedin_template_type_connect_note'))],
            ['id' => LinkedinTemplate::TYPE_DM, 'name' => ucfirst(__('laravel-crm::lang.linkedin_template_type_dm'))],
            ['id' => LinkedinTemplate::TYPE_FOLLOW_UP, 'name' => ucfirst(__('laravel-crm::lang.linkedin_template_type_follow_up'))],
        ];
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => ucfirst(__('laravel-crm::lang.name'))],
            ['key' => 'type', 'label' => ucfirst(__('laravel-crm::lang.type'))],
            ['key' => 'body', 'label' => ucfirst(__('laravel-crm::lang.body')), 'sortable' => false],
            ['key' => 'created_at', 'label' => ucfirst(__('laravel-crm::lang.created'))],
        ];
    }

    public function templates(): LengthAwarePaginator
    {
        return LinkedinTemplate::query()
            ->when($this->search, function (Builder $q) {
                $q->where(function (Builder $q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('body', 'like', "%{$this->search}%");
                });
            })
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(25);
    }

    public function delete(int $id): void
    {
        if ($template = LinkedinTemplate::find($id)) {
            $this->authorize('delete', $template);

            $template->delete();
            $this->success(ucfirst(__('laravel-crm::lang.linkedin_template')).' '.__('laravel-crm::lang.deleted'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.linkedin-templates.linkedin-template-index', [
            'headers' => $this->headers(),
            'templates' => $this->templates(),
            'types' => $this->types(),
        ]);
    }
}
