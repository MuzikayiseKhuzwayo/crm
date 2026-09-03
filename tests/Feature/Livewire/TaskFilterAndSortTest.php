<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

class FilterTaskIndexStub extends TaskIndex
{
    public function render()
    {
        return '<div></div>';
    }
}

it('filters tasks by due date preset and date ranges', function () {
    $user = User::create(['name' => 'Test User 1', 'email' => 'test1@example.com']);
    $this->actingAs($user);

    $overdueTask = Task::create(['name' => 'Overdue Task', 'due_at' => now()->subDays(3), 'external_id' => Str::uuid()->toString()]);
    $todayTask = Task::create(['name' => 'Today Task', 'due_at' => now(), 'external_id' => Str::uuid()->toString()]);
    $futureTask = Task::create(['name' => 'Future Task', 'due_at' => now()->addDays(10), 'external_id' => Str::uuid()->toString()]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('due_preset', 'overdue')
        ->assertViewHas('tasks', function ($tasks) use ($overdueTask) {
            return $tasks->pluck('id')->contains($overdueTask->id) && $tasks->count() === 1;
        });

    Livewire::test(FilterTaskIndexStub::class)
        ->set('due_preset', 'today')
        ->assertViewHas('tasks', function ($tasks) use ($todayTask) {
            return $tasks->pluck('id')->contains($todayTask->id) && $tasks->count() === 1;
        });
});

it('filters tasks by assigned user', function () {
    $user1 = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $user2 = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task for Alice', 'user_assigned_id' => $user1->id, 'external_id' => Str::uuid()->toString()]);
    $task2 = Task::create(['name' => 'Task for Bob', 'user_assigned_id' => $user2->id, 'external_id' => Str::uuid()->toString()]);
    $task3 = Task::create(['name' => 'Unassigned Task', 'user_assigned_id' => null, 'external_id' => Str::uuid()->toString()]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('user_id', [(string) $user1->id])
        ->assertViewHas('tasks', function ($tasks) use ($task1) {
            return $tasks->pluck('id')->contains($task1->id) && $tasks->count() === 1;
        });

    Livewire::test(FilterTaskIndexStub::class)
        ->set('user_id', ['unassigned'])
        ->assertViewHas('tasks', function ($tasks) use ($task3) {
            return $tasks->pluck('id')->contains($task3->id) && $tasks->count() === 1;
        });
});

it('filters tasks by lead', function () {
    $user = User::create(['name' => 'Test User 2', 'email' => 'test2@example.com']);
    $this->actingAs($user);

    $lead = Lead::create(['title' => 'Alpha Quant Lead', 'external_id' => Str::uuid()->toString()]);
    $leadTask = Task::create(['name' => 'Lead Task', 'taskable_type' => Lead::class, 'taskable_id' => $lead->id, 'external_id' => Str::uuid()->toString()]);
    $otherTask = Task::create(['name' => 'Other Task', 'external_id' => Str::uuid()->toString()]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('lead_id', [(string) $lead->id])
        ->assertViewHas('tasks', function ($tasks) use ($leadTask) {
            return $tasks->pluck('id')->contains($leadTask->id) && $tasks->count() === 1;
        });
});

it('orders tasks by due_at, assigned_user_name, and lead_title', function () {
    $user1 = User::create(['name' => 'Aaron', 'email' => 'aaron@example.com']);
    $user2 = User::create(['name' => 'Zack', 'email' => 'zack@example.com']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task 1', 'due_at' => now()->addDays(1), 'user_assigned_id' => $user2->id, 'external_id' => Str::uuid()->toString()]);
    $task2 = Task::create(['name' => 'Task 2', 'due_at' => now()->addDays(5), 'user_assigned_id' => $user1->id, 'external_id' => Str::uuid()->toString()]);

    Livewire::test(FilterTaskIndexStub::class)
        ->set('sortBy', ['column' => 'due_at', 'direction' => 'asc'])
        ->assertViewHas('tasks', function ($tasks) use ($task1) {
            return $tasks->first()->id === $task1->id;
        });

    Livewire::test(FilterTaskIndexStub::class)
        ->set('sortBy', ['column' => 'assigned_user_name', 'direction' => 'asc'])
        ->assertViewHas('tasks', function ($tasks) use ($task2) {
            return $tasks->first()->id === $task2->id;
        });
});
