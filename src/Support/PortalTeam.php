<?php

namespace VentureDrake\LaravelCrm\Support;

use VentureDrake\LaravelCrm\Models\Feature;

/**
 * Works out which team's public portal the current request is looking at.
 *
 * On a `laravel-crm.teams` install every team has its own public board. The
 * portal is anonymous by definition — the people reading a roadmap are the
 * team's customers, not its staff — so the team cannot come from
 * `auth()->user()->currentTeam` alone, and requiring an operator to nominate
 * one team in `LARAVEL_CRM_PORTAL_TEAM_ID` gave the other teams no portal at
 * all.
 *
 * Resolution order:
 *
 *  1. `laravel-crm.portal.team_id`, when set. Anyone who configured it wanted
 *     a single-tenant portal, so it stays a hard lock and every other signal
 *     is ignored. It is now entirely optional.
 *  2. The team named in the URL (`/p/features/team/{id}`), which is what makes
 *     a board shareable with people who have no account.
 *  3. The team remembered in the session from (2) or from a feature page the
 *     visitor already opened, so "back to the board", voting and submitting
 *     stay on the board they arrived at.
 *  4. The signed-in user's current team — the natural default for staff.
 *  5. The only team that has a public board, when there is exactly one. This
 *     is what makes the common "teams enabled, one team" install work with no
 *     configuration and no team in the URL.
 *
 * Returns null only when teams are enabled and none of the above answered, at
 * which point the caller should 404 rather than guess.
 */
class PortalTeam
{
    public const SESSION_KEY = 'laravel-crm.portal_team_id';

    /**
     * Is the portal team-scoped at all? False on a single-tenant install,
     * where every caller should skip team filtering entirely.
     */
    public static function scoped(): bool
    {
        return (bool) config('laravel-crm.teams');
    }

    /**
     * The configured single-tenant lock, or null when unset.
     */
    public static function locked(): ?int
    {
        $configured = config('laravel-crm.portal.team_id');

        return ($configured !== null && $configured !== '') ? (int) $configured : null;
    }

    /**
     * Resolve the team whose board this request should show.
     *
     * @param  int|null  $fromUrl  the team id taken from the route, when the
     *                             request addressed one explicitly
     */
    public static function resolve(?int $fromUrl = null): ?int
    {
        if (! static::scoped()) {
            return null;
        }

        if ($locked = static::locked()) {
            return $locked;
        }

        if ($fromUrl !== null) {
            static::remember($fromUrl);

            return $fromUrl;
        }

        if ($remembered = static::remembered()) {
            return $remembered;
        }

        if (($user = auth()->user()) && ($team = $user->currentTeam ?? null)) {
            return (int) $team->id;
        }

        return static::soleBoardTeamId();
    }

    /**
     * Take the team from a feature the visitor has navigated to, and remember
     * it so the rest of the portal follows them onto that board.
     *
     * A public feature is public: it is reachable by its own link whichever
     * team owns it. The lock, when set, still wins — an install that pinned
     * the portal to one team keeps 404ing everything else.
     */
    public static function adopt(?int $teamId): ?int
    {
        if (! static::scoped()) {
            return null;
        }

        if ($locked = static::locked()) {
            return $locked;
        }

        if ($teamId !== null) {
            static::remember($teamId);
        }

        return $teamId;
    }

    /**
     * Store the visitor's current board for subsequent requests.
     */
    public static function remember(int $teamId): void
    {
        session()->put(static::SESSION_KEY, $teamId);
    }

    /**
     * The board remembered from an earlier request in this session.
     */
    public static function remembered(): ?int
    {
        $teamId = session(static::SESSION_KEY);

        return $teamId === null ? null : (int) $teamId;
    }

    /**
     * The only team with a public board, or null when there are none or more
     * than one.
     *
     * Scopes are dropped deliberately: this runs for anonymous visitors, where
     * BelongsToTeamsScope is inert anyway, and for signed-in staff, where it
     * would otherwise narrow the count to their own team and make every
     * install look single-team.
     */
    protected static function soleBoardTeamId(): ?int
    {
        $teamIds = Feature::query()
            ->withoutGlobalScopes()
            ->public()
            ->whereNotNull('team_id')
            ->distinct()
            ->limit(2)
            ->pluck('team_id');

        return $teamIds->count() === 1 ? (int) $teamIds->first() : null;
    }
}
