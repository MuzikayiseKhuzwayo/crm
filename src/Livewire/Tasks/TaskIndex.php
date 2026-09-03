<?php

namespace VentureDrake\LaravelCrm\Livewire\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Traits\ClearsProperties;
use VentureDrake\LaravelCrm\Traits\ResetsPaginationWhenPropsChanges;

class TaskIndex extends Component
{
    use AuthorizesRequests, ClearsProperties, ResetsPaginationWhenPropsChanges, Toast, WithPagination;

    public $layout = 'index';

    #[Url]
    public string $search = '';

    #[Url]
    public ?array $user_id = [];

    #[Url]
    public ?array $created_by_id = [];

    #[Url]
    public ?array $lead_id = [];

    #[Url]
    public ?string $status = null;

    #[Url]
    public ?string $taskable_type = null;

    #[Url]
    public ?string $due_preset = null;

    #[Url]
    public ?string $due_from = null;

    #[Url]
    public ?string $due_to = null;

    #[Url]
    public ?string $created_preset = null;

    #[Url]
    public ?string $created_from = null;

    #[Url]
    public ?string $created_to = null;

    #[Url]
    public ?string $assigned_preset = null;

    #[Url]
    public ?string $assigned_from = null;

    #[Url]
    public ?string $assigned_to = null;

    #[Url]
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $showFilters = false;

    public function filterCount(): int
    {
        return (!empty($this->user_id) ? 1 : 0)
            + (!empty($this->created_by_id) ? 1 : 0)
            + (!empty($this->lead_id) ? 1 : 0)
            + ($this->status ? 1 : 0)
            + ($this->taskable_type ? 1 : 0)
            + ($this->due_preset ? 1 : 0)
            + ($this->due_from || $this->due_to ? 1 : 0)
            + ($this->created_preset ? 1 : 0)
            + ($this->created_from || $this->created_to ? 1 : 0)
            + ($this->assigned_preset ? 1 : 0)
            + ($this->assigned_from || $this->assigned_to ? 1 : 0);
    }

    public function users(): Collection
    {
        return User::orderBy('name')->get();
    }

    public function userOptions(): array
    {
        $options = [
            ['id' => 'unassigned', 'name' => '- ' . ucfirst(__('laravel-crm::lang.unallocated')) . ' -'],
        ];

        foreach ($this->users() as $user) {
            $options[] = ['id' => (string)$user->id, 'name' => $user->name];
        }

        return $options;
    }

    public function leads(): Collection
    {
        return Lead::orderBy('title')->get();
    }

    public function duePresets(): array
    {
        return [
            ['id' => '', 'name' => 'All Due Dates'],
            ['id' => 'overdue', 'name' => 'Overdue Tasks'],
            ['id' => 'today', 'name' => 'Due Today'],
            ['id' => 'tomorrow', 'name' => 'Due Tomorrow'],
            ['id' => 'this_week', 'name' => 'Due This Week'],
            ['id' => 'next_week', 'name' => 'Due Next Week'],
            ['id' => 'has_due_date', 'name' => 'Has Due Date'],
            ['id' => 'no_due_date', 'name' => 'No Due Date'],
            ['id' => 'custom', 'name' => 'Custom Date Range'],
        ];
    }

    public function createdPresets(): array
    {
        return [
            ['id' => '', 'name' => 'All Created Dates'],
            ['id' => 'today', 'name' => 'Created Today'],
            ['id' => 'yesterday', 'name' => 'Created Yesterday'],
            ['id' => 'this_week', 'name' => 'Created This Week'],
            ['id' => 'this_month', 'name' => 'Created This Month'],
            ['id' => 'custom', 'name' => 'Custom Date Range'],
        ];
    }

    public function assignedPresets(): array
    {
        return [
            ['id' => '', 'name' => 'All Assigned Dates'],
            ['id' => 'today', 'name' => 'Assigned Today'],
            ['id' => 'this_week', 'name' => 'Assigned This Week'],
            ['id' => 'this_month', 'name' => 'Assigned This Month'],
            ['id' => 'custom', 'name' => 'Custom Date Range'],
        ];
    }

    public function entityTypeOptions(): array
    {
        return [
            ['id' => '', 'name' => 'All Related Entities'],
            ['id' => Lead::class, 'name' => ucfirst(__('laravel-crm::lang.lead'))],
            ['id' => \VentureDrake\LaravelCrm\Models\Deal::class, 'name' => ucfirst(__('laravel-crm::lang.deal'))],
            ['id' => \VentureDrake\LaravelCrm\Models\Person::class, 'name' => ucfirst(__('laravel-crm::lang.contact'))],
            ['id' => \VentureDrake\LaravelCrm\Models\Organization::class, 'name' => ucfirst(__('laravel-crm::lang.organization'))],
        ];
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => ucfirst(__('laravel-crm::lang.task'))],
            ['key' => 'due_at', 'label' => ucfirst(__('laravel-crm::lang.due'))],
            ['key' => 'created_at', 'label' => ucfirst(__('laravel-crm::lang.created'))],
            ['key' => 'assigned_user_name', 'label' => ucfirst(__('laravel-crm::lang.assigned_to'))],
            ['key' => 'updated_at', 'label' => 'Assigned / Updated'],
            ['key' => 'lead_title', 'label' => ucfirst(__('laravel-crm::lang.lead'))],
            ['key' => 'completed_at', 'label' => ucfirst(__('laravel-crm::lang.status'))],
        ];
    }

    public function tasks(): LengthAwarePaginator
    {
        $prefix = config('laravel-crm.db_table_prefix');

        $query = Task::query()
            ->select($prefix . 'tasks.*')
            ->leftJoin('users as assigned_users', $prefix . 'tasks.user_assigned_id', '=', 'assigned_users.id')
            ->leftJoin('users as created_users', $prefix . 'tasks.user_created_id', '=', 'created_users.id')
            ->leftJoin($prefix . 'leads as related_leads', function ($join) use ($prefix) {
                $join->on($prefix . 'tasks.taskable_id', '=', 'related_leads.id')
                    ->where($prefix . 'tasks.taskable_type', '=', Lead::class);
            })
            ->with(['taskable', 'ownerUser', 'assignedToUser', 'createdByUser']);

        // 1. Search Filter
        if ($this->search) {
            $term = $this->search;
            $query->where(function (Builder $q) use ($prefix, $term) {
                $q->where($prefix . 'tasks.name', 'like', "%{$term}%")
                    ->orWhere($prefix . 'tasks.description', 'like', "%{$term}%")
                    ->orWhere('assigned_users.name', 'like', "%{$term}%")
                    ->orWhere('created_users.name', 'like', "%{$term}%")
                    ->orWhere('related_leads.title', 'like', "%{$term}%");
            });
        }

        // 2. Status Filter
        if ($this->status === 'completed') {
            $query->whereNotNull($prefix . 'tasks.completed_at');
        } elseif ($this->status === 'pending') {
            $query->whereNull($prefix . 'tasks.completed_at');
        } elseif ($this->status === 'overdue') {
            $query->whereNull($prefix . 'tasks.completed_at')
                ->whereNotNull($prefix . 'tasks.due_at')
                ->where($prefix . 'tasks.due_at', '<', now());
        }

        // 3. Entity Type Filter
        if ($this->taskable_type) {
            $query->where($prefix . 'tasks.taskable_type', $this->taskable_type);
        }

        // 4. Assigned User Filter
        if (!empty($this->user_id)) {
            $hasUnassigned = in_array('unassigned', $this->user_id);
            $userIds = array_values(array_filter($this->user_id, fn ($id) => $id !== 'unassigned'));

            $query->where(function (Builder $q) use ($prefix, $hasUnassigned, $userIds) {
                if (!empty($userIds)) {
                    $q->whereIn($prefix . 'tasks.user_assigned_id', $userIds);
                }
                if ($hasUnassigned) {
                    if (!empty($userIds)) {
                        $q->orWhereNull($prefix . 'tasks.user_assigned_id');
                    } else {
                        $q->whereNull($prefix . 'tasks.user_assigned_id');
                    }
                }
            });
        }

        // 5. Created By User Filter
        if (!empty($this->created_by_id)) {
            $query->whereIn($prefix . 'tasks.user_created_id', $this->created_by_id);
        }

        // 6. Lead Filter
        if (!empty($this->lead_id)) {
            $query->where($prefix . 'tasks.taskable_type', Lead::class)
                ->whereIn($prefix . 'tasks.taskable_id', $this->lead_id);
        }

        // 7. Due Date Filter
        if ($this->due_preset === 'overdue') {
            $query->whereNull($prefix . 'tasks.completed_at')
                ->whereNotNull($prefix . 'tasks.due_at')
                ->where($prefix . 'tasks.due_at', '<', now());
        } elseif ($this->due_preset === 'today') {
            $query->whereDate($prefix . 'tasks.due_at', today());
        } elseif ($this->due_preset === 'tomorrow') {
            $query->whereDate($prefix . 'tasks.due_at', now()->addDay());
        } elseif ($this->due_preset === 'this_week') {
            $query->whereBetween($prefix . 'tasks.due_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->due_preset === 'next_week') {
            $query->whereBetween($prefix . 'tasks.due_at', [now()->addWeek()->startOfWeek(), now()->addWeek()->endOfWeek()]);
        } elseif ($this->due_preset === 'has_due_date') {
            $query->whereNotNull($prefix . 'tasks.due_at');
        } elseif ($this->due_preset === 'no_due_date') {
            $query->whereNull($prefix . 'tasks.due_at');
        }

        if ($this->due_from) {
            $query->whereDate($prefix . 'tasks.due_at', '>=', $this->due_from);
        }
        if ($this->due_to) {
            $query->whereDate($prefix . 'tasks.due_at', '<=', $this->due_to);
        }

        // 8. Created Date Filter
        if ($this->created_preset === 'today') {
            $query->whereDate($prefix . 'tasks.created_at', today());
        } elseif ($this->created_preset === 'yesterday') {
            $query->whereDate($prefix . 'tasks.created_at', now()->subDay());
        } elseif ($this->created_preset === 'this_week') {
            $query->whereBetween($prefix . 'tasks.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->created_preset === 'this_month') {
            $query->whereBetween($prefix . 'tasks.created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        if ($this->created_from) {
            $query->whereDate($prefix . 'tasks.created_at', '>=', $this->created_from);
        }
        if ($this->created_to) {
            $query->whereDate($prefix . 'tasks.created_at', '<=', $this->created_to);
        }

        // 9. Assigned Date Filter
        if ($this->assigned_preset === 'today') {
            $query->whereNotNull($prefix . 'tasks.user_assigned_id')
                ->whereDate($prefix . 'tasks.updated_at', today());
        } elseif ($this->assigned_preset === 'this_week') {
            $query->whereNotNull($prefix . 'tasks.user_assigned_id')
                ->whereBetween($prefix . 'tasks.updated_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->assigned_preset === 'this_month') {
            $query->whereNotNull($prefix . 'tasks.user_assigned_id')
                ->whereBetween($prefix . 'tasks.updated_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        if ($this->assigned_from) {
            $query->whereNotNull($prefix . 'tasks.user_assigned_id')
                ->whereDate($prefix . 'tasks.updated_at', '>=', $this->assigned_from);
        }
        if ($this->assigned_to) {
            $query->whereNotNull($prefix . 'tasks.user_assigned_id')
                ->whereDate($prefix . 'tasks.updated_at', '<=', $this->assigned_to);
        }

        // 10. Sorting
        $sortCol = $this->sortBy['column'] ?? 'created_at';
        $sortDir = strtolower($this->sortBy['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortCol === 'assigned_user_name') {
            $query->orderBy('assigned_users.name', $sortDir);
        } elseif ($sortCol === 'lead_title') {
            $query->orderBy('related_leads.title', $sortDir);
        } elseif (in_array($sortCol, ['name', 'due_at', 'created_at', 'completed_at', 'updated_at'])) {
            $query->orderBy($prefix . 'tasks.' . $sortCol, $sortDir);
        } else {
            $query->orderBy($prefix . 'tasks.created_at', 'desc');
        }

        return $query->paginate(25);
    }

    public function delete($id): void
    {
        if ($task = Task::find($id)) {
            $this->authorize('delete', $task);

            $task->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.task_deleted')));
        }
    }

    public function complete($id): void
    {
        if ($task = Task::find($id)) {
            $this->authorize('update', $task);

            $task->update(['completed_at' => now()]);

            $this->success(ucfirst(trans('laravel-crm::lang.task_completed')));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.tasks.task-index', [
            'users' => $this->users(),
            'userOptions' => $this->userOptions(),
            'leads' => $this->leads(),
            'duePresets' => $this->duePresets(),
            'createdPresets' => $this->createdPresets(),
            'assignedPresets' => $this->assignedPresets(),
            'entityTypeOptions' => $this->entityTypeOptions(),
            'filterCount' => $this->filterCount(),
            'headers' => $this->headers(),
            'tasks' => $this->tasks(),
        ]);
    }
}
