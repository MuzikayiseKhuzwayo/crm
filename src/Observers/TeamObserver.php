<?php

namespace VentureDrake\LaravelCrm\Observers;

use App\Team;
use Carbon\Carbon;
use DB;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\PermissionRegistrar;
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
     * Pipelines + pipeline stages are copied via `updateOrInsert` keyed on
     * `team_id + model` (pipelines) and `team_id + pipeline_id + name`
     * (stages) so the block is safe to re-run against a team that already
     * has per-team pipelines — no duplicates, no exceptions.
     * `PipelineStageProbability` rows are shared globally and are NOT
     * copied per team.
     */
    public static function seedCrmDataForTeam(int $teamId): void
    {
        foreach (DB::table(config('laravel-crm.db_table_prefix').'labels')
            ->whereNull('team_id')
            ->get() as $label) {
            DB::table(config('laravel-crm.db_table_prefix').'labels')->insert([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => $label->name,
                'hex' => $label->hex,
                'description' => $label->description,
                'team_id' => $teamId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach (DB::table(config('laravel-crm.db_table_prefix').'organization_types')
            ->whereNull('team_id')
            ->get() as $organizationType) {
            DB::table(config('laravel-crm.db_table_prefix').'organization_types')->insert([
                'name' => $organizationType->name,
                'description' => $organizationType->description,
                'team_id' => $teamId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach (DB::table(config('laravel-crm.db_table_prefix').'address_types')
            ->whereNull('team_id')
            ->get() as $addressType) {
            DB::table(config('laravel-crm.db_table_prefix').'address_types')->insert([
                'name' => $addressType->name,
                'description' => $addressType->description,
                'team_id' => $teamId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach (DB::table(config('laravel-crm.db_table_prefix').'contact_types')
            ->whereNull('team_id')
            ->get() as $contactType) {
            DB::table(config('laravel-crm.db_table_prefix').'contact_types')->insert([
                'name' => $contactType->name,
                'description' => $contactType->description,
                'team_id' => $teamId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach (DB::table(config('laravel-crm.db_table_prefix').'industries')
            ->whereNull('team_id')
            ->get() as $industry) {
            DB::table(config('laravel-crm.db_table_prefix').'industries')->insert([
                'name' => $industry->name,
                'description' => $industry->description,
                'team_id' => $teamId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach (DB::table(config('laravel-crm.db_table_prefix').'tax_rates')
            ->whereNull('team_id')
            ->get() as $taxRate) {
            DB::table(config('laravel-crm.db_table_prefix').'tax_rates')->insert([
                'name' => $taxRate->name,
                'description' => $taxRate->description,
                'rate' => $taxRate->rate,
                'default' => $taxRate->default,
                'team_id' => $teamId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // Pipelines + pipeline stages. `updateOrInsert` keeps this block
        // idempotent so re-running against a team that already has per-team
        // pipelines produces no duplicates. `PipelineStageProbability` rows
        // are shared globally and are NOT copied per team.
        foreach (DB::table(config('laravel-crm.db_table_prefix').'pipelines')
            ->whereNull('team_id')
            ->get() as $pipeline) {
            DB::table(config('laravel-crm.db_table_prefix').'pipelines')->updateOrInsert([
                'team_id' => $teamId,
                'model' => $pipeline->model,
            ], [
                'external_id' => Uuid::uuid4()->toString(),
                'name' => $pipeline->name,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $teamPipelineId = DB::table(config('laravel-crm.db_table_prefix').'pipelines')
                ->where('team_id', $teamId)
                ->where('model', $pipeline->model)
                ->value('id');

            if ($teamPipelineId === null) {
                continue;
            }

            foreach (DB::table(config('laravel-crm.db_table_prefix').'pipeline_stages')
                ->where('pipeline_id', $pipeline->id)
                ->whereNull('team_id')
                ->get() as $stage) {
                DB::table(config('laravel-crm.db_table_prefix').'pipeline_stages')->updateOrInsert([
                    'team_id' => $teamId,
                    'pipeline_id' => $teamPipelineId,
                    'name' => $stage->name,
                ], [
                    'external_id' => Uuid::uuid4()->toString(),
                    'description' => $stage->description ?? null,
                    'pipeline_stage_probability_id' => $stage->pipeline_stage_probability_id ?? null,
                    'order' => $stage->order ?? 0,
                    'color' => $stage->color ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
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
