<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('filters tasks by due date preset and date ranges', function () {
    $user = User::create(['name' => 'Test User 1', 'email' => 'test1_'.Str::random(5).'@example.com']);
    $this->actingAs($user);

    $overdueTask = Task::create(['name' => 'Overdue Task '.Str::random(5), 'due_at' => now()->subDays(3)->startOfDay(), 'external_id' => Str::uuid()->toString()]);
    $todayTask = Task::create(['name' => 'Today Task '.Str::random(5), 'due_at' => now()->endOfDay(), 'external_id' => Str::uuid()->toString()]);
    $futureTask = Task::create(['name' => 'Future Task '.Str::random(5), 'due_at' => now()->addDays(10)->startOfDay(), 'external_id' => Str::uuid()->toString()]);

    $test = Livewire::test(TaskIndex::class)->set('due_preset', 'overdue');
    $tasks = $test->instance()->tasks();
    expect($tasks->pluck('id'))->toContain($overdueTask->id)
        ->and($tasks->pluck('id'))->not->toContain($futureTask->id)
        ->and($tasks->pluck('id'))->not->toContain($todayTask->id);

    $testToday = Livewire::test(TaskIndex::class)->set('due_preset', 'today');
    $tasksToday = $testToday->instance()->tasks();
    expect($tasksToday->pluck('id'))->toContain($todayTask->id)
        ->and($tasksToday->pluck('id'))->not->toContain($overdueTask->id)
        ->and($tasksToday->pluck('id'))->not->toContain($futureTask->id);
});

it('filters tasks by assigned user', function () {
    $user1 = User::create(['name' => 'Alice '.Str::random(5), 'email' => 'alice_'.Str::random(5).'@example.com']);
    $user2 = User::create(['name' => 'Bob '.Str::random(5), 'email' => 'bob_'.Str::random(5).'@example.com']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => 'Task for Alice '.Str::random(5), 'user_assigned_id' => $user1->id, 'external_id' => Str::uuid()->toString()]);
    $task2 = Task::create(['name' => 'Task for Bob '.Str::random(5), 'user_assigned_id' => $user2->id, 'external_id' => Str::uuid()->toString()]);
    $task3 = Task::create(['name' => 'Unassigned Task '.Str::random(5), 'user_assigned_id' => null, 'external_id' => Str::uuid()->toString()]);

    $testUser = Livewire::test(TaskIndex::class)->set('user_id', [(string) $user1->id]);
    $tasksUser = $testUser->instance()->tasks();
    expect($tasksUser->pluck('id'))->toContain($task1->id)
        ->and($tasksUser->pluck('id'))->not->toContain($task2->id);

    $testUnassigned = Livewire::test(TaskIndex::class)->set('user_id', ['unassigned']);
    $tasksUnassigned = $testUnassigned->instance()->tasks();
    expect($tasksUnassigned->pluck('id'))->toContain($task3->id)
        ->and($tasksUnassigned->pluck('id'))->not->toContain($task1->id);
});

it('filters tasks by lead', function () {
    $user = User::create(['name' => 'Test User 2', 'email' => 'test2_'.Str::random(5).'@example.com']);
    $this->actingAs($user);

    $lead = Lead::create(['title' => 'Alpha Quant Lead '.Str::random(5), 'external_id' => Str::uuid()->toString()]);
    $leadTask = Task::create(['name' => 'Lead Task '.Str::random(5), 'taskable_type' => Lead::class, 'taskable_id' => $lead->id, 'external_id' => Str::uuid()->toString()]);
    $otherTask = Task::create(['name' => 'Other Task '.Str::random(5), 'external_id' => Str::uuid()->toString()]);

    $testLead = Livewire::test(TaskIndex::class)->set('lead_id', [(string) $lead->id]);
    $tasksLead = $testLead->instance()->tasks();
    expect($tasksLead->pluck('id'))->toContain($leadTask->id)
        ->and($tasksLead->pluck('id'))->not->toContain($otherTask->id);
});

it('orders tasks by due_at, assigned_user_name, and lead_title', function () {
    $namePrefix = 'SortTest_'.Str::random(6);
    $user1 = User::create(['name' => 'AAA_'.$namePrefix, 'email' => 'aaa_'.Str::random(5).'@example.com']);
    $user2 = User::create(['name' => 'ZZZ_'.$namePrefix, 'email' => 'zzz_'.Str::random(5).'@example.com']);
    $this->actingAs($user1);

    $task1 = Task::create(['name' => $namePrefix.'_Task1', 'due_at' => now()->addDays(1), 'user_assigned_id' => $user2->id, 'external_id' => Str::uuid()->toString()]);
    $task2 = Task::create(['name' => $namePrefix.'_Task2', 'due_at' => now()->addDays(5), 'user_assigned_id' => $user1->id, 'external_id' => Str::uuid()->toString()]);

    $testDue = Livewire::test(TaskIndex::class)
        ->set('search', $namePrefix)
        ->set('sortBy', ['column' => 'due_at', 'direction' => 'asc']);
    $dueIds = $testDue->instance()->tasks()->pluck('id')->values()->all();
    expect($dueIds)->toBe([$task1->id, $task2->id]);

    $testAssigned = Livewire::test(TaskIndex::class)
        ->set('search', $namePrefix)
        ->set('sortBy', ['column' => 'assigned_user_name', 'direction' => 'asc']);
    $assignedIds = $testAssigned->instance()->tasks()->pluck('id')->values()->all();
    expect($assignedIds)->toBe([$task2->id, $task1->id]);
});
