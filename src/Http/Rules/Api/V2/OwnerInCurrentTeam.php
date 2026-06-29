<?php

namespace VentureDrake\LaravelCrm\Http\Rules\Api\V2;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject `user_owner_id` values that point at a user outside the caller's
 * current team. When teams are disabled this rule is a no-op. When teams
 * are enabled but the caller has no current team, the rule fails closed
 * to prevent cross-tenant owner assignment.
 */
class OwnerInCurrentTeam implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $userClass = config('auth.providers.users.model');

        if (! $userClass || ! class_exists($userClass)) {
            return;
        }

        $owner = $userClass::find($value);

        if (! $owner) {
            // 'exists' rule covers the missing-user case; nothing to add here.
            return;
        }

        // Reject host-app users who don't have CRM access — without this,
        // an authenticated API caller could assign records to any user in
        // the host application's users table (e.g. an admin account, or a
        // user with no CRM access at all).
        if (! ($owner->crm_access ?? false)) {
            $fail('The selected owner does not have CRM access.');

            return;
        }

        if (! config('laravel-crm.teams')) {
            return;
        }

        $caller = auth()->user();

        if (! $caller) {
            return;
        }

        $currentTeamId = $caller->currentTeam->id ?? null;

        if (! $currentTeamId) {
            $fail('A current team must be set to assign an owner.');

            return;
        }

        if (! method_exists($owner, 'allTeams')) {
            return;
        }

        $ownerTeamIds = collect($owner->allTeams())
            ->map(fn ($team) => $team->id ?? null)
            ->filter()
            ->all();

        if (! in_array($currentTeamId, $ownerTeamIds, false)) {
            $fail('The selected owner is not a member of your current team.');
        }
    }
}
