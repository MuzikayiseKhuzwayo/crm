<?php

namespace VentureDrake\LaravelCrm\Observers;

use App\Team;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\PermissionRegistrar;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Role;

class TeamObserver
{
    /**
     * Handle the team "creating" event.
     *
     * @return void
     */
    public function creating(Team $team)
    {
        //
    }

    /**
     * Handle the team "created" event.
     *
     * @return void
     */
    public function created(Team $team)
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $tableNames = config('permission.table_names');

        if (config('laravel-crm.teams')) {
            // Get the roles
            foreach (DB::table($tableNames['roles'])
                ->where('crm_role', 1)
                ->whereNull('team_id')
                ->get() as $role) {
                DB::table($tableNames['roles'])->updateOrInsert([
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'description' => $role->description,
                    'crm_role' => $role->crm_role,
                    'team_id' => $team->id,
                ], [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                if ($newRole = DB::table($tableNames['roles'])->where([
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'description' => $role->description,
                    'crm_role' => $role->crm_role,
                    'team_id' => $team->id,
                ])->first()) {
                    foreach (DB::table($tableNames['permissions'])
                        ->leftJoin($tableNames['role_has_permissions'], $tableNames['permissions'].'.id', '=', $tableNames['role_has_permissions'].'.permission_id')
                        ->where($tableNames['role_has_permissions'].'.role_id', $role->id)
                        ->get() as $permission) {
                        DB::table($tableNames['role_has_permissions'])->updateOrInsert([
                            'permission_id' => $permission->id,
                            'role_id' => $newRole->id,
                        ]);
                    }
                }
            }

            if ($role = Role::where([
                'name' => 'Owner',
                'team_id' => $team->id,
                'crm_role' => 1,
            ])->first()) {
                DB::table($tableNames['model_has_roles'])->insert([
                    'role_id' => $role->id,
                    'model_type' => auth()->user()->getMorphClass(),
                    'model_id' => auth()->user()->id,
                    'team_id' => $team->id,
                ]);
            }

            static::seedCrmDataForTeam($team->id);
        }
    }

    /**
     * Seed the per-team CRM lookup data (labels, organization/address/contact
     * types, industries, tax rates, pipelines + pipeline stages) by copying
     * the global (team_id = null) rows into the target team.
     *
     * Callers are expected to decide whether teams are enabled; this helper
     * unconditionally copies the per-entity blocks so console commands can
     * invoke it directly for a specific team without re-checking the
     * `laravel-crm.teams` config guard.
     *
     * Every block is idempotent. The six lookup tables are copied via
     * `updateOrInsert` keyed on `team_id + name`; pipelines and pipeline
     * stages via `team_id + model` and `team_id + pipeline_id + name`. So the
     * helper is safe to re-run against a team that already holds this data —
     * no duplicates, no exceptions. `PipelineStageProbability` rows are shared
     * globally and are NOT copied per team.
     */
    public static function seedCrmDataForTeam(int $teamId): void
    {
        $prefix = config('laravel-crm.db_table_prefix');

        foreach (DB::table($prefix.'labels')
            ->whereNull('team_id')
            ->get() as $label) {
            static::upsertTeamRow($prefix.'labels', ['team_id' => $teamId, 'name' => $label->name], [
                'hex' => $label->hex,
                'description' => $label->description,
            ], [
                'external_id' => Uuid::uuid4()->toString(),
            ]);
        }

        foreach (DB::table($prefix.'organization_types')
            ->whereNull('team_id')
            ->get() as $organizationType) {
            static::upsertTeamRow($prefix.'organization_types', ['team_id' => $teamId, 'name' => $organizationType->name], [
                'description' => $organizationType->description,
            ]);
        }

        foreach (DB::table($prefix.'address_types')
            ->whereNull('team_id')
            ->get() as $addressType) {
            static::upsertTeamRow($prefix.'address_types', ['team_id' => $teamId, 'name' => $addressType->name], [
                'description' => $addressType->description,
            ]);
        }

        foreach (DB::table($prefix.'contact_types')
            ->whereNull('team_id')
            ->get() as $contactType) {
            static::upsertTeamRow($prefix.'contact_types', ['team_id' => $teamId, 'name' => $contactType->name], [
                'description' => $contactType->description,
            ]);
        }

        foreach (DB::table($prefix.'industries')
            ->whereNull('team_id')
            ->get() as $industry) {
            static::upsertTeamRow($prefix.'industries', ['team_id' => $teamId, 'name' => $industry->name], [
                'description' => $industry->description,
            ]);
        }

        foreach (DB::table($prefix.'tax_rates')
            ->whereNull('team_id')
            ->get() as $taxRate) {
            static::upsertTeamRow($prefix.'tax_rates', ['team_id' => $teamId, 'name' => $taxRate->name], [
                'description' => $taxRate->description,
                'rate' => $taxRate->rate,
                'default' => $taxRate->default,
            ]);
        }

        // Pipelines + pipeline stages, keyed on `team_id + model` and
        // `team_id + pipeline_id + name`. `PipelineStageProbability` rows are
        // shared globally and are NOT copied per team.
        foreach (DB::table($prefix.'pipelines')
            ->whereNull('team_id')
            ->get() as $pipeline) {
            static::upsertTeamRow($prefix.'pipelines', [
                'team_id' => $teamId,
                'model' => $pipeline->model,
            ], [
                'name' => $pipeline->name,
            ], [
                'external_id' => Uuid::uuid4()->toString(),
            ]);

            $teamPipelineId = DB::table($prefix.'pipelines')
                ->where('team_id', $teamId)
                ->where('model', $pipeline->model)
                ->value('id');

            if ($teamPipelineId === null) {
                continue;
            }

            foreach (DB::table($prefix.'pipeline_stages')
                ->where('pipeline_id', $pipeline->id)
                ->whereNull('team_id')
                ->get() as $stage) {
                static::upsertTeamRow($prefix.'pipeline_stages', [
                    'team_id' => $teamId,
                    'pipeline_id' => $teamPipelineId,
                    'name' => $stage->name,
                ], [
                    'description' => $stage->description ?? null,
                    'pipeline_stage_probability_id' => $stage->pipeline_stage_probability_id ?? null,
                    'order' => $stage->order ?? 0,
                    'color' => $stage->color ?? null,
                ], [
                    'external_id' => Uuid::uuid4()->toString(),
                ]);
            }
        }
    }

    /**
     * Copy one global row into a team, keyed on `$attributes`.
     *
     * `updateOrInsert` keeps the copy idempotent: re-running against a team
     * that already holds the row refreshes it rather than inserting a second
     * copy. `$insertOnly` values — an `external_id`, `created_at` — are
     * written on first insert only, so a re-run never re-keys or re-stamps a
     * row the host may already be linking to. A row the team soft-deleted
     * stays deleted; it matches the key, so it is neither duplicated nor
     * resurrected.
     */
    private static function upsertTeamRow(string $table, array $attributes, array $values, array $insertOnly = []): void
    {
        if (! DB::table($table)->where($attributes)->exists()) {
            $values = array_merge($values, $insertOnly, ['created_at' => Carbon::now()]);
        }

        DB::table($table)->updateOrInsert($attributes, $values + ['updated_at' => Carbon::now()]);
    }

    /**
     * Re-point existing lead/deal/quote/order/invoice/delivery/purchase-order
     * rows whose `pipeline_stage_id` still references a global (team_id = null)
     * pipeline stage at the matching per-team stage — matched by stage name
     * within the same pipeline model (Lead-model global stages map only to
     * Lead-model per-team stages, etc.).
     *
     * Only records with `team_id = $teamId` are updated; records from other
     * teams are untouched. Stages whose name does not appear in the per-team
     * pipeline are left unchanged (safe no-op — no data loss, documented as a
     * known limitation so hosts can rename or add the missing stage first
     * and re-run the helper).
     *
     * Running the helper twice is a no-op on the second run because the
     * matching WHERE clause targets `pipeline_stage_id = global_id` and the
     * first run has already replaced every match with the per-team id.
     */
    public static function repointCrmRecordsToTeamPipelines(int $teamId): void
    {
        $prefix = config('laravel-crm.db_table_prefix');

        $models = [
            Lead::class => $prefix.'leads',
            Deal::class => $prefix.'deals',
            Quote::class => $prefix.'quotes',
            Order::class => $prefix.'orders',
            Invoice::class => $prefix.'invoices',
            Delivery::class => $prefix.'deliveries',
            PurchaseOrder::class => $prefix.'purchase_orders',
        ];

        foreach ($models as $modelClass => $recordTable) {
            if (! Schema::hasTable($recordTable) || ! Schema::hasColumn($recordTable, 'pipeline_stage_id')) {
                continue;
            }

            $globalPipelineId = DB::table($prefix.'pipelines')
                ->whereNull('team_id')
                ->where('model', $modelClass)
                ->value('id');

            $teamPipelineId = DB::table($prefix.'pipelines')
                ->where('team_id', $teamId)
                ->where('model', $modelClass)
                ->value('id');

            if ($globalPipelineId === null || $teamPipelineId === null) {
                continue;
            }

            $globalStages = DB::table($prefix.'pipeline_stages')
                ->where('pipeline_id', $globalPipelineId)
                ->whereNull('team_id')
                ->pluck('id', 'name')
                ->all();

            $teamStages = DB::table($prefix.'pipeline_stages')
                ->where('pipeline_id', $teamPipelineId)
                ->where('team_id', $teamId)
                ->pluck('id', 'name')
                ->all();

            foreach ($globalStages as $stageName => $globalStageId) {
                if (! isset($teamStages[$stageName])) {
                    continue;
                }

                $teamStageId = $teamStages[$stageName];

                DB::table($recordTable)
                    ->where('team_id', $teamId)
                    ->where('pipeline_stage_id', $globalStageId)
                    ->update(['pipeline_stage_id' => $teamStageId]);
            }
        }
    }

    /**
     * Handle the team "updating" event.
     *
     * @return void
     */
    public function updating(Team $team)
    {
        //
    }

    /**
     * Handle the team "updated" event.
     *
     * @return void
     */
    public function updated(Team $team)
    {
        //
    }

    /**
     * Handle the team "deleting" event.
     *
     * @return void
     */
    public function deleting(Team $team)
    {
        //
    }

    /**
     * Handle the team "deleted" event.
     *
     * @return void
     */
    public function deleted(Team $team)
    {
        //
    }

    /**
     * Handle the team "restored" event.
     *
     * @return void
     */
    public function restored(Team $team)
    {
        //
    }

    /**
     * Handle the team "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Team $team)
    {
        //
    }
}
