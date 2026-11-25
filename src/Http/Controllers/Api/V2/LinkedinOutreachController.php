<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\ClaimLinkedinTaskRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\CompleteLinkedinTaskRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\FailLinkedinTaskRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\ResetLinkedinCircuitBreakerRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\SyncLinkedinInboxRequest;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;
use VentureDrake\LaravelCrm\Services\LinkedinOutreachService;
use VentureDrake\LaravelCrm\Services\LinkedinSafetyGovernor;

class LinkedinOutreachController extends ApiController
{
    public function __construct(
        private LinkedinOutreachService $outreachService,
        private LinkedinSafetyGovernor $safetyGovernor
    ) {}

    public function status(Request $request): JsonResponse
    {
        $now = now();
        $teamId = $this->resolveTeamId($request);
        $workerId = $request->query('worker_id');
        $heartbeatAt = null;

        if (is_string($workerId) && trim($workerId) !== '') {
            $workerId = trim($workerId);
            $heartbeatAt = $now;

            $this->teamCampaignsQuery($teamId)
                ->where('status', LinkedinCampaign::STATUS_ACTIVE)
                ->update(['last_worker_heartbeat_at' => $now]);
        } else {
            $workerId = null;
        }

        $activeCampaigns = $this->teamCampaignsQuery($teamId)
            ->where('status', LinkedinCampaign::STATUS_ACTIVE)
            ->orderBy('id')
            ->get();

        $circuitBrokenCampaign = $this->teamCampaignsQuery($teamId)
            ->where('status', LinkedinCampaign::STATUS_CIRCUIT_BROKEN)
            ->orderByDesc('circuit_broken_at')
            ->first();

        $dueTasksCount = $activeCampaigns->isEmpty()
            ? 0
            : $this->teamRecipientsQuery($teamId)
                ->whereIn('linkedin_campaign_id', $activeCampaigns->pluck('id'))
                ->dueForAction($now)
                ->count();

        $remainingToday = ['connect' => 0, 'dm' => 0];
        $anyAllowed = false;
        $earliestNextEligible = null;

        foreach ($activeCampaigns as $campaign) {
            $connectEval = $this->safetyGovernor->evaluate($campaign, 'connect', $now);
            $dmEval = $this->safetyGovernor->evaluate($campaign, 'dm', $now);

            $remainingToday['connect'] = max($remainingToday['connect'], $connectEval['remaining_today']['connect']);
            $remainingToday['dm'] = max($remainingToday['dm'], $dmEval['remaining_today']['dm']);

            if ($connectEval['allowed'] || $dmEval['allowed']) {
                $anyAllowed = true;
            }

            foreach ([$connectEval['next_eligible_at'], $dmEval['next_eligible_at']] as $candidate) {
                if ($candidate instanceof Carbon && ($earliestNextEligible === null || $candidate->lt($earliestNextEligible))) {
                    $earliestNextEligible = $candidate;
                }
            }
        }

        return response()->json([
            'data' => [
                'worker_id' => $workerId,
                'heartbeat_at' => $heartbeatAt?->toIso8601String(),
                'active_campaigns_count' => $activeCampaigns->count(),
                'active_campaigns' => $activeCampaigns->map(fn (LinkedinCampaign $campaign): array => [
                    'id' => $campaign->external_id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'total_recipients' => (int) $campaign->total_recipients,
                    'connects_sent_count' => (int) $campaign->connects_sent_count,
                    'accepted_count' => (int) $campaign->accepted_count,
                    'dms_sent_count' => (int) $campaign->dms_sent_count,
                    'replied_count' => (int) $campaign->replied_count,
                    'failed_count' => (int) $campaign->failed_count,
                    'last_worker_heartbeat_at' => $campaign->last_worker_heartbeat_at?->toIso8601String(),
                ])->values()->all(),
                'due_tasks_count' => $dueTasksCount,
                'remaining_today' => $remainingToday,
                'next_eligible_at' => $anyAllowed ? null : $earliestNextEligible?->toIso8601String(),
                'circuit_broken' => $circuitBrokenCampaign !== null,
                'circuit_broken_reason' => $circuitBrokenCampaign?->circuit_broken_reason,
            ],
        ]);
    }

    public function claim(ClaimLinkedinTaskRequest $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);
        $validated = $request->validated();

        $task = $this->outreachService->claimNextTask(
            $teamId,
            $validated['worker_id'],
            (int) ($validated['lease_seconds'] ?? 300)
        );

        if ($task !== null) {
            return response()->json(['data' => $task]);
        }

        return response()->json([
            'data' => null,
            'meta' => $this->resolveIneligibleMeta($teamId),
        ]);
    }

    public function complete(CompleteLinkedinTaskRequest $request, LinkedinCampaignRecipient $recipient): JsonResponse
    {
        $this->ensureRecipientInTeam($request, $recipient);

        $validated = $request->validated();
        $result = $validated['metadata'] ?? [];

        if (array_key_exists('sent_text', $validated) && $validated['sent_text'] !== null) {
            $result['sent_text'] = $validated['sent_text'];
            $result['message_body'] = $validated['sent_text'];
        }

        try {
            $this->outreachService->completeTask($recipient, $validated['lease_token'], $result);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $recipient->refresh();

        return response()->json([
            'data' => $this->formatRecipientSummary($recipient),
        ]);
    }

    public function fail(FailLinkedinTaskRequest $request, LinkedinCampaignRecipient $recipient): JsonResponse
    {
        $this->ensureRecipientInTeam($request, $recipient);

        $validated = $request->validated();

        try {
            $this->outreachService->failTask(
                $recipient,
                $validated['lease_token'],
                $validated['error_code'],
                $validated['error_message'] ?? null
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $recipient->refresh();
        $campaign = LinkedinCampaign::withoutGlobalScope(BelongsToTeamsScope::class)
            ->find($recipient->linkedin_campaign_id);

        return response()->json([
            'data' => array_merge($this->formatRecipientSummary($recipient), [
                'attempt_count' => (int) $recipient->attempt_count,
                'last_error_code' => $recipient->last_error_code,
                'last_error_message' => $recipient->last_error_message,
                'circuit_broken' => $campaign?->isCircuitBroken() ?? false,
                'circuit_broken_reason' => $campaign?->circuit_broken_reason,
            ]),
        ]);
    }

    public function syncInbox(SyncLinkedinInboxRequest $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);
        $validated = $request->validated();

        $counts = $this->outreachService->syncInboxEvents(
            $teamId,
            $validated['accepted_slugs'] ?? [],
            $validated['replies'] ?? []
        );

        return response()->json([
            'data' => [
                'accepted' => $counts['accepted'],
                'replied' => $counts['replied'],
                'updated_recipients' => $counts['accepted'] + $counts['replied'],
            ],
        ]);
    }

    public function resetCircuitBreaker(ResetLinkedinCircuitBreakerRequest $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);
        $validated = $request->validated();
        $resume = (bool) ($validated['resume'] ?? false);
        $targetStatus = $resume ? LinkedinCampaign::STATUS_ACTIVE : LinkedinCampaign::STATUS_PAUSED;

        $query = $this->teamCampaignsQuery($teamId)
            ->where('status', LinkedinCampaign::STATUS_CIRCUIT_BROKEN);

        if (! empty($validated['campaign_id'])) {
            $query->where('external_id', $validated['campaign_id']);
        }

        $campaigns = $query->get();

        foreach ($campaigns as $campaign) {
            $campaign->update([
                'status' => $targetStatus,
                'circuit_broken_at' => null,
                'circuit_broken_reason' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'reset_count' => $campaigns->count(),
                'status' => $targetStatus,
                'campaigns' => $campaigns->map(fn (LinkedinCampaign $campaign): array => [
                    'id' => $campaign->external_id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                ])->values()->all(),
            ],
        ]);
    }

    protected function resolveTeamId(Request $request): ?int
    {
        if (! config('laravel-crm.teams')) {
            return null;
        }

        $teamId = $request->user()?->currentTeam?->id;

        return $teamId !== null ? (int) $teamId : null;
    }

    protected function ensureRecipientInTeam(Request $request, LinkedinCampaignRecipient $recipient): void
    {
        if (! config('laravel-crm.teams')) {
            return;
        }

        $teamId = $this->resolveTeamId($request);

        if ((int) $recipient->team_id !== (int) $teamId) {
            abort(404);
        }
    }

    /**
     * @return array{reason: string, next_eligible_at: ?string}
     */
    protected function resolveIneligibleMeta(?int $teamId): array
    {
        $now = now();

        $activeCampaigns = $this->teamCampaignsQuery($teamId)
            ->where('status', LinkedinCampaign::STATUS_ACTIVE)
            ->get();

        if ($activeCampaigns->isEmpty()) {
            $hasCircuitBroken = $this->teamCampaignsQuery($teamId)
                ->where('status', LinkedinCampaign::STATUS_CIRCUIT_BROKEN)
                ->exists();

            return [
                'reason' => $hasCircuitBroken ? 'circuit_broken' : 'no_active_campaigns',
                'next_eligible_at' => null,
            ];
        }

        $hasDueRecipients = $this->teamRecipientsQuery($teamId)
            ->whereIn('linkedin_campaign_id', $activeCampaigns->pluck('id'))
            ->dueForAction($now)
            ->exists();

        if (! $hasDueRecipients) {
            $nextActionAt = $this->teamRecipientsQuery($teamId)
                ->whereIn('linkedin_campaign_id', $activeCampaigns->pluck('id'))
                ->whereIn('status', LinkedinCampaignRecipient::ACTIONABLE_STATUSES)
                ->whereNotNull('next_action_at')
                ->where('next_action_at', '>', $now)
                ->min('next_action_at');

            return [
                'reason' => 'no_due_tasks',
                'next_eligible_at' => $nextActionAt ? Carbon::parse($nextActionAt)->utc()->toIso8601String() : null,
            ];
        }

        $reason = 'safety_governor_blocked';
        $earliestNextEligible = null;

        foreach ($activeCampaigns as $campaign) {
            foreach (['connect', 'dm'] as $action) {
                $eval = $this->safetyGovernor->evaluate($campaign, $action, $now);

                if (! $eval['allowed'] && $eval['reason'] !== null && $reason === 'safety_governor_blocked') {
                    $reason = $eval['reason'];
                }

                $candidate = $eval['next_eligible_at'];
                if ($candidate instanceof Carbon && ($earliestNextEligible === null || $candidate->lt($earliestNextEligible))) {
                    $earliestNextEligible = $candidate;
                }
            }
        }

        return [
            'reason' => $reason,
            'next_eligible_at' => $earliestNextEligible?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRecipientSummary(LinkedinCampaignRecipient $recipient): array
    {
        return [
            'id' => $recipient->external_id,
            'status' => $recipient->status,
            'current_step' => (int) $recipient->current_step,
            'connect_sent_at' => $recipient->connect_sent_at?->toIso8601String(),
            'connected_at' => $recipient->connected_at?->toIso8601String(),
            'last_dm_sent_at' => $recipient->last_dm_sent_at?->toIso8601String(),
            'next_action_at' => $recipient->next_action_at?->toIso8601String(),
            'completed_at' => $recipient->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return Builder<LinkedinCampaign>
     */
    protected function teamCampaignsQuery(?int $teamId): Builder
    {
        $query = LinkedinCampaign::withoutGlobalScope(BelongsToTeamsScope::class);

        return $teamId === null
            ? $query->whereNull('team_id')
            : $query->where('team_id', $teamId);
    }

    /**
     * @return Builder<LinkedinCampaignRecipient>
     */
    protected function teamRecipientsQuery(?int $teamId): Builder
    {
        $query = LinkedinCampaignRecipient::withoutGlobalScope(BelongsToTeamsScope::class);

        return $teamId === null
            ? $query->whereNull('team_id')
            : $query->where('team_id', $teamId);
    }
}
