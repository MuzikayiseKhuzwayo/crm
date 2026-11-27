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
use VentureDrake\LaravelCrm\Models\LinkedinOutreachMessage;
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
                'limits' => $activeCampaigns->isEmpty() ? null : [
                    'daily_connect_limit' => (int) $activeCampaigns->max('daily_connect_limit'),
                    'weekly_connect_limit' => (int) $activeCampaigns->max('weekly_connect_limit'),
                    'daily_dm_limit' => (int) $activeCampaigns->max('daily_dm_limit'),
                    'min_delay_seconds' => (int) $activeCampaigns->min('min_delay_seconds'),
                ],
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

        if (array_key_exists('completed_step', $validated) && $validated['completed_step'] !== null) {
            $result['completed_step'] = (int) $validated['completed_step'];
        }

        if (array_key_exists('prior_outbound_count', $validated) && $validated['prior_outbound_count'] !== null) {
            $result['prior_outbound_count'] = (int) $validated['prior_outbound_count'];
        }

        if (array_key_exists('last_sender', $validated) && $validated['last_sender'] !== null) {
            $result['last_sender'] = $validated['last_sender'];
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
                $validated['error_message'] ?? null,
                $validated['metadata'] ?? []
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
            $validated['replies'] ?? [],
            $validated['outbound'] ?? []
        );

        return response()->json([
            'data' => [
                'accepted' => $counts['accepted'],
                'replied' => $counts['replied'],
                'outbound_synced' => $counts['outbound_synced'],
                'updated_recipients' => $counts['accepted'] + $counts['replied'] + $counts['outbound_synced'],
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

    public function updateLimits(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);
        $validated = $request->validate([
            'daily_connect_limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'weekly_connect_limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'daily_dm_limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'min_delay_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'campaign_id' => ['nullable', 'string'],
        ]);

        $updates = array_intersect_key($validated, array_flip([
            'daily_connect_limit',
            'weekly_connect_limit',
            'daily_dm_limit',
            'min_delay_seconds',
        ]));

        $query = $this->teamCampaignsQuery($teamId);
        if (! empty($validated['campaign_id'])) {
            $query->where('external_id', $validated['campaign_id']);
        } else {
            $query->whereIn('status', [
                LinkedinCampaign::STATUS_ACTIVE,
                LinkedinCampaign::STATUS_PAUSED,
            ]);
        }

        $campaigns = $query->get();
        if (! empty($updates)) {
            foreach ($campaigns as $campaign) {
                $campaign->update($updates);
            }
        }

        $now = now();
        $activeCampaigns = $this->teamCampaignsQuery($teamId)
            ->where('status', LinkedinCampaign::STATUS_ACTIVE)
            ->get();

        $remainingToday = ['connect' => 0, 'dm' => 0];
        foreach ($activeCampaigns as $campaign) {
            $connectEval = $this->safetyGovernor->evaluate($campaign, 'connect', $now);
            $dmEval = $this->safetyGovernor->evaluate($campaign, 'dm', $now);
            $remainingToday['connect'] = max($remainingToday['connect'], $connectEval['remaining_today']['connect']);
            $remainingToday['dm'] = max($remainingToday['dm'], $dmEval['remaining_today']['dm']);
        }

        $source = $activeCampaigns->isNotEmpty() ? $activeCampaigns : $campaigns;

        return response()->json([
            'data' => [
                'updated_count' => $campaigns->count(),
                'limits' => [
                    'daily_connect_limit' => (int) ($source->max('daily_connect_limit') ?? ($updates['daily_connect_limit'] ?? 0)),
                    'weekly_connect_limit' => (int) ($source->max('weekly_connect_limit') ?? ($updates['weekly_connect_limit'] ?? 0)),
                    'daily_dm_limit' => (int) ($source->max('daily_dm_limit') ?? ($updates['daily_dm_limit'] ?? 0)),
                    'min_delay_seconds' => (int) ($source->min('min_delay_seconds') ?? ($updates['min_delay_seconds'] ?? 0)),
                ],
                'remaining_today' => $remainingToday,
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

        $reason = null;
        $earliestNextEligible = null;

        foreach ($activeCampaigns as $campaign) {
            foreach (['connect', 'dm'] as $action) {
                $eval = $this->safetyGovernor->evaluate($campaign, $action, $now);

                if (! $eval['allowed'] && $eval['reason'] !== null && $reason === null) {
                    $reason = $eval['reason'];
                }

                $candidate = $eval['next_eligible_at'];
                if ($candidate instanceof Carbon && ($earliestNextEligible === null || $candidate->lt($earliestNextEligible))) {
                    $earliestNextEligible = $candidate;
                }
            }
        }

        return [
            'reason' => $reason ?? 'no_due_tasks',
            'next_eligible_at' => $earliestNextEligible?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRecipientSummary(LinkedinCampaignRecipient $recipient): array
    {
        $recipient->loadMissing('lead.pipelineStage');

        $outboundCount = LinkedinOutreachMessage::withoutGlobalScope(BelongsToTeamsScope::class)
            ->where('linkedin_campaign_recipient_id', $recipient->id)
            ->where('direction', LinkedinOutreachMessage::DIRECTION_OUTBOUND)
            ->count();

        return [
            'id' => $recipient->external_id,
            'status' => $recipient->status,
            'current_step' => (int) $recipient->current_step,
            'lead_stage' => $recipient->lead?->pipelineStage?->name,
            'outbound_messages_count' => $outboundCount,
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
