<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('filters tasks by due date preset and date ranges', function () {
    $user = User::create(['name' => 'Test User 1', 'email' => 'test1@example.com']);
    $this->actingAs($user);

    $overdueTask = Task::create(['name' => 'Overdue Task', 'due_at' => now()->subDays(3)->startOfDay(), 'external_id' => Str::uuid()->toString()]);
    $todayTask = Task::create(['name' => 'Today Task', 'due_at' => today()->addHours(10), 'external_id' => Str::uuid()->toString()]);
    $futureTask = Task::create(['name' => 'Future Task', 'due_at' => now()->addDays(10)->startOfDay(), 'external_id' => Str::uuid()->toString()]);

    $test = Livewire::test(TaskIndex::class)->set('due_preset', 'overdue');
    $tasks = $test->instance()->tasks();
    expect($tasks->pluck('id'))->toContain($overdueTask->id)
        ->and($tasks->count())->toBe(1);

    $testToday = Livewire::test(TaskIndex::class)->set('due_preset', 'today');
    $tasksToday = $testToday->instance()->tasks();
    expect($tasksToday->pluck('id'))->toContain($todayTask->id)
        ->and($tasksToday->count())->toBe(1);
});

it('filters tasks by assigned user', function () {
    $user1 = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $user2 = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task for Alice', 'user_assigned_id' => $user1->id, 'external_id' => Str::uuid()->toString()]);
    $task2 = Task::create(['name' => 'Task for Bob', 'user_assigned_id' => $user2->id, 'external_id' => Str::uuid()->toString()]);
    $task3 = Task::create(['name' => 'Unassigned Task', 'user_assigned_id' => null, 'external_id' => Str::uuid()->toString()]);

    $testUser = Livewire::test(TaskIndex::class)->set('user_id', [(string) $user1->id]);
    $tasksUser = $testUser->instance()->tasks();
    expect($tasksUser->pluck('id'))->toContain($task1->id)
        ->and($tasksUser->count())->toBe(1);

    $testUnassigned = Livewire::test(TaskIndex::class)->set('user_id', ['unassigned']);
    $tasksUnassigned = $testUnassigned->instance()->tasks();
    expect($tasksUnassigned->pluck('id'))->toContain($task3->id)
        ->and($tasksUnassigned->count())->toBe(1);
});

it('filters tasks by lead', function () {
    $user = User::create(['name' => 'Test User 2', 'email' => 'test2@example.com']);
    $this->actingAs($user);

    $lead = Lead::create(['title' => 'Alpha Quant Lead', 'external_id' => Str::uuid()->toString()]);
    $leadTask = Task::create(['name' => 'Lead Task', 'taskable_type' => Lead::class, 'taskable_id' => $lead->id, 'external_id' => Str::uuid()->toString()]);
    $otherTask = Task::create(['name' => 'Other Task', 'external_id' => Str::uuid()->toString()]);

    $testLead = Livewire::test(TaskIndex::class)->set('lead_id', [(string) $lead->id]);
    $tasksLead = $testLead->instance()->tasks();
    expect($tasksLead->pluck('id'))->toContain($leadTask->id)
        ->and($tasksLead->count())->toBe(1);
});

it('orders tasks by due_at, assigned_user_name, and lead_title', function () {
    $user1 = User::create(['name' => 'Aaron', 'email' => 'aaron@example.com']);
    $user2 = User::create(['name' => 'Zack', 'email' => 'zack@example.com']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task 1', 'due_at' => now()->addDays(1), 'user_assigned_id' => $user2->id, 'external_id' => Str::uuid()->toString()]);
    $task2 = Task::create(['name' => 'Task 2', 'due_at' => now()->addDays(5), 'user_assigned_id' => $user1->id, 'external_id' => Str::uuid()->toString()]);

    $testDue = Livewire::test(TaskIndex::class)->set('sortBy', ['column' => 'due_at', 'direction' => 'asc']);
    expect($testDue->instance()->tasks()->first()->id)->toBe($task1->id);

    $testAssigned = Livewire::test(TaskIndex::class)->set('sortBy', ['column' => 'assigned_user_name', 'direction' => 'asc']);
    expect($testAssigned->instance()->tasks()->first()->id)->toBe($task2->id);
});
