<?php

namespace VentureDrake\LaravelCrm\Livewire\Activities;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Task;

class ActivityIndex extends Component
{
    use Toast;

    public $model = null;

    #[Computed, On('activity-logged')]
    public function activities()
    {
        $activityIds = [];

        foreach ($this->model->activities()->latest()->get() as $activity) {
            $activityIds[] = $activity->id;
        }

        if (method_exists($this->model, 'tasks') && ! ($this->model instanceof Task)) {
            $taskIds = $this->model->tasks()->pluck('id')->toArray();
            if (count($taskIds) > 0) {
                $taskActivityIds = Activity::where(function ($query) use ($taskIds) {
                    $query->whereIn('timelineable_id', $taskIds)
                        ->where(function ($q) {
                            $q->where('timelineable_type', Task::class)
                                ->orWhere('timelineable_type', (new Task)->getMorphClass());
                        });
                })->orWhere(function ($query) use ($taskIds) {
                    $query->where(function ($q) {
                        $q->where('recordable_type', Note::class)
                            ->orWhere('recordable_type', (new Note)->getMorphClass());
                    })->whereIn('recordable_id', function ($q) use ($taskIds) {
                        $q->select('id')
                            ->from(config('laravel-crm.db_table_prefix').'notes')
                            ->whereIn('noteable_id', $taskIds)
                            ->where(function ($q2) {
                                $q2->where('noteable_type', Task::class)
                                    ->orWhere('noteable_type', (new Task)->getMorphClass());
                            });
                    });
                })->pluck('id')->toArray();

                $activityIds = array_unique(array_merge($activityIds, $taskActivityIds));
            }
        }

        if (app('laravel-crm.settings')->get('show_related_activity') == 1 && method_exists($this->model, 'contacts')) {
            foreach ($this->model->contacts as $contact) {
                foreach ($contact->entityable->activities()->latest()->get() as $activity) {
                    $activityIds[] = $activity->id;
                }
            }
        }

        if (count($activityIds) > 0) {
            return Activity::whereIn('id', $activityIds)->latest()->get();
        }

        return [];
    }

    public function render()
    {
        return view('laravel-crm::livewire.activities.activity-index');
    }
}
