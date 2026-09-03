<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('filters leads by status, pipeline stage, source, amount, and date ranges', function () {
    $user = User::create(['name' => 'Lead Manager', 'email' => 'manager@example.com']);
    $this->actingAs($user);

    $source = LeadSource::create(['name' => 'LinkedIn Search']);
    $stage = PipelineStage::create(['name' => 'Qualified Lead', 'order' => 1]);

    $lead1 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Quantum Signal Subscriptions',
        'amount' => 1500000, // $15,000
        'lead_source_id' => $source->id,
        'pipeline_stage_id' => $stage->id,
        'user_owner_id' => $user->id,
    ]);

    $lead2 = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Small Retainer Deal',
        'amount' => 200000, // $2,000
        'user_owner_id' => null,
    ]);

    Livewire::test(LeadIndex::class)
        ->set('lead_status', 'active')
        ->assertSet('lead_status', 'active')
        ->set('amount_preset', '5k_25k')
        ->assertSet('amount_preset', '5k_25k')
        ->set('lead_source_id', [$source->id])
        ->assertSet('lead_source_id', [$source->id])
        ->set('pipeline_stage_id', [$stage->id])
        ->assertSet('pipeline_stage_id', [$stage->id])
        ->call('clear')
        ->assertSet('amount_preset', '')
        ->assertSet('lead_source_id', []);
});

it('orders leads by created_at, lead_id, title, and amount', function () {
    $user = User::create(['name' => 'Sorter User', 'email' => 'sorter@example.com']);
    $this->actingAs($user);

    $leadA = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'lead_id' => 'TFA-L1001',
        'title' => 'Alpha Asset Deal',
        'amount' => 1000000,
        'user_owner_id' => $user->id,
    ]);

    $leadB = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'lead_id' => 'TFA-L1002',
        'title' => 'Beta Quant Deal',
        'amount' => 5000000,
        'user_owner_id' => $user->id,
    ]);

    Livewire::test(LeadIndex::class)
        ->set('sortBy', ['column' => 'amount', 'direction' => 'desc'])
        ->assertSet('sortBy', ['column' => 'amount', 'direction' => 'desc']);
});
