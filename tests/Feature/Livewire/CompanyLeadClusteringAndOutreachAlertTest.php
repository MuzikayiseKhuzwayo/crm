<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadIndex;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Livewire\RelatedLeads;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Agent Hunter', 'email' => 'hunter@example.com']);
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

    $this->stageEngaged = PipelineStage::create([
        'external_id' => Uuid::uuid4()->toString(),
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Engaged / Qualified',
        'order' => 3,
    ]);
});

it('detects uncontacted colleague cluster for leads belonging to the same company', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Acme Corporation',
    ]);

    $personAlice = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'organization_id' => $org->id,
    ]);

    $personBob = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Bob',
        'last_name' => 'Jones',
        'organization_id' => $org->id,
    ]);

    $leadAlice = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Acme Corp - Alice Smith',
        'organization_id' => $org->id,
        'person_id' => $personAlice->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $leadBob = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Acme Corp - Bob Jones',
        'organization_id' => $org->id,
        'person_id' => $personBob->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $summary = $org->outreachSummary($leadAlice->id);

    expect($summary['status'])->toBe('uncontacted')
        ->and($summary['total_leads_count'])->toBe(2)
        ->and($summary['other_leads'])->toHaveCount(1)
        ->and($summary['other_leads']->first()->id)->toBe($leadBob->id);

    // Livewire LeadShow test
    Livewire::test(LeadShow::class, ['lead' => $leadAlice])
        ->assertSee('Acme Corporation')
        ->assertSee('Account Roster')
        ->assertSee('Bob Jones');
});

it('alerts active outreach collision when a colleague lead is in progress', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Globex Inc',
    ]);

    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Globex - Alice',
        'organization_id' => $org->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageEngaged->id, // Engaged (stage order 3)
        'user_owner_id' => $this->user->id,
    ]);

    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Globex - Charlie',
        'organization_id' => $org->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $summary = $org->outreachSummary($lead2->id);

    expect($summary['status'])->toBe('active')
        ->and($summary['has_previous_contact'])->toBeTrue();

    Livewire::test(LeadShow::class, ['lead' => $lead2])
        ->assertSee('Active Outreach in Progress')
        ->assertSee('Globex Inc');
});

it('flags company as disqualified and propagates warning to all leads of that company', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Initech Industries',
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Initech - Peter Gibbons',
        'organization_id' => $org->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    // Mark disqualified via LeadShow component
    Livewire::test(LeadShow::class, ['lead' => $lead])
        ->call('markCompanyDisqualified')
        ->assertHasNoErrors();

    expect($org->fresh()->isDisqualified())->toBeTrue();

    // Now viewing this lead or any other lead at Initech shows the Red Warning Banner
    Livewire::test(LeadShow::class, ['lead' => $lead->fresh()])
        ->assertSee('Company Flagged as Do Not Contact')
        ->assertSee('Do not waste time pitching contacts at this company');

    // Clear disqualified
    Livewire::test(LeadShow::class, ['lead' => $lead->fresh()])
        ->call('clearCompanyDisqualified')
        ->assertHasNoErrors();

    expect($org->fresh()->isDisqualified())->toBeFalse();
});

it('lists all company leads inside RelatedLeads component on organization show', function () {
    $org = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Hooli Tech',
    ]);

    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Hooli - Richard',
        'organization_id' => $org->id,
        'user_owner_id' => $this->user->id,
    ]);

    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Hooli - Dinesh',
        'organization_id' => $org->id,
        'user_owner_id' => $this->user->id,
    ]);

    Livewire::test(RelatedLeads::class, ['model' => $org])
        ->assertSee('Hooli - Richard')
        ->assertSee('Hooli - Dinesh')
        ->assertSee('(2)');
});

it('filters leads by company outreach status on LeadIndex', function () {
    $freshOrg = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'FreshCo',
    ]);
    $freshLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Fresh Lead One',
        'organization_id' => $freshOrg->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    $dncOrg = Organization::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'BlockedCo',
    ]);
    $dncOrg->markDisqualified();
    $dncLead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Blocked Lead Two',
        'organization_id' => $dncOrg->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->stageCold->id,
        'user_owner_id' => $this->user->id,
    ]);

    // Test uncontacted filter
    Livewire::test(LeadIndex::class)
        ->set('company_status', 'uncontacted')
        ->assertSee('Fresh Lead One')
        ->assertDontSee('Blocked Lead Two');

    // Test disqualified filter
    Livewire::test(LeadIndex::class)
        ->set('company_status', 'disqualified')
        ->assertSee('Blocked Lead Two')
        ->assertDontSee('Fresh Lead One');
});
