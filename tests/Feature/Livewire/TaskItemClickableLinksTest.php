<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('renders clickable links and view action for task cards embedded in leads', function () {
    $user = User::create(['name' => 'Lead Manager', 'email' => 'manager@example.com']);
    $this->actingAs($user);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Prospect Enterprise Lead',
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Call Decision Maker',
        'description' => 'Schedule discovery call',
        'taskable_type' => get_class($lead),
        'taskable_id' => $lead->id,
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    $taskShowUrl = route('laravel-crm.tasks.show', $task);

    Livewire::test(TaskItem::class, ['task' => $task])
        ->assertSeeHtml('href="' . $taskShowUrl . '"')
        ->assertSeeHtml('Call Decision Maker');
});
