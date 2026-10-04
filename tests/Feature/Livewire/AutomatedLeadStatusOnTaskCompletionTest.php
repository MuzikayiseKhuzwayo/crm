<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Automator', 'email' => 'automator@example.com']);
    $this->actingAs($this->user);

    $this->artisan('laravelcrm:setup-lead-pipeline')->assertExitCode(0);

    $this->pipeline = Pipeline::where('model', Lead::class)->first();
    $this->stages = $this->pipeline->pipelineStages()->orderBy('order', 'asc')->get();
});

it('automatically advances cold lead to connected/dm stage when connection request task is completed', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage2 = $this->stages->firstWhere('order', 2);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Initech - Software Lead',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'lead_status_id' => 1,
        'user_owner_id' => $this->user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send a Connection Request and get accepted',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(2),
        'user_owner_id' => $this->user->id,
    ]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage1->id);

    // Complete the task via TaskItem Livewire component
    Livewire::test(TaskItem::class, ['task' => $task])
        ->call('complete')
        ->assertDispatched('task-completed');

    $freshLead = $lead->fresh();
    expect($freshLead->pipeline_stage_id)->toBe($stage2->id)
        ->and($freshLead->lead_status_id)->toBe(2);
});

it('automatically advances lead to call scheduled when booking call task is completed', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage4 = $this->stages->firstWhere('order', 4);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Hooli - Enterprise Integration',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Schedule Discovery / Pitch Call',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(1),
        'user_owner_id' => $this->user->id,
    ]);

    $task->update(['completed_at' => now()]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage4->id);
});

it('automatically advances lead to proposal sent when proposal task is completed', function () {
    $stage2 = $this->stages->firstWhere('order', 2);
    $stage5 = $this->stages->firstWhere('order', 5);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Pied Piper - Compression Quote',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage2->id,
        'user_owner_id' => $this->user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Prepare & Send Formal Proposal / Quote',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(3),
        'user_owner_id' => $this->user->id,
    ]);

    $task->update(['completed_at' => now()]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage5->id);
});

it('does not regress lead stage when an earlier stage task is completed afterwards', function () {
    $stage4 = $this->stages->firstWhere('order', 4);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Aviato - Advanced Lead',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage4->id, // Currently at Call Scheduled
        'user_owner_id' => $this->user->id,
    ]);

    // An introductory DM task gets completed late
    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send an introductory DM',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->subDay(),
        'user_owner_id' => $this->user->id,
    ]);

    $task->update(['completed_at' => now()]);

    // Lead stage must remain at stage 4 (Call Scheduled) and not regress to stage 2
    expect($lead->fresh()->pipeline_stage_id)->toBe($stage4->id);
});

it('advances person open lead stage when task attached to person is completed', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage2 = $this->stages->firstWhere('order', 2);

    $person = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Erlich',
        'last_name' => 'Bachman',
        'user_owner_id' => $this->user->id,
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Bachmanity Ventures Opportunity',
        'person_id' => $person->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send introductory DM on LinkedIn',
        'taskable_type' => Person::class,
        'taskable_id' => $person->id,
        'user_owner_id' => $this->user->id,
    ]);

    $task->update(['completed_at' => now()]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage2->id);
});

it('syncs existing leads with completed tasks using sync-lead-stages command', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage3 = $this->stages->firstWhere('order', 3);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Retroactive Sync Prospect',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    // Create a task that was already completed previously
    Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Conduct Discovery Meeting',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'completed_at' => now()->subDay(),
        'user_owner_id' => $this->user->id,
    ]);

    // Force stage back to stage 1 to simulate pre-automation state
    $lead->updateQuietly(['pipeline_stage_id' => $stage1->id]);
    expect($lead->fresh()->pipeline_stage_id)->toBe($stage1->id);

    // Run artisan sync command
    $this->artisan('laravelcrm:sync-lead-stages', [
        '--lead' => $lead->id,
    ])
        ->expectsOutputToContain('Sync completed')
        ->assertExitCode(0);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage3->id);
});

it('refreshes LeadShow livewire component when task is completed', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage2 = $this->stages->firstWhere('order', 2);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Livewire Refresh Lead',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send a Connection Request and get accepted',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $test = Livewire::test(LeadShow::class, ['lead' => $lead]);

    // Complete the task and dispatch event
    $task->update(['completed_at' => now()]);
    $test->dispatch('task-completed');

    expect($test->get('lead')->pipeline_stage_id)->toBe($stage2->id);
});
