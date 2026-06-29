<?php

namespace VentureDrake\LaravelCrm\Http\Rules\Api\V2;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Builds an `exists` rule that respects the caller's current team when
 * multi-tenant teams mode is enabled. The bare Laravel `exists` rule
 * bypasses the package's `BelongsToTeamsScope` because it queries the DB
 * directly, which would otherwise let API callers reference UUIDs that
 * belong to other tenants.
 */
class ScopedExists
{
    public static function for(string $table, string $column = 'external_id'): Exists
    {
        $rule = Rule::exists($table, $column);

        if (! config('laravel-crm.teams')) {
            return $rule;
        }

        // Tables without a team_id column are package-wide globals (e.g.
        // labels, lead_sources) and cannot be team-scoped — fall back to a
        // bare exists check rather than producing a SQL error at validation.
        if (! Schema::hasColumn($table, 'team_id')) {
            return $rule;
        }

        $teamId = auth()->user()->currentTeam->id ?? null;

        if ($teamId === null) {
            // Force a guaranteed miss; OwnerInCurrentTeam-style rules surface
            // the underlying "no current team" failure on their own attributes.
            return $rule->where(fn ($q) => $q->whereRaw('1 = 0'));
        }

        return $rule->where('team_id', $teamId);
    }
}
