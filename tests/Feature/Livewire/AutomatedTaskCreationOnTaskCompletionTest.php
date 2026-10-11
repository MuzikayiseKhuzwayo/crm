<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\LeadStatusAutomationService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Quantitative Founder', 'email' => 'founder@dubstrata.com']);
    $this->actingAs($this->user);

    $this->artisan('laravelcrm:setup-lead-pipeline')->assertExitCode(0);

    $this->pipeline = Pipeline::where('model', Lead::class)->first();
    $this->stages = $this->pipeline->pipelineStages()->orderBy('order', 'asc')->get();
});

it('auto-creates and assigns Scenario 1 and Scenario 4 tasks when connection request task is completed', function () {
    $stage1 = $this->stages->firstWhere('order', 1);
    $stage2 = $this->stages->firstWhere('order', 2);

    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Citadel Securities',
    ]);

    $person = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Alexander',
        'last_name' => 'Lipton',
        'organization_id' => $org->id,
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Citadel Securities - Quantitative Researcher',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'lead_status_id' => 1,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $connectionTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send a Connection Request and get accepted',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(1),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    // Complete the task via Livewire TaskItem component
    Livewire::test(TaskItem::class, ['task' => $connectionTask])
        ->call('complete')
        ->assertDispatched('task-completed');

    $freshLead = $lead->fresh();
    expect($freshLead->pipeline_stage_id)->toBe($stage2->id);

    // Verify newly auto-created tasks
    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    // 1 completed task + 2 new tasks = 3 total tasks
    expect($tasks->count())->toBe(3);

    $scenario1 = $tasks->firstWhere('name', 'Playbook Scenario 1: Accept in Silence (Audit Paper)');
    $scenario4 = $tasks->firstWhere('name', 'Playbook Scenario 4: Mid-Cadence Multi-Asset Nudge');

    expect($scenario1)->not->toBeNull()
        ->and($scenario1->user_assigned_id)->toBe($this->user->id)
        ->and($scenario1->user_owner_id)->toBe($this->user->id)
        ->and($scenario1->description)->toContain('Alexander')
        ->and($scenario1->description)->toContain('dubstrata.com/research')
        ->and($scenario1->completed_at)->toBeNull();

    expect($scenario4)->not->toBeNull()
        ->and($scenario4->user_assigned_id)->toBe($this->user->id)
        ->and($scenario4->user_owner_id)->toBe($this->user->id)
        ->and($scenario4->description)->toContain('Alexander')
        ->and($scenario4->completed_at)->toBeNull();
});

it('auto-creates Scenario 3 evaluation kit and call transition when Scenario 1 task is completed', function () {
    $stage2 = $this->stages->firstWhere('order', 2);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Jane Street - Prop Trader',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage2->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $scenario1Task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 1: Accept in Silence (Audit Paper)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(2),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $scenario1Task->update(['completed_at' => now()]);

    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    $scenario3 = $tasks->firstWhere('name', 'Playbook Scenario 3: Deliver Evaluation Kit Link');
    $callTransition = $tasks->firstWhere('name', 'Playbook Call Transition: Propose 15-Min Technical Sync');

    expect($scenario3)->not->toBeNull()
        ->and($scenario3->description)->toContain('dubstrata.com/evaluation-kit')
        ->and($scenario3->user_assigned_id)->toBe($this->user->id);

    expect($callTransition)->not->toBeNull()
        ->and($callTransition->description)->toContain('15-minute technical sync')
        ->and($callTransition->user_assigned_id)->toBe($this->user->id);
});

it('auto-creates Post-Evaluation Review and Canary Debrief Call when Evaluation Kit task is completed', function () {
    $stage3 = $this->stages->firstWhere('order', 3);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Two Sigma - Quant Dev',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage3->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $kitTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 3: Deliver Evaluation Kit Link',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDay(),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $kitTask->update(['completed_at' => now()]);

    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    $postEval = $tasks->firstWhere('name', 'Playbook Scenario 3.1 & 3.2: Review Deletion Gain & Deploy Canary Paper-Trader');
    $debriefCall = $tasks->firstWhere('name', 'Playbook Call: 15-Minute Post-Eval Debrief & Canary Demo');

    expect($postEval)->not->toBeNull()
        ->and($postEval->description)->toContain('canary_paper_trader.py')
        ->and($postEval->description)->toContain('Deletion Gain')
        ->and($postEval->user_assigned_id)->toBe($this->user->id);

    expect($debriefCall)->not->toBeNull()
        ->and($debriefCall->description)->toContain('15-Minute Technical Sync Agenda')
        ->and($debriefCall->user_assigned_id)->toBe($this->user->id);
});

it('auto-creates Sandbox SOW and CRO clearance tasks when technical debrief call task is completed', function () {
    $stage4 = $this->stages->firstWhere('order', 4);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Millennium Management - Macro PM',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage4->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $callTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Call: 15-Minute Post-Eval Debrief & Canary Demo',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(2),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $callTask->update(['completed_at' => now()]);

    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    $sandboxSow = $tasks->firstWhere('name', 'Playbook SOW: Deliver 30-Day Paid Sandbox SOW ($15k–$35k)');
    $cro = $tasks->firstWhere('name', 'Playbook CRO: Provision HRX Terminal Access for Fiduciary Clearance');

    expect($sandboxSow)->not->toBeNull()
        ->and($sandboxSow->description)->toContain('$15,000–$35,000')
        ->and($sandboxSow->user_assigned_id)->toBe($this->user->id);

    expect($cro)->not->toBeNull()
        ->and($cro->description)->toContain('HRX Command Center')
        ->and($cro->user_assigned_id)->toBe($this->user->id);
});

it('auto-creates Annual Enterprise Contract task when Sandbox SOW task is completed', function () {
    $stage5 = $this->stages->firstWhere('order', 5);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Balyasny Asset Management - Prop Desk',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage5->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $sowTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook SOW: Deliver 30-Day Paid Sandbox SOW ($15k–$35k)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(2),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $sowTask->update(['completed_at' => now()]);

    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    $contractTask = $tasks->firstWhere('name', 'Playbook Contract: Execute Annual Enterprise Production Contract ($120k–$240k/yr)');

    expect($contractTask)->not->toBeNull()
        ->and($contractTask->name)->toContain('$120k–$240k/yr')
        ->and($contractTask->description)->toContain('ASC 606')
        ->and($contractTask->user_assigned_id)->toBe($this->user->id);
});

it('auto-creates Production Onboarding task when Annual Enterprise Contract is completed', function () {
    $stage6 = $this->stages->firstWhere('order', 6);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Brevan Howard - Systematic Desk',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage6->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $contractTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Contract: Execute Annual Enterprise Production Contract ($120k–$240k/yr)',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(7),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $contractTask->update(['completed_at' => now()]);

    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    $onboardingTask = $tasks->firstWhere('name', 'Playbook Onboarding: Deploy Dedicated Production Feed & Support');

    expect($onboardingTask)->not->toBeNull()
        ->and($onboardingTask->description)->toContain('Production Onboarding & Infrastructure Provisioning')
        ->and($onboardingTask->user_assigned_id)->toBe($this->user->id);
});

it('does not create duplicate tasks when completion is called multiple times', function () {
    $stage1 = $this->stages->firstWhere('order', 1);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Point72 - Macro Desk',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage1->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $connectionTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send a Connection Request and get accepted',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(1),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    // Complete the task
    $connectionTask->update(['completed_at' => now()]);

    $count1 = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->count();

    // Call handleTaskCompleted again to test idempotency
    app(LeadStatusAutomationService::class)->handleTaskCompleted($connectionTask);

    $count2 = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->count();

    expect($count1)->toBe(3)
        ->and($count2)->toBe(3);
});

it('auto-creates Scenario 5 Disconnect task when Scenario 4 Nudge task is completed', function () {
    $stage2 = $this->stages->firstWhere('order', 2);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Bridgewater Associates - Macro Researcher',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stage2->id,
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $nudgeTask = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Playbook Scenario 4: Mid-Cadence Multi-Asset Nudge',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(5),
        'user_owner_id' => $this->user->id,
        'user_assigned_id' => $this->user->id,
    ]);

    $nudgeTask->update(['completed_at' => now()]);

    $tasks = Task::where('taskable_type', $lead->getMorphClass())
        ->where('taskable_id', $lead->id)
        ->get();

    $disconnectTask = $tasks->firstWhere('name', 'Playbook Scenario 5: Graceful Long-Term Disconnect');

    expect($disconnectTask)->not->toBeNull()
        ->and($disconnectTask->description)->toContain('leave your inbox alone')
        ->and($disconnectTask->user_assigned_id)->toBe($this->user->id);
});
