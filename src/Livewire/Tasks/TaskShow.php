<?php

namespace VentureDrake\LaravelCrm\Livewire\Tasks;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Task;

class TaskShow extends Component
{
    use AuthorizesRequests, Toast;

    public Task $task;

    public function mount(Task $task)
    {
        $this->task = $task;
    }

    public function complete(): void
    {
        $this->authorize('update', $this->task);

        $this->task->update(['completed_at' => now()]);

        $this->success(ucfirst(trans('laravel-crm::lang.task_completed')));
    }

    public function delete($id): void
    {
        if ($task = Task::find($id)) {
            $this->authorize('delete', $task);

            $task->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.task_deleted')), redirectTo: route('laravel-crm.tasks.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.tasks.task-show');
    }
}
