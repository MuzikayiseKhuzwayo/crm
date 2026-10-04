<?php

use Carbon\Carbon;
use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskShow;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\TaskService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('rebases a task deadline taking current date as start_at and moving due_at forward by intensity days', function () {
    $now = Carbon::parse('2026-10-04 10:00:00');
    Carbon::setTestNow($now);

    // Overdue task originally from September
    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Review Alpha Risk Model',
        'start_at' => '2026-09-01 09:00:00',
        'due_at' => '2026-09-05 17:00:00',
    ]);

    expect($task->due_at->isPast())->toBeTrue();
    expect($task->original_span_days)->toBe(4);

    // Rebase with Medium intensity (+3 days)
    $task->rebaseDeadline(3);
    $task->refresh();

    expect($task->start_at->toDateTimeString())->toBe('2026-10-04 10:00:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-10-07 17:00:00');
    expect($task->due_at->isPast())->toBeFalse();

    Carbon::setTestNow();
});

it('rebases overdue task from TaskItem component on show pages', function () {
    $user = User::create(['name' => 'Quant Trader', 'email' => 'quant@example.com']);
    $this->actingAs($user);

    $now = Carbon::parse('2026-10-04 14:00:00');
    Carbon::setTestNow($now);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Millennium Management Alpha Desk',
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send introductory DM',
        'taskable_type' => get_class($lead),
        'taskable_id' => $lead->id,
        'start_at' => '2026-09-10 09:00:00',
        'due_at' => '2026-09-12 11:30:00',
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    expect($task->due_at->isPast())->toBeTrue();

    Livewire::test(TaskItem::class, ['task' => $task, 'related' => true])
        ->assertSee('Rebase')
        ->call('rebase', 2)
        ->assertDispatched('task-updated')
        ->assertDispatched('activity-logged');

    $task->refresh();
    expect($task->start_at->toDateTimeString())->toBe('2026-10-04 14:00:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-10-06 11:30:00');
    expect($task->due_at->isPast())->toBeFalse();

    Carbon::setTestNow();
});

it('rebases task deadline from TaskShow component and displays rebase controls in overdue banner', function () {
    $user = User::create(['name' => 'Risk Manager', 'email' => 'risk@example.com']);
    $this->actingAs($user);

    $now = Carbon::parse('2026-10-04 12:00:00');
    Carbon::setTestNow($now);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Sign 30-Day Sandbox SOW',
        'due_at' => '2026-09-15 15:00:00',
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    expect($task->due_at->isPast())->toBeTrue();

    Livewire::test(TaskShow::class, ['task' => $task])
        ->assertSee('Rebase Deadline')
        ->call('rebase', 7)
        ->assertDispatched('task-updated');

    $task->refresh();
    expect($task->start_at->toDateTimeString())->toBe('2026-10-04 12:00:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-10-11 15:00:00');
    expect($task->due_at->isPast())->toBeFalse();

    Carbon::setTestNow();
});

it('rebases task from TaskIndex table via rebaseTask action', function () {
    $user = User::create(['name' => 'Desk Analyst', 'email' => 'analyst@example.com']);
    $this->actingAs($user);

    $now = Carbon::parse('2026-10-04 09:30:00');
    Carbon::setTestNow($now);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Follow up on Parquet sample data',
        'due_at' => '2026-09-20 16:00:00',
        'user_assigned_id' => $user->id,
    ]);

    Livewire::test(TaskIndex::class)
        ->call('rebaseTask', $task->id, 5)
        ->assertDispatched('task-updated')
        ->assertDispatched('activity-logged');

    $task->refresh();
    expect($task->start_at->toDateTimeString())->toBe('2026-10-04 09:30:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-10-09 16:00:00');
    expect($task->due_at->isPast())->toBeFalse();

    Carbon::setTestNow();
});

it('forbids rebasing task deadline without update permission', function () {
    $this->actingAsUserWithPermissions(['view crm tasks']);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Unauthorized Rebase Target',
        'due_at' => '2026-09-01 10:00:00',
    ]);

    Livewire::test(TaskShow::class, ['task' => $task])
        ->call('rebase', 3)
        ->assertForbidden();

    expect($task->fresh()->due_at->toDateTimeString())->toBe('2026-09-01 10:00:00');
});

it('exposes standard intensity presets on TaskService', function () {
    $service = app(TaskService::class);
    $presets = $service->getIntensityPresets();

    expect($presets)->toHaveKeys(['light_1', 'light_2', 'medium_3', 'moderate_5', 'deep_7'])
        ->and($presets['light_1']['days'])->toBe(1)
        ->and($presets['medium_3']['days'])->toBe(3)
        ->and($presets['deep_7']['days'])->toBe(7);
});
