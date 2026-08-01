<?php

namespace VentureDrake\LaravelCrm\Support;

/**
 * Answers "is this host-app user a member of the caller's current team?".
 *
 * The membership probe is deliberately portable: `crmTeams()` is usually empty
 * (it is the CRM's own optional grouping, not the tenancy boundary) and
 * `team_user` is a Jetstream table that non-Jetstream hosts do not ship. The
 * only reliable signal is Jetstream's `allTeams()` accessor, so we duck-type
 * for it exactly the way the API's OwnerInCurrentTeam validation rule does.
 *
 * Semantics:
 *  - teams disabled                -> true  (there is no tenancy boundary)
 *  - teams enabled, no current team -> false (fail closed)
 *  - host user has no allTeams()    -> true  (unknowable; do not block)
 *  - otherwise                      -> membership of the current team
 */
class TeamMembership
{
    /**
     * @param  mixed  $user  the host-app user being checked
     */
    public static function inCurrentTeam($user): bool
    {
        if (! config('laravel-crm.teams')) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $currentTeamId = auth()->user()->currentTeam->id ?? null;

        if (! $currentTeamId) {
            return false;
        }

        if (! method_exists($user, 'allTeams')) {
            return true;
        }

        $teamIds = collect($user->allTeams())
            ->map(fn ($team) => $team->id ?? null)
            ->filter()
            ->all();

        return in_array($currentTeamId, $teamIds, false);
    }
}
