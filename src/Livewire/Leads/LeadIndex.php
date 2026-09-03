<?php

namespace VentureDrake\LaravelCrm\Livewire\Leads;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Livewire\Traits\SearchesEncryptableContacts;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Traits\ClearsProperties;
use VentureDrake\LaravelCrm\Traits\ResetsPaginationWhenPropsChanges;

class LeadIndex extends Component
{
    use AuthorizesRequests, ClearsProperties, ResetsPaginationWhenPropsChanges, SearchesEncryptableContacts, Toast, WithPagination;

    public $layout = 'index';

    #[Url]
    public string $search = '';

    #[Url]
    public ?array $user_id = [];

    #[Url]
    public ?array $label_id = [];

    #[Url]
    public ?array $lead_source_id = [];

    #[Url]
    public ?array $pipeline_stage_id = [];

    #[Url]
    public string $lead_status = 'active';

    #[Url]
    public string $amount_preset = '';

    #[Url]
    public $min_amount = null;

    #[Url]
    public $max_amount = null;

    #[Url]
    public string $created_preset = '';

    #[Url]
    public ?string $created_from = null;

    #[Url]
    public ?string $created_to = null;

    #[Url]
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $showFilters = false;

    public function filterCount(): int
    {
        return (count((array) $this->user_id) > 0 ? 1 : 0)
            + (count((array) $this->label_id) > 0 ? 1 : 0)
            + (count((array) $this->lead_source_id) > 0 ? 1 : 0)
            + (count((array) $this->pipeline_stage_id) > 0 ? 1 : 0)
            + ($this->lead_status !== 'active' ? 1 : 0)
            + ($this->amount_preset !== '' || $this->min_amount || $this->max_amount ? 1 : 0)
            + ($this->created_preset !== '' || $this->created_from || $this->created_to ? 1 : 0);
    }

    public function users(): Collection
    {
        return User::orderBy('name')->get();
    }

    public function userFilterOptions(): array
    {
        $options = [
            ['id' => 'unassigned', 'name' => '- Unallocated / Unassigned -'],
        ];

        foreach ($this->users() as $user) {
            $options[] = ['id' => (string) $user->id, 'name' => $user->name];
        }

        return $options;
    }

    public function labels(): Collection
    {
        return Label::all();
    }

    public function leadSources(): Collection
    {
        return LeadSource::orderBy('name')->get();
    }

    public function pipelineStages(): Collection
    {
        return PipelineStage::orderBy('order')->get();
    }

    public function amountPresets(): array
    {
        return [
            ['id' => '', 'name' => 'All Lead Values'],
            ['id' => 'under_5k', 'name' => 'Under $5,000'],
            ['id' => '5k_25k', 'name' => '$5,000 - $25,000'],
            ['id' => '25k_100k', 'name' => '$25,000 - $100,000'],
            ['id' => 'over_100k', 'name' => 'Over $100,000'],
        ];
    }

    public function createdPresets(): array
    {
        return [
            ['id' => '', 'name' => 'All Time'],
            ['id' => 'today', 'name' => 'Created Today'],
            ['id' => 'yesterday', 'name' => 'Created Yesterday'],
            ['id' => 'this_week', 'name' => 'Created This Week'],
            ['id' => 'this_month', 'name' => 'Created This Month'],
        ];
    }

    public function headers(): array
    {
        return [
            ['key' => 'created_at', 'label' => ucfirst(__('laravel-crm::lang.created')), 'format' => fn ($row, $field) => $field->format('M d, Y')],
            ['key' => 'lead_id', 'label' => ucfirst(__('laravel-crm::lang.number'))],
            ['key' => 'title', 'label' => ucfirst(__('laravel-crm::lang.title'))],
            ['key' => 'labels', 'label' => ucfirst(__('laravel-crm::lang.labels')), 'format' => fn ($row, $field) => $field, 'sortable' => false],
            ['key' => 'amount', 'label' => ucfirst(__('laravel-crm::lang.value')), 'format' => fn ($row, $field) => money($field, $row->currency)],
            ['key' => 'person.name', 'label' => ucfirst(__('laravel-crm::lang.contact')), 'sortable' => false],
            ['key' => 'organization.name', 'label' => ucfirst(__('laravel-crm::lang.organization')), 'sortable' => false],
            ['key' => 'pipeline_stage', 'label' => ucfirst(__('laravel-crm::lang.stage')), 'sortable' => false],
            ['key' => 'leadSource.name', 'label' => ucfirst(__('laravel-crm::lang.source'))],
            ['key' => 'ownerUser.name', 'label' => 'Owner'],
        ];
    }

    public function leads(): LengthAwarePaginator
    {
        $prefix = config('laravel-crm.db_table_prefix');

        $query = Lead::query()
            ->select(
                "{$prefix}leads.*",
                "{$prefix}people.first_name",
                "{$prefix}people.last_name",
                "{$prefix}organizations.name as organization_name"
            )
            ->leftJoin("{$prefix}people", "{$prefix}leads.person_id", '=', "{$prefix}people.id")
            ->leftJoin("{$prefix}organizations", "{$prefix}leads.organization_id", '=', "{$prefix}organizations.id")
            ->leftJoin("{$prefix}lead_sources", "{$prefix}leads.lead_source_id", '=', "{$prefix}lead_sources.id")
            ->leftJoin("users as owner_users", "{$prefix}leads.user_owner_id", '=', "owner_users.id");

        // Converted Status Filter
        if ($this->lead_status === 'active') {
            $query->whereNull("{$prefix}leads.converted_at");
        } elseif ($this->lead_status === 'converted') {
            $query->whereNotNull("{$prefix}leads.converted_at");
        }

        // Search Filter
        $query->when($this->search, function (Builder $q) use ($prefix) {
            $term = $this->search;
            $q->where(function ($q) use ($prefix, $term) {
                $q->orWhere("{$prefix}leads.title", 'like', "%{$term}%")
                  ->orWhere("{$prefix}leads.lead_id", 'like', "%{$term}%");

                if ($this->encryptionEnabled()) {
                    if (($personIds = $this->matchingPersonIds($term))->isNotEmpty()) {
                        $q->orWhereIn("{$prefix}leads.person_id", $personIds);
                    }
                    if (($organizationIds = $this->matchingOrganizationIds($term))->isNotEmpty()) {
                        $q->orWhereIn("{$prefix}leads.organization_id", $organizationIds);
                    }
                } else {
                    $q->orWhere("{$prefix}organizations.name", 'like', "%{$term}%")
                      ->orWhere("{$prefix}people.first_name", 'like', "%{$term}%")
                      ->orWhere("{$prefix}people.last_name", 'like', "%{$term}%")
                      ->orWhereRaw("CONCAT({$prefix}people.first_name, ' ', {$prefix}people.last_name) like ?", ["%{$term}%"]);
                }
            });
        });

        // Owner Filter
        if (! empty($this->user_id)) {
            $query->where(function ($q) use ($prefix) {
                $ids = (array) $this->user_id;
                if (in_array('unassigned', $ids)) {
                    $q->orWhereNull("{$prefix}leads.user_owner_id");
                }
                $realUserIds = array_filter($ids, fn ($id) => $id !== 'unassigned');
                if (! empty($realUserIds)) {
                    $q->orWhereIn("{$prefix}leads.user_owner_id", $realUserIds);
                }
            });
        }

        // Labels Filter
        if (! empty($this->label_id)) {
            $query->whereHas('labels', fn (Builder $q) => $q->whereIn("{$prefix}labels.id", (array) $this->label_id));
        }

        // Lead Sources Filter
        if (! empty($this->lead_source_id)) {
            $query->whereIn("{$prefix}leads.lead_source_id", (array) $this->lead_source_id);
        }

        // Pipeline Stages Filter
        if (! empty($this->pipeline_stage_id)) {
            $query->whereIn("{$prefix}leads.pipeline_stage_id", (array) $this->pipeline_stage_id);
        }

        // Amount Value Filter
        if ($this->amount_preset) {
            switch ($this->amount_preset) {
                case 'under_5k':
                    $query->where("{$prefix}leads.amount", '<', 500000);
                    break;
                case '5k_25k':
                    $query->whereBetween("{$prefix}leads.amount", [500000, 2500000]);
                    break;
                case '25k_100k':
                    $query->whereBetween("{$prefix}leads.amount", [2500000, 10000000]);
                    break;
                case 'over_100k':
                    $query->where("{$prefix}leads.amount", '>', 10000000);
                    break;
            }
        }

        if ($this->min_amount !== null && $this->min_amount !== '') {
            $query->where("{$prefix}leads.amount", '>=', (float) $this->min_amount * 100);
        }

        if ($this->max_amount !== null && $this->max_amount !== '') {
            $query->where("{$prefix}leads.amount", '<=', (float) $this->max_amount * 100);
        }

        // Created Date Filter
        if ($this->created_preset) {
            switch ($this->created_preset) {
                case 'today':
                    $query->whereDate("{$prefix}leads.created_at", Carbon::today());
                    break;
                case 'yesterday':
                    $query->whereDate("{$prefix}leads.created_at", Carbon::yesterday());
                    break;
                case 'this_week':
                    $query->whereBetween("{$prefix}leads.created_at", [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereBetween("{$prefix}leads.created_at", [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
            }
        }

        if ($this->created_from) {
            $query->whereDate("{$prefix}leads.created_at", '>=', $this->created_from);
        }

        if ($this->created_to) {
            $query->whereDate("{$prefix}leads.created_at", '<=', $this->created_to);
        }

        // Sorting
        $sortCol = $this->sortBy['column'] ?? 'created_at';
        $sortDir = $this->sortBy['direction'] ?? 'desc';

        if ($sortCol === 'leadSource.name') {
            $query->orderBy("{$prefix}lead_sources.name", $sortDir);
        } elseif ($sortCol === 'ownerUser.name') {
            $query->orderBy('owner_users.name', $sortDir);
        } elseif (in_array($sortCol, ['created_at', 'amount', 'title', 'lead_id'])) {
            $query->orderBy("{$prefix}leads.{$sortCol}", $sortDir);
        } else {
            $query->orderBy("{$prefix}leads.created_at", 'desc');
        }

        return $query->paginate(25);
    }

    public function clear(): void
    {
        $this->reset([
            'user_id',
            'label_id',
            'lead_source_id',
            'pipeline_stage_id',
            'lead_status',
            'amount_preset',
            'min_amount',
            'max_amount',
            'created_preset',
            'created_from',
            'created_to',
        ]);
        $this->resetPage();
    }

    public function delete($id)
    {
        if ($lead = Lead::find($id)) {
            $this->authorize('delete', $lead);

            $lead->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.lead_deleted')));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.leads.lead-index', [
            'users' => $this->users(),
            'userFilterOptions' => $this->userFilterOptions(),
            'labels' => $this->labels(),
            'leadSources' => $this->leadSources(),
            'pipelineStages' => $this->pipelineStages(),
            'amountPresets' => $this->amountPresets(),
            'createdPresets' => $this->createdPresets(),
            'filterCount' => $this->filterCount(),
            'headers' => $this->headers(),
            'leads' => $this->leads(),
        ]);
    }
}
