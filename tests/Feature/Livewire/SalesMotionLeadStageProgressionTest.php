<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
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

it('strictly associates sales motion task completion with sequential 8-stage lead funnel', function () {
    $stage1 = $this->stages->firstWhere('order', 1); // Cold Prospect
    $stage2 = $this->stages->firstWhere('order', 2); // Connection Request Sent
    $stage3 = $this->stages->firstWhere('order', 3); // Connected / DM Sent
    $stage4 = $this->stages->firstWhere('order', 4); // Engaged / Qualified
    $stage5 = $this->stages->firstWhere('order', 5); // Call Scheduled
    $stage6 = $this->stages->firstWhere('order', 6); // Proposal Sent
    $stage7 = $this->stages->firstWhere('order', 7); // Closed Won
    $stage8 = $this->stages->firstWhere('order', 8); // Closed Lost

    expect($stage1->name)->toBe('Cold Prospect')
        ->and($stage2->name)->toBe('Connection Request Sent')
        ->and($stage3->name)->toBe('Connected / DM Sent')
        ->and($stage4->name)->toBe('Engaged / Qualified')
        ->and($stage5->name)->toBe('Call Scheduled')
        ->and($stage6->name)->toBe('Proposal Sent')
        ->and($stage7->name)->toBe('Closed Won')
        ->and($stage8->name)->toBe('Closed Lost');

    // 1. Initial State: Cold Prospect
    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Jane Street - Algorithmic Trader',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
    ]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage1->id);

    // 2. Complete Initial Connection Task -> Moves to Connection Request Sent (Stage 2), NOT Connected
    $connectionTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'LinkedIn Note: Angle A (Crypto Prop / MMs)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $connectionTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage2->id)
        ->and($lead->pipelineStage->name)->toBe('Connection Request Sent')
        ->and($lead->pipelineStage->name)->not->toBe('Connected / DM Sent');

    // 3. Prospect connects & we send Introductory DM -> Moves to Connected / DM Sent (Stage 3)
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
        ->and($lead->pipelineStage->name)->toBe('Connected / DM Sent');

    // 4. Prospect engages / we deliver kit -> Moves to Engaged / Qualified (Stage 4)
    $engageTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 2: Data Engineering Headache & Kit Offer',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $engageTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage4->id)
        ->and($lead->pipelineStage->name)->toBe('Engaged / Qualified');

    // 5. Complete Call Transition Task -> Moves to Call Scheduled (Stage 5)
    $callTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Call Transition: Propose 15-Min Technical Sync',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $callTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage5->id)
        ->and($lead->pipelineStage->name)->toBe('Call Scheduled');

    // 6. Complete Proposal / SOW Task -> Moves to Proposal Sent (Stage 6)
    $sowTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook SOW: Deliver 30-Day Paid Sandbox SOW ($15k–$35k)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $sowTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage6->id)
        ->and($lead->pipelineStage->name)->toBe('Proposal Sent');

    // 7. Complete Contract Task -> Moves to Closed Won (Stage 7)
    $contractTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Contract: Execute Annual Enterprise Production Contract ($120k–$240k/yr)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $contractTask->update(['completed_at' => now()]);

    $lead = $lead->fresh();
    expect($lead->pipeline_stage_id)->toBe($stage7->id)
        ->and($lead->pipelineStage->name)->toBe('Closed Won');
});

it('moves to closed lost upon disconnect task completion', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage8 = $this->stages->firstWhere('order', 8);

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

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage8->id)
        ->and($lead->fresh()->pipelineStage->name)->toBe('Closed Lost');
});

it('allows manual transition from Connection Request Sent to Connected / DM Sent and then advances to Engaged upon Scenario 1 completion', function () {
    $stage2 = $this->stages->firstWhere('order', 2); // Connection Request Sent
    $stage3 = $this->stages->firstWhere('order', 3); // Connected / DM Sent
    $stage4 = $this->stages->firstWhere('order', 4); // Engaged / Qualified

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Pending Quant Lead',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage2->id,
        'pipeline_stage_order' => $stage2->order,
        'user_owner_id' => $this->user->id,
    ]);

    // Manually mark as connected via LeadShow
    Livewire::test(LeadShow::class, ['lead' => $lead])
        ->call('markConnected')
        ->assertHasNoErrors();

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage3->id)
        ->and($lead->fresh()->pipelineStage->name)->toBe('Connected / DM Sent');

    // Completing Scenario 1 once already Connected advances to Engaged / Qualified
    $scenario1 = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 1: Accept in Silence (Audit Paper)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $this->user->id,
    ]);

    $scenario1->update(['completed_at' => now()]);

    expect($lead->fresh()->pipeline_stage_id)->toBe($stage4->id)
        ->and($lead->fresh()->pipelineStage->name)->toBe('Engaged / Qualified');
});
