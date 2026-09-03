<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskShow;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('assigns notes directly to tasks and renders everywhere links on task show page', function () {
    $user = User::create(['name' => 'Task Master', 'email' => 'taskmaster@example.com']);
    $this->actingAs($user);

    $person = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'linkedin' => 'https://linkedin.com/in/alicesmith',
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Alchemy CRM Outbound Lead',
        'person_id' => $person->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Follow up on proposal via LinkedIn',
        'description' => 'Send follow-up message and update notes on progress',
        'taskable_type' => get_class($lead),
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(2),
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    // Test assigning note directly to the task
    $note = $task->notes()->create([
        'external_id' => Uuid::uuid4()->toString(),
        'content' => 'Initial LinkedIn message sent. Waiting for response.',
        'created_by' => $user->id,
    ]);

    expect($task->notes)->toHaveCount(1)
        ->and($task->notes->first()->content)->toBe('Initial LinkedIn message sent. Waiting for response.');

    // Test rendering TaskShow component with everywhere links & activity tabs
    Livewire::test(TaskShow::class, ['task' => $task])
        ->assertSee('Alchemy CRM Outbound Lead')
        ->assertSee('Alice Smith')
        ->assertSee('LinkedIn')
        ->assertSee('Initial LinkedIn message sent. Waiting for response.');
});
