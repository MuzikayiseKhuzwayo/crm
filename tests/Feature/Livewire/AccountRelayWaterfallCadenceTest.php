<?php

use Carbon\Carbon;
use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\AccountRelayService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Relay Specialist', 'email' => 'relay@example.com']);
    $this->actingAs($this->user);

    $this->pipeline = Pipeline::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Lead Sales Funnel',
        'model' => Lead::class,
    ]);

    $this->stageCold = PipelineStage::create([
        'external_id' => Uuid::uuid4()->toString(),
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Cold Prospect',
        'order' => 1,
    ]);

    $this->stageCall = PipelineStage::create([
        'external_id' => Uuid::uuid4()->toString(),
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Call Scheduled',
        'order' => 4,
    ]);

    $this->relayService = app(AccountRelayService::class);
});

it('initializes company basket prioritizing seniority with exactly one active lead', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Cyberdyne Systems',
    ]);

    $personSdr = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Sam',
        'last_name' => 'Specialist',
        'description' => 'Junior SDR Specialist',
        'organization_id' => $org->id,
    ]);

    $personVp = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Victoria',
        'last_name' => 'President',
        'description' => 'VP of Engineering',
        'organization_id' => $org->id,
    ]);

    $personDir = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'David',
        'last_name' => 'Director',
        'description' => 'Director of Infrastructure',
        'organization_id' => $org->id,
    ]);

    $leadSdr = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Cyberdyne - Sam Specialist',
        'organization_id' => $org->id,
        'person_id' => $personSdr->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $leadVp = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Cyberdyne - Victoria President',
        'organization_id' => $org->id,
        'person_id' => $personVp->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $leadDir = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Cyberdyne - David Director',
        'organization_id' => $org->id,
        'person_id' => $personDir->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $this->relayService->initializeOrganizationBasket($org);

    // VP should be Priority 1 and Active
    expect($leadVp->fresh()->relay_order)->toBe(1)
        ->and($leadVp->fresh()->relay_status)->toBe('active')
        ->and($leadVp->fresh()->relay_activated_at)->not->toBeNull();

    // Director should be Priority 2 and Standby
    expect($leadDir->fresh()->relay_order)->toBe(2)
        ->and($leadDir->fresh()->relay_status)->toBe('standby');

    // Specialist should be Priority 3 and Standby
    expect($leadSdr->fresh()->relay_order)->toBe(3)
        ->and($leadSdr->fresh()->relay_status)->toBe('standby');
});

it('rotates to next colleague when active lead falls off and seeds outreach task', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Wayne Enterprises',
    ]);

    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Wayne - Bruce Wayne',
        'organization_id' => $org->id,
        'relay_status' => 'active',
        'relay_order' => 1,
        'relay_activated_at' => Carbon::now()->subDays(14),
        'user_owner_id' => $this->user->id,
    ]);

    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Wayne - Lucius Fox',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
        'user_owner_id' => $this->user->id,
    ]);

    $nextLead = $this->relayService->rotateToNext($lead1, 'unresponsive after 14 days');

    expect($lead1->fresh()->relay_status)->toBe('fallen_off')
        ->and($lead1->fresh()->relay_fallen_off_at)->not->toBeNull()
        ->and($nextLead->id)->toBe($lead2->id)
        ->and($lead2->fresh()->relay_status)->toBe('active')
        ->and($lead2->fresh()->relay_activated_at)->not->toBeNull();

    // Verify initial task was seeded for new active lead
    $task = Task::where('taskable_id', $lead2->id)->latest()->first();
    expect($task)->not->toBeNull()
        ->and($task->name)->toContain('Account Relay')
        ->and($task->name)->toContain('Lucius Fox');
});

it('allows manual rotation directly from LeadShow livewire component', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Stark Industries',
    ]);

    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Stark - Tony Stark',
        'organization_id' => $org->id,
        'relay_status' => 'active',
        'relay_order' => 1,
        'user_owner_id' => $this->user->id,
    ]);

    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Stark - Pepper Potts',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
        'user_owner_id' => $this->user->id,
    ]);

    Livewire::test(LeadShow::class, ['lead' => $lead1])
        ->call('rotateRelay', 'not decision maker')
        ->assertRedirect(route('laravel-crm.leads.show', $lead2));

    expect($lead1->fresh()->relay_status)->toBe('fallen_off')
        ->and($lead2->fresh()->relay_status)->toBe('active');
});

it('executes artisan rotate command to auto-advance due accounts', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Umbrella Corp',
    ]);

    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Umbrella - Albert Wesker',
        'organization_id' => $org->id,
        'relay_status' => 'active',
        'relay_order' => 1,
        'relay_activated_at' => Carbon::now()->subDays(20),
        'user_owner_id' => $this->user->id,
    ]);

    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Umbrella - William Birkin',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
        'user_owner_id' => $this->user->id,
    ]);

    $this->artisan('laravelcrm:rotate-account-relay', ['--days' => 14])
        ->assertExitCode(0);

    expect($lead1->fresh()->relay_status)->toBe('fallen_off')
        ->and($lead2->fresh()->relay_status)->toBe('active');
});
