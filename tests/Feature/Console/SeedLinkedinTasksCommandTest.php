<?php

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('seeds linkedin connection and intro dm tasks for leads without existing dm tasks', function () {
    $user = User::create(['name' => 'Outbound SDR', 'email' => 'sdr@example.com']);
    $this->actingAs($user);

    // Lead 1: Needs LinkedIn tasks
    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Fresh Prospect - Lead 1',
        'user_owner_id' => $user->id,
    ]);

    // Lead 2: Already has a "Send a DM" task
    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Contacted Prospect - Lead 2',
        'user_owner_id' => $user->id,
    ]);

    Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send a DM via LinkedIn',
        'taskable_type' => get_class($lead2),
        'taskable_id' => $lead2->id,
        'user_owner_id' => $user->id,
    ]);

    $this->artisan('laravelcrm:seed-linkedin-tasks')
        ->assertExitCode(0);

    // Lead 1 should now have 2 tasks: "Send a Connection Request and get accepted" (due Sept 7) and "Send an introductory DM" (due Sept 11)
    $lead1Tasks = Task::where('taskable_type', get_class($lead1))->where('taskable_id', $lead1->id)->get();
    expect($lead1Tasks)->toHaveCount(2);

    $connTask = $lead1Tasks->firstWhere('name', 'Send a Connection Request and get accepted');
    expect($connTask)->not->toBeNull()
        ->and($connTask->due_at->format('Y-m-d'))->toBe('2026-09-07');

    $dmTask = $lead1Tasks->firstWhere('name', 'Send an introductory DM');
    expect($dmTask)->not->toBeNull()
        ->and($dmTask->due_at->format('Y-m-d'))->toBe('2026-09-11');

    // Lead 2 should still have only 1 task (the existing DM task)
    $lead2Tasks = Task::where('taskable_type', get_class($lead2))->where('taskable_id', $lead2->id)->get();
    expect($lead2Tasks)->toHaveCount(1);
});
