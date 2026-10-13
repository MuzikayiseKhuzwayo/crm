<?php

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Motion Tester', 'email' => 'motion@example.com']);
    $this->actingAs($this->user);

    $this->artisan('laravelcrm:setup-lead-pipeline')->assertExitCode(0);

    $this->pipeline = Pipeline::where('model', Lead::class)->first();
    $this->stages = $this->pipeline->pipelineStages()->orderBy('order', 'asc')->get();
});

it('strictly associates sales motion task completion with sequential lead stages', function () {
    $stage1 = $this->stages->firstWhere('order', 1); // Cold Prospect
    $stage2 = $this->stages->firstWhere('order', 2); // Connected / DM Sent
    $stage3 = $this->stages->firstWhere('order', 3); // Engaged / Qualified
    $stage4 = $this->stages->firstWhere('order', 4); // Call Scheduled
    $stage5 = $this->stages->firstWhere('order', 5); // Proposal Sent
    $stage6 = $this->stages->firstWhere('order', 6); // Closed Won
    $stage7 = $this->stages->firstWhere('order', 7); // Closed Lost

    expect($stage1->name)->toBe('Cold Prospect')
        ->and($stage2->name)->toBe('Connected / DM Sent')
        ->and($stage3->name)->toBe('Engaged / Qualified')
        ->and($stage4->name)->toBe('Call Scheduled')
        ->and($stage5->name)->toBe('Proposal Sent')
        ->and($stage6->name)->toBe('Closed Won')
        ->and($stage7->name)->toBe('Closed Lost');

    // 1. Initial State: Cold Prospect
    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Jane Street - Algorithmic Trader',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage1->id);

    // 2. Complete Initial Connection Task -> Moves to Connected / DM Sent (Stage 2)
    $connectionTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send a Connection Request and get accepted',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $connectionTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage2->id)
        ->and($lead->pipelineStage->name)->toBe('Connected / DM Sent')
        ->and($lead->pipelineStage->name)->not->toBe('Appointment Scheduled');

    // 3. Lead starts engaging: Complete Introductory DM Task -> Moves to Engaged / Qualified (Stage 3)
    $dmTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send an introductory DM',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $dmTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage3->id)
        ->and($lead->pipelineStage->name)->toBe('Engaged / Qualified');

    // 4. Complete Call Transition Task -> Moves to Call Scheduled (Stage 4)
    $callTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Call Transition: Propose 15-Min Technical Sync',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $callTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage4->id)
        ->and($lead->pipelineStage->name)->toBe('Call Scheduled');

    // 5. Complete Proposal / SOW Task -> Moves to Proposal Sent (Stage 5)
    $sowTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook SOW: Deliver 30-Day Paid Sandbox SOW ($15k–$35k)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $sowTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage5->id)
        ->and($lead->pipelineStage->name)->toBe('Proposal Sent');

    // 6. Complete Contract Task -> Moves to Closed Won (Stage 6)
    $contractTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Contract: Execute Annual Enterprise Production Contract ($120k–$240k/yr)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $contractTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage6->id)
        ->and($lead->pipelineStage->name)->toBe('Closed Won');
});

it('moves to closed lost upon disconnect task completion', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage7 = $this->stages->firstWhere('order', 7);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Unresponsive Account',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    $disconnectTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 5: Graceful Long-Term Disconnect',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $disconnectTask->update(['completed_at' => now()]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage7->id)
        ->and($lead->fresh()->pipelineStage->name)->toBe('Closed Lost');
});

it('advances from Connected to Engaged upon Scenario 1 completion', function () {
    $stage2 = $this->stages->firstWhere('order', 2);
    $stage3 = $this->stages->firstWhere('order', 3);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Connected Quant Lead',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage2->id,
        'user_owner_id' => $this->user->id,
    ]);

    $scenario1 = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 1: Accept in Silence (Audit Paper)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $scenario1->update(['completed_at' => now()]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage3->id)
        ->and($lead->fresh()->pipelineStage->name)->toBe('Engaged / Qualified');
});
