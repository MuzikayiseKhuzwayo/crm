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
     * legitimate ownership transfer. Use assignableBy() for anything driven by
     * user input; this scope is the team/crm_role filter only.
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

    /**
     * CRM roles $user is entitled to hand out.
     *
     * assignable() keeps Owner in the set so ownership transfer stays possible;
     * this scope removes it for everyone except an existing Owner. Every site
     * that turns user input into a role assignment must go through here -- the
     * role dropdowns, the AssignableRole validation rule and UserInvite -- so
     * the options offered and the values accepted can never diverge.
     *
     * A null caller (console, queue, unauthenticated) is treated as not an
     * Owner, so the escalation-sensitive role is excluded by default.
     *
     * @param  mixed  $user  defaults to the authenticated caller
     */
    public function scopeAssignableBy($query, $user = null)
    {
        $user = func_num_args() > 1 ? $user : auth()->user();

        return $query->assignable()->unless(
            (bool) $user?->hasRole('Owner'),
            fn ($query) => $query->where('name', '<>', 'Owner')
        );
    }
}
