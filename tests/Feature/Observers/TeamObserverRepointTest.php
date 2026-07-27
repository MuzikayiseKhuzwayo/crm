<?php

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Observers\TeamObserver;

/**
 * Seed a global (team_id = null) pipeline for the given entity model class
 * and matching per-team pipeline + stages under the target team, keyed by
 * name so the helper's name-map matching has something to hit. Returns
 * ['global' => globalPipelineId, 'team' => teamPipelineId].
 */
function seedPipelinePair(string $modelClass, string $pipelineName, array $stageNames, int $teamId): array
{
    $globalPipelineId = DB::table('crm_pipelines')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'name' => $pipelineName,
        'model' => $modelClass,
        'team_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $teamPipelineId = DB::table('crm_pipelines')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'name' => $pipelineName,
        'model' => $modelClass,
        'team_id' => $teamId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($stageNames as $order => $name) {
        DB::table('crm_pipeline_stages')->insert([
            'external_id' => (string) Uuid::uuid4(),
            'name' => $name,
            'pipeline_id' => $globalPipelineId,
            'order' => $order,
            'team_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('crm_pipeline_stages')->insert([
            'external_id' => (string) Uuid::uuid4(),
            'name' => $name,
            'pipeline_id' => $teamPipelineId,
            'order' => $order,
            'team_id' => $teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return ['global' => $globalPipelineId, 'team' => $teamPipelineId];
}

function globalStageId(int $pipelineId, string $name): int
{
    return DB::table('crm_pipeline_stages')
        ->where('pipeline_id', $pipelineId)
        ->whereNull('team_id')
        ->where('name', $name)
        ->value('id');
}

function teamStageId(int $pipelineId, string $name, int $teamId): int
{
    return DB::table('crm_pipeline_stages')
        ->where('pipeline_id', $pipelineId)
        ->where('team_id', $teamId)
        ->where('name', $name)
        ->value('id');
}

test('repointCrmRecordsToTeamPipelines exists as a public static void method taking int', function () {
    $ref = new ReflectionMethod(TeamObserver::class, 'repointCrmRecordsToTeamPipelines');

    expect($ref->isPublic())->toBeTrue()
        ->and($ref->isStatic())->toBeTrue()
        ->and((string) $ref->getReturnType())->toBe('void')
        ->and($ref->getNumberOfRequiredParameters())->toBe(1);

    $params = $ref->getParameters();
    expect((string) $params[0]->getType())->toBe('int');
});

test('re-points lead records with global stage id to matching team stage id', function () {
    $pipelines = seedPipelinePair(Lead::class, 'Lead Pipeline', ['New', 'Working', 'Won'], 42);
    $global = globalStageId($pipelines['global'], 'Working');
    $team = teamStageId($pipelines['team'], 'Working', 42);

    $leadId = DB::table('crm_leads')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'title' => 'Orphaned lead',
        'pipeline_stage_id' => $global,
        'team_id' => 42,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    TeamObserver::repointCrmRecordsToTeamPipelines(42);

    expect(DB::table('crm_leads')->where('id', $leadId)->value('pipeline_stage_id'))
        ->toBe($team);
});

test('leaves records from other teams untouched', function () {
    $pipelines = seedPipelinePair(Lead::class, 'Lead Pipeline', ['New', 'Working'], 42);
    $global = globalStageId($pipelines['global'], 'New');

    $ourLead = DB::table('crm_leads')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'title' => 'Team 42 lead',
        'pipeline_stage_id' => $global,
        'team_id' => 42,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherLead = DB::table('crm_leads')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'title' => 'Team 99 lead',
        'pipeline_stage_id' => $global,
        'team_id' => 99,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    TeamObserver::repointCrmRecordsToTeamPipelines(42);

    expect(DB::table('crm_leads')->where('id', $ourLead)->value('pipeline_stage_id'))
        ->not->toBe($global)
        ->and(DB::table('crm_leads')->where('id', $otherLead)->value('pipeline_stage_id'))
        ->toBe($global);
});

test('matches stages within the correct pipeline model only', function () {
    $leadPipelines = seedPipelinePair(Lead::class, 'Lead Pipeline', ['Won'], 42);
    $dealPipelines = seedPipelinePair(Deal::class, 'Deal Pipeline', ['Won'], 42);

    $leadGlobal = globalStageId($leadPipelines['global'], 'Won');
    $leadTeam = teamStageId($leadPipelines['team'], 'Won', 42);
    $dealTeam = teamStageId($dealPipelines['team'], 'Won', 42);

    $leadId = DB::table('crm_leads')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'title' => 'Lead Won',
        'pipeline_stage_id' => $leadGlobal,
        'team_id' => 42,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    TeamObserver::repointCrmRecordsToTeamPipelines(42);

    // Lead lands on its own team stage id, NOT the Deal team stage
    expect(DB::table('crm_leads')->where('id', $leadId)->value('pipeline_stage_id'))
        ->toBe($leadTeam)
        ->not->toBe($dealTeam);
});

test('leaves unmatched stage names untouched (no null-outs, no exceptions)', function () {
    $pipelines = seedPipelinePair(Lead::class, 'Lead Pipeline', ['New', 'Working'], 42);
    $global = globalStageId($pipelines['global'], 'New');

    // Remove the matching per-team stage — leaves the record's global stage id
    // with no name-match under the per-team pipeline.
    DB::table('crm_pipeline_stages')
        ->where('pipeline_id', $pipelines['team'])
        ->where('name', 'New')
        ->delete();

    $leadId = DB::table('crm_leads')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'title' => 'Unmatched stage',
        'pipeline_stage_id' => $global,
        'team_id' => 42,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    TeamObserver::repointCrmRecordsToTeamPipelines(42);

    expect(DB::table('crm_leads')->where('id', $leadId)->value('pipeline_stage_id'))
        ->toBe($global);
});

test('running the helper twice is a no-op on the second run', function () {
    $pipelines = seedPipelinePair(Lead::class, 'Lead Pipeline', ['New'], 42);
    $global = globalStageId($pipelines['global'], 'New');
    $team = teamStageId($pipelines['team'], 'New', 42);

    $leadId = DB::table('crm_leads')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'title' => 'Twice-repointed lead',
        'pipeline_stage_id' => $global,
        'team_id' => 42,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    TeamObserver::repointCrmRecordsToTeamPipelines(42);
    expect(DB::table('crm_leads')->where('id', $leadId)->value('pipeline_stage_id'))
        ->toBe($team);

    TeamObserver::repointCrmRecordsToTeamPipelines(42);
    expect(DB::table('crm_leads')->where('id', $leadId)->value('pipeline_stage_id'))
        ->toBe($team);
});

test('covers all 5 test-schema-available entity tables (leads/deals/quotes/orders/invoices) in a single call', function () {
    $tables = [
        'leads' => Lead::class,
        'deals' => Deal::class,
        'quotes' => Quote::class,
        'orders' => Order::class,
        'invoices' => Invoice::class,
    ];

    $stageIds = [];
    foreach ($tables as $table => $modelClass) {
        $pipelines = seedPipelinePair($modelClass, ucfirst($table).' Pipeline', ['Stage A'], 42);
        $stageIds[$table] = [
            'global' => globalStageId($pipelines['global'], 'Stage A'),
            'team' => teamStageId($pipelines['team'], 'Stage A', 42),
        ];
    }

    $recordIds = [
        'leads' => DB::table('crm_leads')->insertGetId(['external_id' => (string) Uuid::uuid4(), 'title' => 'L', 'pipeline_stage_id' => $stageIds['leads']['global'], 'team_id' => 42, 'created_at' => now(), 'updated_at' => now()]),
        'deals' => DB::table('crm_deals')->insertGetId(['external_id' => (string) Uuid::uuid4(), 'title' => 'D', 'pipeline_stage_id' => $stageIds['deals']['global'], 'team_id' => 42, 'created_at' => now(), 'updated_at' => now()]),
        'quotes' => DB::table('crm_quotes')->insertGetId(['external_id' => (string) Uuid::uuid4(), 'title' => 'Q', 'pipeline_stage_id' => $stageIds['quotes']['global'], 'team_id' => 42, 'created_at' => now(), 'updated_at' => now()]),
        'orders' => DB::table('crm_orders')->insertGetId(['external_id' => (string) Uuid::uuid4(), 'pipeline_stage_id' => $stageIds['orders']['global'], 'team_id' => 42, 'created_at' => now(), 'updated_at' => now()]),
        'invoices' => DB::table('crm_invoices')->insertGetId(['external_id' => (string) Uuid::uuid4(), 'invoice_id' => 'INV-001', 'pipeline_stage_id' => $stageIds['invoices']['global'], 'team_id' => 42, 'created_at' => now(), 'updated_at' => now()]),
    ];

    TeamObserver::repointCrmRecordsToTeamPipelines(42);

    foreach (array_keys($tables) as $table) {
        expect(DB::table('crm_'.$table)->where('id', $recordIds[$table])->value('pipeline_stage_id'))
            ->toBe($stageIds[$table]['team']);
    }
});

test('gracefully skips entity models whose table or pipeline_stage_id column is missing from the current schema', function () {
    // Even without seeding any pipelines/stages/records, the helper should
    // complete without exceptions. Locks the AC's "no exceptions" guarantee
    // against test-schema-vs-production divergence (crm_deliveries has no
    // table and crm_purchase_orders has no pipeline_stage_id column in the
    // current test schema).
    expect(fn () => TeamObserver::repointCrmRecordsToTeamPipelines(42))->not->toThrow(Throwable::class);
});
