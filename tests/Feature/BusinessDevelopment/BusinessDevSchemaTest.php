<?php

namespace VentureDrake\LaravelCrm\Tests\Feature\BusinessDevelopment;

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\DealDerisking;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\PartnerProfile;
use VentureDrake\LaravelCrm\Models\ProcessingPerformanceLog;
use VentureDrake\LaravelCrm\Models\TelemetryEvent;
use VentureDrake\LaravelCrm\Tests\TestCase;

class BusinessDevSchemaTest extends TestCase
{
    /** @test */
    public function it_can_create_and_associate_business_development_models()
    {
        $organization = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Acme Enterprise Holdings',
        ]);

        $partnerOrg = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Apex Cloud Systems (SI Partner)',
        ]);

        $partner = PartnerProfile::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $partnerOrg->id,
            'partner_tier' => 'si',
            'status' => 'active',
            'region_code' => 'EMEA',
            'recruited_at' => now()->subMonths(3),
            'first_sale_at' => now()->subMonth(),
            'last_deal_at' => now(),
            'total_sourced_pipeline_amount' => 50000000,
        ]);

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'title' => 'Global Distribution Platform Expansion',
            'amount' => 120000,
        ]);

        // Associate partner
        $deal->update(['partner_id' => $partner->id]);
        $this->assertEquals($partner->id, $deal->fresh()->partner->id);

        // 1. Contract Telemetry
        $contract = Contract::create([
            'external_id' => (string) Str::uuid(),
            'deal_id' => $deal->id,
            'organization_id' => $organization->id,
            'contract_type' => 'enterprise',
            'term_months' => 36,
            'payment_terms' => 'NET_30',
            'sla_commitment_level' => 'mission_critical',
            'sla_penalty_clause' => true,
            'partner_rev_share_percent' => 15.00,
            'annual_price_escalation_percent' => 5.00,
            'minimum_commitment_amount' => 25000,
            'bespoke_work_ratio' => 0.12,
            'is_referenceable' => true,
            'is_design_partner' => true,
            'kickoff_at' => now()->addDays(7),
            'signed_at' => now(),
            'renewal_status' => 'renewed_expansion',
        ]);

        $this->assertNotNull($contract->id);
        $this->assertEquals($deal->id, $contract->deal->id);
        $this->assertTrue($contract->is_referenceable);
        $this->assertTrue($contract->sla_penalty_clause);
        $this->assertEquals(15.00, $contract->partner_rev_share_percent);

        // 2. Handoff Gates (auto-initialized by DealObserver)
        $this->assertCount(3, $deal->fresh()->handoffGates);

        $productGate = $deal->fresh()->handoffGates()->where('gate_type', 'product_capability')->first();
        $productGate->update([
            'status' => 'approved',
            'cleared_at' => now(),
            'metadata' => ['verified_apis' => ['streaming', 'webhooks']],
        ]);

        $opsGate = $deal->fresh()->handoffGates()->where('gate_type', 'operational_capacity')->first();

        $this->assertTrue($productGate->fresh()->isCleared());
        $this->assertFalse($opsGate->fresh()->isCleared());

        // 3. Deal Derisking Playbook
        $derisking = DealDerisking::create([
            'external_id' => (string) Str::uuid(),
            'deal_id' => $deal->id,
            'problem_statement' => 'Legacy provider lacks high-throughput streaming and SLA guarantees.',
            'pitfalls_identified' => 'Existing custom code risks vendor lock-in and high migration drag.',
            'unique_insight' => 'Decoupled telemetry ingestion avoids 22P02 boundary errors and isolates load.',
            'execution_plan' => 'Phased rollout starting with pilot department before global rollout.',
            'commercial_thesis_validated' => true,
            'loi_signed_at' => now()->subWeeks(2),
            'pilot_converted_at' => now(),
        ]);

        $this->assertEquals($derisking->id, $deal->fresh()->derisking->id);
        $this->assertTrue($deal->fresh()->derisking->commercial_thesis_validated);

        // 4. Telemetry Events
        $telemetry = TelemetryEvent::create([
            'external_id' => (string) Str::uuid(),
            'event_name' => 'sqo_converted',
            'entity_type' => 'deal',
            'entity_id' => $deal->external_id,
            'ansoff_quadrant' => 'market_penetration',
            'pirate_stage' => 'acquisition',
            'metric_key' => 'sqo_velocity_hours',
            'metric_value' => 48.5,
            'payload' => ['velocity_days' => 2],
            'recorded_at' => now(),
        ]);

        $this->assertNotNull($telemetry->id);
        $this->assertEquals(48.5, $telemetry->metric_value);

        // 5. Processing Performance Log
        $perfLog = ProcessingPerformanceLog::create([
            'external_id' => (string) Str::uuid(),
            'trace_id' => (string) Str::uuid(),
            'stage' => 'gate_check',
            'latency_ms' => 4.250,
            'status' => 'success',
            'metadata' => ['deal_id' => $deal->id],
        ]);

        $this->assertNotNull($perfLog->id);
        $this->assertEquals(4.250, $perfLog->latency_ms);
    }
}
