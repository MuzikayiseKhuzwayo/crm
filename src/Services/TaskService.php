<?php

namespace VentureDrake\LaravelCrm\Services;

use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Repositories\TaskRepository;

class TaskService
{
    /**
     * @var TaskRepository
     */
    private $taskRepository;

    /**
     * TaskService constructor.
     */
    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function create($request)
    {
        return Task::create([
            'name' => $request->name,
            'description' => $request->description,
            'start_at' => $request->start_at,
            'due_at' => $request->due_at,
            'user_owner_id' => $request->user_owner_id,
            'user_assigned_id' => $request->user_assigned_id,
        ]);
    }

    public function update($request, Task $task)
    {
        $task->update([
            'name' => $request->name,
            'description' => $request->description,
            'start_at' => $request->start_at,
            'due_at' => $request->due_at,
            'user_owner_id' => $request->user_owner_id,
            'user_assigned_id' => $request->user_assigned_id,
        ]);

        return $task;
    }

    /**
     * Standard intensity presets with day offsets.
     */
    public const INTENSITY_PRESETS = [
        'light_1' => ['days' => 1, 'label' => 'Light (+1 Day - Tomorrow)', 'icon' => 'o-clock'],
        'light_2' => ['days' => 2, 'label' => 'Light (+2 Days - 48h)', 'icon' => 'o-clock'],
        'medium_3' => ['days' => 3, 'label' => 'Medium (+3 Days)', 'icon' => 'o-bolt'],
        'moderate_5' => ['days' => 5, 'label' => 'Moderate (+5 Days - 1 Wk)', 'icon' => 'o-calendar'],
        'deep_7' => ['days' => 7, 'label' => 'Deep (+7 Days)', 'icon' => 'o-calendar-days'],
    ];

    /**
     * Get intensity presets.
     */
    public function getIntensityPresets(): array
    {
        return self::INTENSITY_PRESETS;
    }

    /**
     * Rebase a task's schedule starting from now/today and pushing due_at forward by $days based on intensity.
     */
    public function rebaseDeadline(Task $task, int $days = 1): Task
    {
        return $task->rebaseDeadline($days);
    }
}
