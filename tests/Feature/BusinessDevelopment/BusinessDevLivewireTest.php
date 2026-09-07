<?php

namespace VentureDrake\LaravelCrm\Tests\Feature\BusinessDevelopment;

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\BusinessDevelopment\CommercialIntelligenceDashboard;
use VentureDrake\LaravelCrm\Livewire\BusinessDevelopment\ContractTelemetryCard;
use VentureDrake\LaravelCrm\Livewire\BusinessDevelopment\DeriskingPlaybookWidget;
use VentureDrake\LaravelCrm\Livewire\BusinessDevelopment\HandoffGateModal;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Tests\TestCase;

class BusinessDevLivewireTest extends TestCase
{
    /** @test */
    public function it_can_render_and_interact_with_handoff_gate_modal()
    {
        $this->actingAsUser();

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'title' => 'Enterprise SLA Deal',
            'amount' => 100000,
        ]);

        Livewire::test(HandoffGateModal::class, ['deal' => $deal])
            ->assertSee('Operational Clearance Handoff Gates')
            ->call('open', 'product_capability')
            ->set('action', 'approve')
            ->call('submitGate');

        $this->assertTrue($deal->fresh()->handoffGates()->where('gate_type', 'product_capability')->first()->isCleared());
    }

    /** @test */
    public function it_can_render_and_save_contract_telemetry()
    {
        $this->actingAsUser();

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'title' => 'Channel Cloud Deal',
            'amount' => 250000,
        ]);

        Livewire::test(ContractTelemetryCard::class, ['deal' => $deal])
            ->assertSee('Contract Telemetry & Structural Terms')
            ->set('contractType', 'enterprise')
            ->set('termMonths', 36)
            ->set('partnerRevShare', 12.5)
            ->set('isReferenceable', true)
            ->call('save');

        $contract = $deal->fresh()->contract;
        $this->assertNotNull($contract);
        $this->assertEquals('enterprise', $contract->contract_type);
        $this->assertEquals(36, $contract->term_months);
        $this->assertEquals(12.5, $contract->partner_rev_share_percent);
        $this->assertTrue($contract->is_referenceable);
    }

    /** @test */
    public function it_can_render_and_save_derisking_playbook()
    {
        $this->actingAsUser();

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'title' => 'Strategic Design Deal',
            'amount' => 300000,
        ]);

        Livewire::test(DeriskingPlaybookWidget::class, ['deal' => $deal])
            ->assertSee('Derisking Deal Playbook')
            ->set('problemStatement', 'Legacy batch ETL fails SLA constraints.')
            ->set('uniqueInsight', 'Decoupled event telemetry with idempotency.')
            ->call('save');

        $derisking = $deal->fresh()->derisking;
        $this->assertNotNull($derisking);
        $this->assertEquals('Legacy batch ETL fails SLA constraints.', $derisking->problem_statement);
        $this->assertEquals('Decoupled event telemetry with idempotency.', $derisking->unique_insight);
    }

    /** @test */
    public function it_can_render_commercial_intelligence_dashboard()
    {
        $this->actingAsUser();

        Livewire::test(CommercialIntelligenceDashboard::class)
            ->assertSee('Commercial Intelligence & Telemetry Dashboard')
            ->assertSee('Market Penetration')
            ->call('selectQuadrant', 'product_development')
            ->assertSet('selectedQuadrant', 'product_development');
    }
}
