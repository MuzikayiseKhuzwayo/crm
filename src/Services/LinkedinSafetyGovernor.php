<?php

namespace VentureDrake\LaravelCrm\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;

class LinkedinSafetyGovernor
{
    /**
     * Evaluate whether a campaign (and its team-wide LinkedIn account) is allowed
     * to execute the given outreach action ('connect' or 'dm') right now.
     *
     * @return array{
     *     allowed: bool,
     *     reason: ?string,
     *     remaining_today: array{connect: int, dm: int},
     *     next_eligible_at: ?Carbon
     * }
     */
    public function evaluate(LinkedinCampaign $campaign, string $action, ?CarbonInterface $now = null): array
    {
        $normalizedAction = $this->normalizeAction($action);
        $timezone = $campaign->timezone ?: 'Africa/Johannesburg';
        $nowUtc = $now ? Carbon::instance($now)->copy()->utc() : Carbon::now('UTC');
        $nowLocal = $nowUtc->copy()->setTimezone($timezone);

        $startOfDayUtc = $nowLocal->copy()->startOfDay()->utc();
        $rollingWeekStartUtc = $nowUtc->copy()->subDays(7);

        $connectsToday = $this->teamEventsQuery($campaign->team_id)
            ->where('action', LinkedinOutreachEvent::ACTION_CONNECT_SENT)
            ->where('occurred_at', '>=', $startOfDayUtc)
            ->count();

        $connectsThisWeek = $this->teamEventsQuery($campaign->team_id)
            ->where('action', LinkedinOutreachEvent::ACTION_CONNECT_SENT)
            ->where('occurred_at', '>=', $rollingWeekStartUtc)
            ->count();

        $dmsToday = $this->teamEventsQuery($campaign->team_id)
            ->where('action', LinkedinOutreachEvent::ACTION_DM_SENT)
            ->where('occurred_at', '>=', $startOfDayUtc)
            ->count();

        $dailyConnectLimit = (int) $campaign->daily_connect_limit;
        $weeklyConnectLimit = (int) $campaign->weekly_connect_limit;
        $dailyDmLimit = (int) $campaign->daily_dm_limit;

        $remainingToday = [
            'connect' => max(0, min($dailyConnectLimit - $connectsToday, $weeklyConnectLimit - $connectsThisWeek)),
            'dm' => max(0, $dailyDmLimit - $dmsToday),
        ];

        if ($campaign->status !== LinkedinCampaign::STATUS_ACTIVE) {
            return $this->result(false, 'campaign_'.$campaign->status, $remainingToday, null);
        }

        $activeDays = $this->normalizeActiveDays($campaign->active_days);
        $startHour = (int) $campaign->active_start_hour;
        $endHour = (int) $campaign->active_end_hour;

        if (! $this->isWithinWorkingWindow($nowLocal, $activeDays, $startHour, $endHour)) {
            $nextWindow = $this->nextWorkingWindow($nowLocal, $activeDays, $startHour, $endHour);

            return $this->result(false, 'outside_working_hours', $remainingToday, $nextWindow);
        }

        if ($normalizedAction === 'connect') {
            if ($connectsToday >= $dailyConnectLimit) {
                $nextWindow = $this->nextWorkingWindow(
                    $nowLocal->copy()->addDay()->startOfDay(),
                    $activeDays,
                    $startHour,
                    $endHour
                );

                return $this->result(false, 'daily_connect_limit_reached', $remainingToday, $nextWindow);
            }

            if ($connectsThisWeek >= $weeklyConnectLimit) {
                $oldestConnect = $this->teamEventsQuery($campaign->team_id)
                    ->where('action', LinkedinOutreachEvent::ACTION_CONNECT_SENT)
                    ->where('occurred_at', '>=', $rollingWeekStartUtc)
                    ->orderBy('occurred_at', 'asc')
                    ->first();

                $nextEligible = $oldestConnect
                    ? $this->alignToWorkingWindow(
                        $oldestConnect->occurred_at->copy()->addDays(7)->setTimezone($timezone),
                        $activeDays,
                        $startHour,
                        $endHour
                    )
                    : $this->nextWorkingWindow($nowLocal->copy()->addDay()->startOfDay(), $activeDays, $startHour, $endHour);

                return $this->result(false, 'weekly_connect_limit_reached', $remainingToday, $nextEligible);
            }
        } elseif ($normalizedAction === 'dm') {
            if ($dmsToday >= $dailyDmLimit) {
                $nextWindow = $this->nextWorkingWindow(
                    $nowLocal->copy()->addDay()->startOfDay(),
                    $activeDays,
                    $startHour,
                    $endHour
                );

                return $this->result(false, 'daily_dm_limit_reached', $remainingToday, $nextWindow);
            }
        } else {
            return $this->result(false, 'unsupported_action', $remainingToday, null);
        }

        $minDelaySeconds = (int) $campaign->min_delay_seconds;
        if ($minDelaySeconds > 0) {
            $lastSendEvent = $this->teamEventsQuery($campaign->team_id)
                ->whereIn('action', [
                    LinkedinOutreachEvent::ACTION_CONNECT_SENT,
                    LinkedinOutreachEvent::ACTION_DM_SENT,
                ])
                ->orderBy('occurred_at', 'desc')
                ->first();

            if ($lastSendEvent && $lastSendEvent->occurred_at) {
                $cooldownEndsAt = Carbon::instance($lastSendEvent->occurred_at)->copy()->utc()->addSeconds($minDelaySeconds);

                if ($cooldownEndsAt->isAfter($nowUtc)) {
                    $nextEligible = $this->alignToWorkingWindow(
                        $cooldownEndsAt->copy()->setTimezone($timezone),
                        $activeDays,
                        $startHour,
                        $endHour
                    );

                    return $this->result(false, 'cooldown_active', $remainingToday, $nextEligible);
                }
            }
        }

        return $this->result(true, null, $remainingToday, null);
    }

    public function canExecute(LinkedinCampaign $campaign, string $action, ?CarbonInterface $now = null): bool
    {
        return $this->evaluate($campaign, $action, $now)['allowed'];
    }

    /**
     * @return Builder<LinkedinOutreachEvent>
     */
    protected function teamEventsQuery(?int $teamId): Builder
    {
        $query = LinkedinOutreachEvent::withoutGlobalScope(BelongsToTeamsScope::class);

        return $teamId === null
            ? $query->whereNull('team_id')
            : $query->where('team_id', $teamId);
    }

    protected function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));

        return match ($action) {
            'connect', 'connect_note', 'connection_request' => 'connect',
            'dm', 'follow_up', 'message' => 'dm',
            default => $action,
        };
    }

    /**
     * @param  array<int, int|string>|null  $activeDays
     * @return array<int, int>
     */
    protected function normalizeActiveDays(?array $activeDays): array
    {
        if (empty($activeDays)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $activeDays)));
    }

    /**
     * @param  array<int, int>  $activeDays
     */
    protected function isWithinWorkingWindow(Carbon $localTime, array $activeDays, int $startHour, int $endHour): bool
    {
        if (empty($activeDays) || $startHour >= $endHour) {
            return false;
        }

        if (! in_array($localTime->dayOfWeekIso, $activeDays, true)) {
            return false;
        }

        return $localTime->hour >= $startHour && $localTime->hour < $endHour;
    }

    /**
     * @param  array<int, int>  $activeDays
     */
    protected function alignToWorkingWindow(Carbon $localTime, array $activeDays, int $startHour, int $endHour): ?Carbon
    {
        if ($this->isWithinWorkingWindow($localTime, $activeDays, $startHour, $endHour)) {
            return $localTime->copy()->utc();
        }

        return $this->nextWorkingWindow($localTime, $activeDays, $startHour, $endHour);
    }

    /**
     * Find the next start-of-window Carbon (in UTC) on or after $fromLocal.
     *
     * @param  array<int, int>  $activeDays
     */
    protected function nextWorkingWindow(Carbon $fromLocal, array $activeDays, int $startHour, int $endHour): ?Carbon
    {
        if (empty($activeDays) || $startHour >= $endHour) {
            return null;
        }

        $candidate = $fromLocal->copy();

        for ($offset = 0; $offset < 8; $offset++) {
            $day = $candidate->copy()->addDays($offset);

            if (! in_array($day->dayOfWeekIso, $activeDays, true)) {
                continue;
            }

            $windowStart = $day->copy()->setTime($startHour, 0, 0);

            if ($offset === 0 && $candidate->gte($windowStart)) {
                continue;
            }

            return $windowStart->utc();
        }

        return null;
    }

    /**
     * @param  array{connect: int, dm: int}  $remainingToday
     * @return array{
     *     allowed: bool,
     *     reason: ?string,
     *     remaining_today: array{connect: int, dm: int},
     *     next_eligible_at: ?Carbon
     * }
     */
    protected function result(bool $allowed, ?string $reason, array $remainingToday, ?Carbon $nextEligibleAt): array
    {
        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'remaining_today' => $remainingToday,
            'next_eligible_at' => $nextEligibleAt,
        ];
    }
}
