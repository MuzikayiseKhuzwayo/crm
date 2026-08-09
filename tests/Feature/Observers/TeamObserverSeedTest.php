<?php

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Observers\TeamObserver;

/**
 * The six lookup tables `seedCrmDataForTeam()` copies, with the extra columns
 * each one carries beyond `name` + `team_id`.
 */
const SEEDED_LOOKUPS = [
    'crm_labels' => ['hex' => '#ff0000', 'description' => 'A label'],
    'crm_organization_types' => ['description' => 'An organization type'],
    'crm_address_types' => ['description' => 'An address type'],
    'crm_contact_types' => ['description' => 'A contact type'],
    'crm_industries' => ['description' => 'An industry'],
    'crm_tax_rates' => ['description' => 'A tax rate', 'rate' => 10, 'default' => 0],
];

/**
 * Insert one global (team_id = null) row into each of the six lookup tables.
 * `crm_labels` is the only one of the six with an `external_id`.
 */
function seedGlobalLookups(string $name = 'Standard'): void
{
    foreach (SEEDED_LOOKUPS as $table => $columns) {
        DB::table($table)->insert($columns + [
            'name' => $name,
            'team_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ] + ($table === 'crm_labels' ? ['external_id' => (string) Uuid::uuid4()] : []));
    }
}

function teamLookupCounts(int $teamId): array
{
    $counts = [];

    foreach (array_keys(SEEDED_LOOKUPS) as $table) {
        $counts[$table] = DB::table($table)->where('team_id', $teamId)->count();
    }

    return $counts;
}

test('copies one row per global lookup into the team', function () {
    seedGlobalLookups();

    TeamObserver::seedCrmDataForTeam(42);

    expect(teamLookupCounts(42))->toBe(array_fill_keys(array_keys(SEEDED_LOOKUPS), 1));
});

test('the copied rows carry the global row values', function () {
    seedGlobalLookups();

    TeamObserver::seedCrmDataForTeam(42);

    $label = DB::table('crm_labels')->where('team_id', 42)->first();
    expect($label->name)->toBe('Standard')
        ->and($label->hex)->toBe('#ff0000')
        ->and($label->external_id)->not->toBeNull();

    expect(DB::table('crm_tax_rates')->where('team_id', 42)->value('rate'))->toEqual(10);
});

test('re-running does not give the team a second copy of any lookup table', function () {
    seedGlobalLookups();

    // First call: what TeamObserver::created() already did at 2.3.0.
    TeamObserver::seedCrmDataForTeam(42);
    $afterFirst = teamLookupCounts(42);

    // Second call: what db_update_1201 does to every pre-existing team.
    TeamObserver::seedCrmDataForTeam(42);

    expect(teamLookupCounts(42))->toBe($afterFirst)
        ->and($afterFirst)->toBe(array_fill_keys(array_keys(SEEDED_LOOKUPS), 1));
});

test('re-running does not re-key an already-copied label', function () {
    seedGlobalLookups();

    TeamObserver::seedCrmDataForTeam(42);
    $externalId = DB::table('crm_labels')->where('team_id', 42)->value('external_id');

    TeamObserver::seedCrmDataForTeam(42);

    expect(DB::table('crm_labels')->where('team_id', 42)->value('external_id'))->toBe($externalId);
});

test('leaves the global rows and other teams alone', function () {
    seedGlobalLookups();

    TeamObserver::seedCrmDataForTeam(42);
    TeamObserver::seedCrmDataForTeam(99);
    TeamObserver::seedCrmDataForTeam(42);

    foreach (array_keys(SEEDED_LOOKUPS) as $table) {
        expect(DB::table($table)->whereNull('team_id')->count())->toBe(1)
            ->and(DB::table($table)->where('team_id', 99)->count())->toBe(1);
    }
});

test('a lookup row the team soft-deleted is neither duplicated nor resurrected', function () {
    seedGlobalLookups();

    TeamObserver::seedCrmDataForTeam(42);
    DB::table('crm_labels')->where('team_id', 42)->update(['deleted_at' => now()]);

    TeamObserver::seedCrmDataForTeam(42);

    expect(DB::table('crm_labels')->where('team_id', 42)->count())->toBe(1)
        ->and(DB::table('crm_labels')->where('team_id', 42)->whereNull('deleted_at')->count())->toBe(0);
});

test('re-running does not give the team a second copy of a pipeline or its stages', function () {
    $globalPipelineId = DB::table('crm_pipelines')->insertGetId([
        'external_id' => (string) Uuid::uuid4(),
        'name' => 'Lead Pipeline',
        'model' => Lead::class,
        'team_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['New', 'Working', 'Won'] as $order => $name) {
        DB::table('crm_pipeline_stages')->insert([
            'external_id' => (string) Uuid::uuid4(),
            'name' => $name,
            'pipeline_id' => $globalPipelineId,
            'order' => $order,
            'team_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    TeamObserver::seedCrmDataForTeam(42);
    $stageExternalIds = DB::table('crm_pipeline_stages')->where('team_id', 42)->pluck('external_id', 'name')->all();

    TeamObserver::seedCrmDataForTeam(42);

    expect(DB::table('crm_pipelines')->where('team_id', 42)->count())->toBe(1)
        ->and(DB::table('crm_pipeline_stages')->where('team_id', 42)->count())->toBe(3)
        ->and(DB::table('crm_pipeline_stages')->where('team_id', 42)->pluck('external_id', 'name')->all())
        ->toBe($stageExternalIds);
});
