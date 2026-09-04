<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('updates lead pipeline stage and creates stage-specific automated tasks', function () {
    $user = User::create(['name' => 'Sales Specialist', 'email' => 'sales@example.com']);
    $this->actingAs($user);

    $this->artisan('laravelcrm:setup-lead-pipeline')
        ->assertExitCode(0);

    $pipeline = Pipeline::where('model', get_class(new Lead))->first();
    $stages = $pipeline->pipelineStages()->orderBy('order', 'asc')->get();

    $stageCold = $stages->firstWhere('order', 1);
    $stageCall = $stages->firstWhere('order', 4);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Automation Lead Prospect',
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stageCold->id,
        'user_owner_id' => $user->id,
    ]);

    // Test 1: Updating pipeline stage
    Livewire::test(LeadShow::class, ['lead' => $lead])
        ->call('updateStage', $stageCall->id)
        ->assertHasNoErrors();

    expect($lead->fresh()->pipeline_stage_id)->toBe($stageCall->id);

    // Test 2: Creating automated stage task via quick button
    Livewire::test(LeadShow::class, ['lead' => $lead])
        ->call('createStageTask', 'schedule_call')
        ->assertHasNoErrors();

    $task = Task::where('taskable_type', get_class($lead))
        ->where('taskable_id', $lead->id)
        ->where('name', 'LIKE', '%Schedule Discovery%')
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->due_at)->not->toBeNull();
});
