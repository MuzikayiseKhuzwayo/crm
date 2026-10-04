<?php

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Services\AccountRelayService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::first() ?: User::create([
        'name' => 'Agent Smith',
        'email' => 'smith@example.com',
        'password' => bcrypt('secret'),
    ]);
    $this->actingAs($this->user);

    $this->pipeline = Pipeline::first() ?: Pipeline::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Outbound Pipeline',
        'model' => Lead::class,
    ]);

    $this->stageCold = PipelineStage::first() ?: PipelineStage::create([
        'external_id' => Uuid::uuid4()->toString(),
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Cold Outreach',
        'order' => 1,
    ]);

    $this->relayService = app(AccountRelayService::class);
});

it('batch initializes solo leads and multi-lead organization baskets correctly', function () {
    // 1. Solo Lead (no organization)
    $soloLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Freelance Lead',
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    // 2. Single-lead company
    $singleOrg = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Single Account Inc',
    ]);
    $singleOrgLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Single Lead',
        'organization_id' => $singleOrg->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    // 3. Multi-lead company
    $multiOrg = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Enterprise Corp',
    ]);
    $juniorLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Enterprise - Junior Analyst',
        'organization_id' => $multiOrg->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);
    $seniorLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Enterprise - Chief Executive Officer',
        'organization_id' => $multiOrg->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    // Run artisan command
    $this->artisan('laravelcrm:init-relay-baskets --reset')
        ->assertSuccessful();

    // Assert solo lead is active #1
    expect($soloLead->fresh()->relay_status)->toBe('active')
        ->and($soloLead->fresh()->relay_order)->toBe(1);

    // Assert single-lead account is active #1
    expect($singleOrgLead->fresh()->relay_status)->toBe('active')
        ->and($singleOrgLead->fresh()->relay_order)->toBe(1);

    // Assert multi-lead: CEO is active #1, Analyst is standby #2
    expect($seniorLead->fresh()->relay_status)->toBe('active')
        ->and($seniorLead->fresh()->relay_order)->toBe(1)
        ->and($seniorLead->fresh()->relay_activated_at)->not->toBeNull();

    expect($juniorLead->fresh()->relay_status)->toBe('standby')
        ->and($juniorLead->fresh()->relay_order)->toBe(2);
});

it('defaults lead badge to active when status is empty or uninitialized', function () {
    $lead = new Lead([
        'relay_status' => null,
    ]);

    $badge = $lead->relay_badge;
    expect($badge['label'])->toContain('Active')
        ->and($badge['class'])->toContain('badge-success');
});
