<?php

namespace VentureDrake\LaravelCrm\Livewire\Tasks;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use VentureDrake\LaravelCrm\Livewire\Tasks\Traits\HasTaskCommon;
use VentureDrake\LaravelCrm\Models\Task;

class TaskCreate extends Component
{
    use AuthorizesRequests, HasTaskCommon;

    public function mount()
    {
        $this->user_owner_id = auth()->user()->id;
        $this->user_assigned_id = auth()->user()->id;
    }

    public function save()
    {
        $this->authorize('create', Task::class);

        $this->validate();

        $request = \VentureDrake\LaravelCrm\Http\Helpers\PublicProperties\asRequest($this);

        $task = $this->taskService->create($request);

        $this->saveCustomFields($task);

        $this->success(
            ucfirst(trans('laravel-crm::lang.task_created')),
            redirectTo: route('laravel-crm.tasks.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.tasks.task-create');
    }
}
