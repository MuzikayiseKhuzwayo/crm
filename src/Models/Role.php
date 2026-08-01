<?php

namespace VentureDrake\LaravelCrm\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function scopeCrm($query)
    {
        return $query->where('crm_role', 1);
    }

    public function scopeCrmNotOwner($query)
    {
        return $query->where('crm_role', 1)->where('name', '<>', 'Owner');
    }

    /**
     * CRM roles the current caller is allowed to hand out.
     *
     * The `whereNull('team_id')` branch is load-bearing: the seeded
     * Owner/Admin/Manager/Employee roles are written with `team_id => null`
     * even when teams are enabled (see LaravelCrmTablesSeeder), so a plain
     * `where('team_id', $currentTeamId)` match would return an empty set and
     * silently skip role assignment on every create/edit.
     *
     * Deliberately NOT `crmNotOwner()` -- excluding Owner here would break
     * legitimate ownership transfer. Callers guard the Owner escalation
     * separately, at the point of assignment.
     */
    public function scopeAssignable($query)
    {
        return $query->crm()->when(config('laravel-crm.teams'), function ($query) {
            $currentTeamId = auth()->user()?->currentTeam?->id;

            $query->where(function ($query) use ($currentTeamId) {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $currentTeamId);
            });
        });
    }
}
