<?php

namespace VentureDrake\LaravelCrm\Livewire\Tasks\Traits;

use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\TaskService;
use VentureDrake\LaravelCrm\Traits\HasCustomFormFields;

trait HasTaskCommon
{
    use HasCustomFormFields;
    use Toast;

    protected TaskService $taskService;

    public $name;

    public $description;

    public $start_at;

    public $due_at;

    public $user_owner_id;

    public $user_assigned_id;

    protected function customFieldsModel(): string
    {
        return Task::class;
    }

    protected function rules()
    {
        return array_merge([
            'name' => 'required|max:255',
            'description' => 'nullable',
            'start_at' => 'nullable',
            // Only compare against a start date the user actually entered: Laravel resolves a
            // null start_at to now(), which would forbid an otherwise valid past due date.
            'due_at' => array_filter([
                'nullable',
                filled($this->start_at) ? 'after_or_equal:start_at' : null,
            ]),
            'user_owner_id' => 'nullable',
            'user_assigned_id' => 'nullable',
        ], $this->customFieldRules());
    }

    protected function messages()
    {
        return $this->customFieldMessages();
    }

    protected function validationAttributes()
    {
        return $this->customFieldValidationAttributes();
    }

    public function boot(TaskService $taskService): void
    {
        $this->taskService = $taskService;
    }
}
