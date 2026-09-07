<?php

namespace VentureDrake\LaravelCrm\Tests\Feature\BusinessDevelopment;

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\PartnerProfile;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Services\Graph\CommercialGraphService;
use VentureDrake\LaravelCrm\Tests\TestCase;

class CommercialGraphTest extends TestCase
{
    protected CommercialGraphService $graphService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphService = app(CommercialGraphService::class);
    }

    /** @test */
    public function it_extracts_and_synchronizes_commercial_graph_triplets()
    {
        $org = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Fortune 50 Enterprise',
        ]);

        $champion = Person::create([
            'external_id' => (string) Str::uuid(),
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
        ]);

        $partnerOrg = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Accenture Alliance',
        ]);

        $partner = PartnerProfile::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $partnerOrg->id,
            'partner_tier' => 'si',
            'status' => 'active',
        ]);

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'person_id' => $champion->id,
            'partner_id' => $partner->id,
            'title' => 'Autonomous Data Platform',
            'amount' => 500000,
        ]);

        $contract = Contract::create([
            'external_id' => (string) Str::uuid(),
            'deal_id' => $deal->id,
            'organization_id' => $org->id,
            'is_design_partner' => true,
            'is_referenceable' => true,
            'sla_commitment_level' => 'mission_critical',
            'bespoke_work_ratio' => 0.05,
        ]);

        $triplets = $this->graphService->syncDealToGraph($deal->fresh());

        $this->assertNotEmpty($triplets);

        // Check for CONTRACTS_WITH claim
        $contractsWith = array_values(array_filter($triplets, fn ($t) => $t['predicate'] === 'CONTRACTS_WITH'));
        $this->assertCount(1, $contractsWith);
        $this->assertEquals('Fortune 50 Enterprise', $contractsWith[0]['subject']['name']);

        // Check for SOURCED claim from Partner
        $sourced = array_values(array_filter($triplets, fn ($t) => $t['predicate'] === 'SOURCED'));
        $this->assertCount(1, $sourced);
        $this->assertEquals('si', $sourced[0]['subject']['tier']);

        // Check for CHAMPIONS claim
        $champions = array_values(array_filter($triplets, fn ($t) => $t['predicate'] === 'CHAMPIONS'));
        $this->assertCount(1, $champions);
        $this->assertTrue($champions[0]['properties']['active']);

        // Check for CO_DESIGNS & SERVES_AS_ANCHOR claims
        $coDesigns = array_values(array_filter($triplets, fn ($t) => $t['predicate'] === 'CO_DESIGNS'));
        $this->assertCount(1, $coDesigns);
        $this->assertEquals('mission_critical', $coDesigns[0]['properties']['sla_level']);

        $anchorInfluence = $this->graphService->findAnchorInfluence($deal->external_id);
        $this->assertNotEmpty($anchorInfluence);
    }
}
