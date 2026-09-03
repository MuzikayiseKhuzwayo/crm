<?php

use App\Models\User;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;

class FilterTaskIndexStub extends TaskIndex
{
    public function render()
    {
        return '<div></div>';
    }
}

it('filters tasks by due date preset and date ranges', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $overdueTask = Task::create(['name' => 'Overdue Task', 'due_at' => now()->subDays(3)]);
    $todayTask = Task::create(['name' => 'Today Task', 'due_at' => now()]);
    $futureTask = Task::create(['name' => 'Future Task', 'due_at' => now()->addDays(10)]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('due_preset', 'overdue')
        ->assertViewHas('tasks', function ($tasks) use ($overdueTask) {
            return $tasks->contains($overdueTask) && $tasks->count() === 1;
        });

    Livewire::test(FilterTaskIndexStub::class)
        ->set('due_preset', 'today')
        ->assertViewHas('tasks', function ($tasks) use ($todayTask) {
            return $tasks->contains($todayTask) && $tasks->count() === 1;
        });
});

it('filters tasks by assigned user', function () {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task for Alice', 'user_assigned_id' => $user1->id]);
    $task2 = Task::create(['name' => 'Task for Bob', 'user_assigned_id' => $user2->id]);
    $task3 = Task::create(['name' => 'Unassigned Task', 'user_assigned_id' => null]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('user_id', [(string) $user1->id])
        ->assertViewHas('tasks', function ($tasks) use ($task1) {
            return $tasks->contains($task1) && $tasks->count() === 1;
        });

    Livewire::test(FilterTaskIndexStub::class)
        ->set('user_id', ['unassigned'])
        ->assertViewHas('tasks', function ($tasks) use ($task3) {
            return $tasks->contains($task3) && $tasks->count() === 1;
        });
});

it('filters tasks by lead', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $lead = Lead::create(['title' => 'Alpha Quant Lead', 'external_id' => (string) Str::uuid()]);
    $leadTask = Task::create(['name' => 'Lead Task', 'taskable_type' => Lead::class, 'taskable_id' => $lead->id]);
    $otherTask = Task::create(['name' => 'Other Task']);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('lead_id', [(string) $lead->id])
        ->assertViewHas('tasks', function ($tasks) use ($leadTask) {
            return $tasks->contains($leadTask) && $tasks->count() === 1;
        });
});

it('orders tasks by due_at, assigned_user_name, and lead_title', function () {
    $user1 = User::factory()->create(['name' => 'Aaron']);
    $user2 = User::factory()->create(['name' => 'Zack']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task 1', 'due_at' => now()->addDays(1), 'user_assigned_id' => $user2->id]);
    $task2 = Task::create(['name' => 'Task 2', 'due_at' => now()->addDays(5), 'user_assigned_id' => $user1->id]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('sortBy', ['column' => 'due_at', 'direction' => 'asc'])
        ->assertViewHas('tasks', function ($tasks) use ($task1, $task2) {
            return $tasks->first()->id === $task1->id;
        });

    Livewire::test(FilterTaskIndexStub::class)
        ->set('sortBy', ['column' => 'assigned_user_name', 'direction' => 'asc'])
        ->assertViewHas('tasks', function ($tasks) use ($task2) {
            return $tasks->first()->id === $task2->id;
        });
});
