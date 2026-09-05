<?php

namespace VentureDrake\LaravelCrm\Livewire\Activities;

use App\User;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Call;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\File;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Lunch;
use VentureDrake\LaravelCrm\Models\Meeting;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Traits\ResetsPaginationWhenPropsChanges;

class ActivityFeed extends Component
{
    use ResetsPaginationWhenPropsChanges, WithPagination;

    #[Url]
    public string $scope = 'all';

    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $entityType = null;

    #[Url]
    public ?int $user_id = null;

    #[Url]
    public string $datePreset = '';

    protected array $activityTypes = [
        'notes' => Note::class,
        'tasks' => Task::class,
        'calls' => Call::class,
        'meetings' => Meeting::class,
        'lunches' => Lunch::class,
        'files' => File::class,
    ];

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedEntityType(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function updatedDatePreset(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'entityType', 'user_id', 'datePreset']);
        $this->resetPage();
    }

    public function entityTypeOptions(): array
    {
        return [
            ['id' => '', 'name' => 'All Entities'],
            ['id' => Lead::class, 'name' => 'Leads'],
            ['id' => Deal::class, 'name' => 'Deals'],
            ['id' => Person::class, 'name' => 'People'],
            ['id' => Organization::class, 'name' => 'Organizations'],
            ['id' => Task::class, 'name' => 'Tasks'],
        ];
    }

    public function userOptions()
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    public function datePresets(): array
    {
        return [
            ['id' => '', 'name' => 'All Time'],
            ['id' => 'today', 'name' => 'Logged Today'],
            ['id' => 'yesterday', 'name' => 'Logged Yesterday'],
            ['id' => 'this_week', 'name' => 'Logged This Week'],
            ['id' => 'this_month', 'name' => 'Logged This Month'],
        ];
    }

    protected function scopedQuery()
    {
        $query = Activity::query()
            ->with(['causeable', 'timelineable', 'recordable']);

        if ($this->scope === 'mine') {
            $query->where('causeable_id', auth()->id())
                ->where('causeable_type', auth()->user()->getMorphClass());
        }

        if ($this->user_id) {
            $query->where('causeable_id', $this->user_id);
        }

        if ($this->entityType) {
            $query->where('timelineable_type', $this->entityType);
        }

        if ($this->datePreset) {
            switch ($this->datePreset) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', Carbon::yesterday());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
            }
        }

        return $query->latest();
    }

    #[Computed]
    public function activities()
    {
        $query = $this->scopedQuery();

        if ($this->tab !== 'all' && isset($this->activityTypes[$this->tab])) {
            $query->where('recordable_type', $this->activityTypes[$this->tab]);
        }

        return $query->paginate(25);
    }

    public function render()
    {
        return view('laravel-crm::livewire.activities.activity-feed', [
            'entityTypeOptions' => $this->entityTypeOptions(),
            'userOptions' => $this->userOptions(),
            'datePresets' => $this->datePresets(),
        ]);
    }
}
