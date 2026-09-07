<?php

namespace VentureDrake\LaravelCrm\Tests\Feature\BusinessDevelopment;

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Exceptions\HandoffGateIncompleteException;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Services\BusinessDevelopment\DeriskingPlaybookService;
use VentureDrake\LaravelCrm\Services\BusinessDevelopment\HandoffGateService;
use VentureDrake\LaravelCrm\Tests\TestCase;

class HandoffGateEnforcementTest extends TestCase
{
    protected HandoffGateService $gateService;

    protected DeriskingPlaybookService $playbookService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateService = app(HandoffGateService::class);
        $this->playbookService = app(DeriskingPlaybookService::class);
    }

    /** @test */
    public function it_blocks_deal_from_moving_to_won_when_gates_are_pending()
    {
        $organization = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Target Enterprise Inc.',
        ]);

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'title' => 'Core Infrastructure Modernization',
            'amount' => 150000,
        ]);

        // Default gates should be initialized
        $this->assertCount(3, $deal->fresh()->handoffGates);
        $this->assertFalse($this->gateService->canAdvanceToWon($deal));

        // Attempting to mark deal as won should throw HandoffGateIncompleteException
        $this->expectException(HandoffGateIncompleteException::class);
        $deal->update(['closed_status' => 'won']);
    }

    /** @test */
    public function it_allows_deal_to_move_to_won_when_all_gates_are_cleared()
    {
        $organization = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Approved Enterprise Corp.',
        ]);

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'title' => 'Cloud Migration Contract',
            'amount' => 200000,
        ]);

        // Clear all 3 required gates
        $this->gateService->approveGate($deal, 'product_capability', 1, ['checked_apis' => true]);
        $this->gateService->approveGate($deal, 'operational_capacity', 1, ['staffing_cleared' => true]);
        $this->gateService->waiveGate($deal, 'financial_margin', 1, 'Executive override approved by CFO');

        $this->assertTrue($this->gateService->canAdvanceToWon($deal));

        // Now updating deal to won succeeds without exception
        $deal->update([
            'closed_status' => 'won',
            'closed_at' => now(),
        ]);

        $this->assertEquals('won', $deal->fresh()->closed_status);
        $this->assertNotNull($deal->fresh()->closed_at);
    }

    /** @test */
    public function it_tracks_derisking_playbook_narrative_and_milestones()
    {
        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'title' => 'Diversification Exploration Venture',
            'amount' => 80000,
        ]);

        $this->playbookService->updateNarrative(
            $deal,
            'High latency in legacy reporting pipeline.',
            'Custom bespoke engineering traps margin.',
            'Decoupled telemetry with boundary normalizer prevents 22P02 database corruption.',
            'Roll out to 1 department pilot then execute enterprise rollover.'
        );

        $derisking = $deal->fresh()->derisking;
        $this->assertNotNull($derisking);
        $this->assertStringContainsString('Decoupled telemetry', $derisking->unique_insight);

        // Record LOI and Pilot Conversion
        $this->playbookService->recordLoiSigned($deal);
        $this->assertNotNull($deal->fresh()->derisking->loi_signed_at);

        $this->playbookService->recordPilotConverted($deal);
        $this->assertNotNull($deal->fresh()->derisking->pilot_converted_at);
        $this->assertTrue($deal->fresh()->derisking->commercial_thesis_validated);
    }
}
