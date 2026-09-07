<?php

namespace VentureDrake\LaravelCrm\Tests\Feature\BusinessDevelopment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\PartnerProfile;
use VentureDrake\LaravelCrm\Services\BusinessDevelopment\DeriskingPlaybookService;
use VentureDrake\LaravelCrm\Services\BusinessDevelopment\HandoffGateService;
use VentureDrake\LaravelCrm\Services\Graph\CommercialGraphService;
use VentureDrake\LaravelCrm\Services\Telemetry\TelemetryIngestionService;
use VentureDrake\LaravelCrm\Support\UuidNormalizer;
use VentureDrake\LaravelCrm\Tests\TestCase;

/**
 * Dubstrata Systems Anti-Mirage Protocol Verification
 * Enforces live client runtime dispatch and destination datastore row persistence.
 */
class LiveTelemetryPersistenceTest extends TestCase
{
    protected TelemetryIngestionService $telemetryIngestion;

    protected HandoffGateService $gateService;

    protected DeriskingPlaybookService $playbookService;

    protected CommercialGraphService $graphService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->telemetryIngestion = app(TelemetryIngestionService::class);
        $this->gateService = app(HandoffGateService::class);
        $this->playbookService = app(DeriskingPlaybookService::class);
        $this->graphService = app(CommercialGraphService::class);
    }

    /** @test */
    public function it_asserts_live_runtime_wiring_and_persists_non_zero_rows_across_all_destination_tables()
    {
        $user = $this->actingAsUser();

        // 1. Ingest with raw string identifiers to verify Defensive Boundary Normalization (22P02 Immunity)
        $rawGuestStringId = 'guest_prospect_session_cookie_abc_999';
        $normalizedId = UuidNormalizer::ensureValidUuid($rawGuestStringId);

        $event1 = $this->telemetryIngestion->ingest([
            'event_name' => 'commercial_thesis_engaged',
            'entity_type' => 'prospect',
            'entity_id' => $rawGuestStringId, // Un-normalized string input
            'ansoff_quadrant' => 'diversification',
            'pirate_stage' => 'acquisition',
            'metric_key' => 'thesis_engagement_rate',
            'metric_value' => 1.0,
            'payload' => ['channel' => 'executive_briefing'],
        ]);

        $this->assertNotNull($event1);
        $this->assertEquals($normalizedId, $event1->entity_id);

        // 2. Test Idempotency Engine: Re-dispatching same payload with same idempotency key
        $idempotencyKey = 'req_idemp_key_12345678';
        $event2 = $this->telemetryIngestion->ingest([
            'event_name' => 'sqo_qualified',
            'entity_type' => 'deal',
            'entity_id' => (string) Str::uuid(),
            'ansoff_quadrant' => 'market_penetration',
            'pirate_stage' => 'acquisition',
            'metric_key' => 'sqo_velocity',
            'metric_value' => 12.5,
        ], $idempotencyKey);

        // Duplicate dispatch
        $duplicateEvent = $this->telemetryIngestion->ingest([
            'event_name' => 'sqo_qualified',
            'entity_type' => 'deal',
            'entity_id' => (string) Str::uuid(),
            'ansoff_quadrant' => 'market_penetration',
            'pirate_stage' => 'acquisition',
            'metric_key' => 'sqo_velocity',
            'metric_value' => 12.5,
        ], $idempotencyKey);

        $this->assertEquals($event2->external_id, $duplicateEvent->external_id);

        // 3. Live Commercial Pipeline Progression
        $org = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Global Tier 1 Enterprise',
        ]);

        $partnerOrg = Organization::create([
            'external_id' => (string) Str::uuid(),
            'name' => 'Regional Distribution Partner',
        ]);

        $partner = PartnerProfile::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $partnerOrg->id,
            'partner_tier' => 'distributor',
            'status' => 'active',
            'region_code' => 'APAC',
        ]);

        $deal = Deal::create([
            'external_id' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'partner_id' => $partner->id,
            'title' => 'Multi-Region Enterprise Rollout',
            'amount' => 450000,
        ]);

        // Operational Pillar 1: Contract Telemetry
        $contract = Contract::create([
            'external_id' => (string) Str::uuid(),
            'deal_id' => $deal->id,
            'organization_id' => $org->id,
            'contract_type' => 'enterprise',
            'term_months' => 36,
            'payment_terms' => 'ANNUAL_PREPAID',
            'sla_commitment_level' => 'mission_critical',
            'sla_penalty_clause' => true,
            'partner_rev_share_percent' => 10.00,
            'annual_price_escalation_percent' => 4.50,
            'minimum_commitment_amount' => 50000,
            'bespoke_work_ratio' => 0.08,
            'is_referenceable' => true,
            'is_design_partner' => true,
            'signed_at' => now(),
            'kickoff_at' => now()->addDays(14),
            'renewal_status' => 'renewed_expansion',
        ]);

        // Operational Pillar 2: Derisking Playbook
        $this->playbookService->updateNarrative(
            $deal,
            'Fragmented distributor network causes margin leakage.',
            'Un-monitored SLAs trigger commercial contract penalties.',
            'Standardized contract telemetry with automated handoff stage-gates guarantees margins.',
            'Pilot in 2 regions before global commercial rollout.'
        );

        // Operational Pillar 3: Cross-Functional Handoff Stage-Gates
        $this->gateService->approveGate($deal, 'product_capability', $user->id, ['arch_approved' => true]);
        $this->gateService->approveGate($deal, 'operational_capacity', $user->id, ['ops_onboarding_cleared' => true]);
        $this->gateService->approveGate($deal, 'financial_margin', $user->id, ['cfo_approved' => true]);

        // Transition deal to Won
        $deal->update([
            'closed_status' => 'won',
            'closed_at' => now(),
        ]);

        // Commercial Knowledge Graph Extraction
        $claims = $this->graphService->syncDealToGraph($deal->fresh());
        $this->assertNotEmpty($claims);

        // =========================================================================
        // ANTI-MIRAGE LIVE DATASTORE ASSERTIONS
        // Explicitly query every destination SQL table to assert non-zero row counts
        // =========================================================================
        $contractsCount = DB::table(config('laravel-crm.db_table_prefix').'contracts')->count();
        $gatesCount = DB::table(config('laravel-crm.db_table_prefix').'handoff_gates')->count();
        $deriskingCount = DB::table(config('laravel-crm.db_table_prefix').'deal_derisking')->count();
        $partnerCount = DB::table(config('laravel-crm.db_table_prefix').'partner_profiles')->count();
        $telemetryCount = DB::table(config('laravel-crm.db_table_prefix').'telemetry_events')->count();
        $perfLogCount = DB::table(config('laravel-crm.db_table_prefix').'processing_performance_logs')->count();

        $this->assertGreaterThan(0, $contractsCount, 'Destination table [crm_contracts] must contain persisted rows.');
        $this->assertGreaterThan(0, $gatesCount, 'Destination table [crm_handoff_gates] must contain persisted rows.');
        $this->assertGreaterThan(0, $deriskingCount, 'Destination table [crm_deal_derisking] must contain persisted rows.');
        $this->assertGreaterThan(0, $partnerCount, 'Destination table [crm_partner_profiles] must contain persisted rows.');
        $this->assertGreaterThan(0, $telemetryCount, 'Destination table [crm_telemetry_events] must contain persisted rows.');
        $this->assertGreaterThan(0, $perfLogCount, 'Destination table [crm_processing_performance_logs] must contain persisted rows.');

        // Verify exact contract data integrity in destination table
        $persistedContract = DB::table(config('laravel-crm.db_table_prefix').'contracts')->where('deal_id', $deal->id)->first();
        $this->assertEquals('enterprise', $persistedContract->contract_type);
        $this->assertEquals(36, $persistedContract->term_months);
        $this->assertEquals(1, $persistedContract->is_referenceable);
        $this->assertEquals(1, $persistedContract->is_design_partner);

        // Verify performance logging telemetry
        $idempotentLogs = DB::table(config('laravel-crm.db_table_prefix').'processing_performance_logs')
            ->where('status', 'idempotent_skip')
            ->count();
        $this->assertGreaterThan(0, $idempotentLogs, 'Processing performance log must record idempotent skipping events.');
    }
}
