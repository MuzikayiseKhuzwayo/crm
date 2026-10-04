<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskRelated;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\SalesPlaybookService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Sales Engineer', 'email' => 'engineer@dubstrata.com']);
    $this->actingAs($this->user);

    $this->artisan('laravelcrm:setup-lead-pipeline')->assertExitCode(0);

    $this->pipeline = Pipeline::where('model', Lead::class)->first();
    $this->stages = $this->pipeline->pipelineStages()->orderBy('order', 'asc')->get();

    $this->org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Citadel Securities',
    ]);

    $this->person = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Alexander',
        'last_name' => 'Kovalev',
        'organization_id' => $this->org->id,
    ]);

    $this->lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Alexander Kovalev - Quantitative Developer',
        'description' => 'Focus on high-throughput Kafka streaming pipelines and orderbook feature ingestion.',
        'person_id' => $this->person->id,
        'organization_id' => $this->org->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stages->first()->id,
        'user_owner_id' => $this->user->id,
    ]);
});

it('detects recommended angle based on role and context correctly', function () {
    $service = app(SalesPlaybookService::class);

    // Lead with dev/infra focus -> Angle C
    expect($service->detectRecommendedAngle($this->lead))->toBe('connection_angle_c');

    // Lead with crypto/prop focus -> Angle A
    $cryptoLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Wintermute - Crypto Prop Market Maker',
        'description' => 'Arbitrage and market making across Polymarket and CEX venues.',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stages->first()->id,
    ]);
    expect($service->detectRecommendedAngle($cryptoLead))->toBe('connection_angle_a');

    // Lead with macro/systematic focus -> Angle B
    $macroLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Brevan Howard - Systematic Macro PM',
        'description' => 'Multi-asset cross-market risk and factor models.',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stages->first()->id,
    ]);
    expect($service->detectRecommendedAngle($macroLead))->toBe('connection_angle_b');
});

it('renders personalized templates with token replacement and invariant checks', function () {
    $service = app(SalesPlaybookService::class);

    $rendered = $service->renderTemplate('connection_angle_c', $this->lead);

    expect($rendered['body'])->toContain('Hi Alexander')
        ->and($rendered['body'])->toContain('Citadel Securities')
        ->and($rendered['body'])->not->toContain('!')
        ->and($rendered['char_count'])->toBeLessThanOrEqual(300)
        ->and($rendered['is_valid_length'])->toBeTrue();
});

it('creates pre-filled playbook tasks directly from LeadShow', function () {
    Livewire::test(LeadShow::class, ['lead' => $this->lead])
        ->call('createStageTask', 'connection_angle_c')
        ->assertDispatched('task-added');

    $task = Task::where('taskable_type', Lead::class)
        ->where('taskable_id', $this->lead->id)
        ->latest('id')
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->name)->toBe('LinkedIn Note: Angle C (Quant Devs / Data Infra)')
        ->and($task->description)->toContain('Hi Alexander')
        ->and($task->description)->toContain('Citadel Securities')
        ->and($task->due_at)->not->toBeNull();
});

it('creates scenario and call transition tasks with appropriate delays', function () {
    Livewire::test(LeadShow::class, ['lead' => $this->lead])
        ->call('createStageTask', 'scenario_1')
        ->assertDispatched('task-added');

    $task1 = Task::where('taskable_type', Lead::class)
        ->where('taskable_id', $this->lead->id)
        ->where('name', 'LIKE', '%Scenario 1%')
        ->first();

    expect($task1)->not->toBeNull()
        ->and($task1->description)->toContain('Thanks for connecting, Alexander.')
        ->and($task1->description)->toContain('dubstrata.com/research');

    Livewire::test(LeadShow::class, ['lead' => $this->lead])
        ->call('createStageTask', 'call_transition')
        ->assertDispatched('task-added');

    $taskCall = Task::where('taskable_type', Lead::class)
        ->where('taskable_id', $this->lead->id)
        ->where('name', 'LIKE', '%15-Min Technical Sync%')
        ->first();

    expect($taskCall)->not->toBeNull()
        ->and($taskCall->description)->toContain('15-minute technical sync');
});

it('auto-populates TaskRelated form when rep selects a Playbook template', function () {
    $component = Livewire::test(TaskRelated::class, ['model' => $this->lead])
        ->set('selectedPlaybookTemplate', 'connection_angle_c')
        ->assertSet('name', 'LinkedIn Note: Angle C (Quant Devs / Data Infra)');

    expect($component->get('description'))->toContain('Citadel Securities');

    $component->call('save')
        ->assertDispatched('task-added');

    $task = Task::where('taskable_type', Lead::class)
        ->where('taskable_id', $this->lead->id)
        ->latest('id')
        ->first();

    expect($task->name)->toBe('LinkedIn Note: Angle C (Quant Devs / Data Infra)')
        ->and($task->description)->toContain('Hi Alexander — Saw your focus on data infrastructure at Citadel Securities.');
});

it('batch generates personalized playbook tasks via console command', function () {
    $this->artisan('laravelcrm:generate-playbook-tasks', [
        '--lead' => $this->lead->id,
        '--force' => true,
    ])->assertExitCode(0);

    $task = Task::where('taskable_type', Lead::class)
        ->where('taskable_id', $this->lead->id)
        ->latest('id')
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->name)->toBe('LinkedIn Note: Angle C (Quant Devs / Data Infra)')
        ->and($task->description)->toContain('Hi Alexander');
});
