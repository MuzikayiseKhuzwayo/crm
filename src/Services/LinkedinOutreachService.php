<?php

namespace VentureDrake\LaravelCrm\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachMessage;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;

class LinkedinOutreachService
{
    public const MAX_TRANSIENT_ATTEMPTS = 3;

    public const STOP_OUTREACH_STAGE_KEYWORDS = [
        'replied',
        'meeting booked',
        'call scheduled',
        'proposal',
        'qualified',
        'engaged',
        'won',
        'lost',
        'do not contact',
        'unsubscribed',
        'disqualified',
    ];

    public const SKIP_ERROR_CODES = [
        'email_required',
        'profile_unavailable',
        'cannot_message',
    ];

    public const CIRCUIT_BREAKER_ERROR_CODES = [
        'rate_limited',
        'captcha_detected',
        'auth_expired',
    ];

    protected LinkedinSafetyGovernor $safetyGovernor;

    protected LinkedinMessageRenderer $messageRenderer;

    public function __construct(
        ?LinkedinSafetyGovernor $safetyGovernor = null,
        ?LinkedinMessageRenderer $messageRenderer = null
    ) {
        $this->safetyGovernor = $safetyGovernor ?? new LinkedinSafetyGovernor;
        $this->messageRenderer = $messageRenderer ?? new LinkedinMessageRenderer;
    }

    /**
     * Enroll leads into a LinkedIn campaign, skipping leads without a valid personal /in/ URL
     * and deduplicating against any active, completed, or replied recipient on the same team.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array{
     *     enrolled: int,
     *     skipped_no_url: int,
     *     skipped_duplicate: int,
     *     recipients: array<int, LinkedinCampaignRecipient>
     * }
     */
    public function enrollLeads(LinkedinCampaign $campaign, Collection $leads): array
    {
        $blockingStatuses = array_merge(
            LinkedinCampaignRecipient::ACTIVE_STATUSES,
            [
                LinkedinCampaignRecipient::STATUS_COMPLETED,
                LinkedinCampaignRecipient::STATUS_REPLIED,
            ]
        );

        $existingSlugs = $this->teamRecipientsQuery($campaign->team_id)
            ->whereIn('status', $blockingStatuses)
            ->whereNotNull('normalized_linkedin_slug')
            ->pluck('normalized_linkedin_slug')
            ->flip()
            ->all();

        $seenSlugs = [];
        $enrolled = 0;
        $skippedNoUrl = 0;
        $skippedDuplicate = 0;
        $createdRecipients = [];
        $now = now();

        foreach ($leads as $lead) {
            if (! $lead instanceof Lead) {
                continue;
            }

            $lead->loadMissing('person');
            $linkedinUrl = $lead->linkedin_url;

            if (! $linkedinUrl) {
                $skippedNoUrl++;

                continue;
            }

            $slug = LinkedinCampaignRecipient::normalizeLinkedinUrl($linkedinUrl);

            if (! $slug) {
                $skippedNoUrl++;

                continue;
            }

            if (isset($existingSlugs[$slug]) || isset($seenSlugs[$slug])) {
                $skippedDuplicate++;

                continue;
            }

            $recipient = LinkedinCampaignRecipient::create([
                'team_id' => $campaign->team_id,
                'linkedin_campaign_id' => $campaign->id,
                'lead_id' => $lead->id,
                'person_id' => $lead->person_id,
                'linkedin_url' => $linkedinUrl,
                'normalized_linkedin_slug' => $slug,
                'status' => LinkedinCampaignRecipient::STATUS_QUEUED,
                'current_step' => 0,
                'next_action_at' => $now,
            ]);

            $seenSlugs[$slug] = true;
            $createdRecipients[] = $recipient;
            $enrolled++;
        }

        if ($enrolled > 0) {
            $campaign->increment('total_recipients', $enrolled);
        }

        return [
            'enrolled' => $enrolled,
            'skipped_no_url' => $skippedNoUrl,
            'skipped_duplicate' => $skippedDuplicate,
            'recipients' => $createdRecipients,
        ];
    }

    /**
     * Atomically reclaim expired leases and claim the highest-priority due outreach task
     * permitted by the safety governor for the given team.
     *
     * @return array{
     *     recipient_external_id: string,
     *     lease_token: string,
     *     action: string,
     *     step: int,
     *     linkedin_url: string,
     *     message_body: string,
     *     person_name: string,
     *     company_name: string
     * }|null
     */
    public function claimNextTask(?int $teamId, string $workerId, int $leaseSeconds = 300): ?array
    {
        return DB::transaction(function () use ($teamId, $workerId, $leaseSeconds): ?array {
            $now = now();

            $this->reclaimExpiredLeases($teamId, $now);

            $activeCampaigns = $this->teamCampaignsQuery($teamId)
                ->where('status', LinkedinCampaign::STATUS_ACTIVE)
                ->get()
                ->keyBy('id');

            if ($activeCampaigns->isEmpty()) {
                return null;
            }

            $candidatesQuery = $this->teamRecipientsQuery($teamId)
                ->whereIn('linkedin_campaign_id', $activeCampaigns->keys())
                ->dueForAction($now)
                ->orderByRaw("CASE status WHEN 'connected' THEN 1 WHEN 'in_sequence' THEN 2 WHEN 'queued' THEN 3 ELSE 4 END")
                ->orderByRaw('CASE WHEN next_action_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('next_action_at', 'asc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit(50);

            if (DB::connection()->getDriverName() === 'sqlite') {
                $candidatesQuery->lockForUpdate();
            } else {
                $candidatesQuery->lock('for update skip locked');
            }

            $candidates = $candidatesQuery->get();

            $safetyCache = [];

            foreach ($candidates as $recipient) {
                /** @var LinkedinCampaign|null $campaign */
                $campaign = $activeCampaigns->get($recipient->linkedin_campaign_id);

                if (! $campaign) {
                    continue;
                }

                $lead = $this->resolveLead($recipient);

                if ($lead && $this->isLeadInStopOutreachStage($lead)) {
                    continue;
                }

                $nextStep = $this->getNextStep($campaign, (int) $recipient->current_step);

                if ($nextStep === null) {
                    $recipient->update([
                        'status' => LinkedinCampaignRecipient::STATUS_COMPLETED,
                        'completed_at' => $now,
                        'next_action_at' => null,
                    ]);

                    continue;
                }

                $action = (string) ($nextStep['action'] ?? 'connect');
                $cacheKey = $campaign->id.':'.$action;

                if (! array_key_exists($cacheKey, $safetyCache)) {
                    $safetyCache[$cacheKey] = $this->safetyGovernor->evaluate($campaign, $action, $now);
                }

                if (! $safetyCache[$cacheKey]['allowed']) {
                    continue;
                }

                $leaseToken = (string) Str::uuid();

                $recipient->update([
                    'status' => LinkedinCampaignRecipient::STATUS_LEASED,
                    'lease_token' => $leaseToken,
                    'leased_at' => $now,
                    'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                    'worker_id' => $workerId,
                ]);

                $campaign->last_worker_heartbeat_at = $now;
                $campaign->saveQuietly();

                $taskStep = (int) ($nextStep['step'] ?? ($recipient->current_step + 1));
                $messageBody = $this->renderStepMessage($nextStep, $recipient);
                [$personName, $companyName] = $this->resolveRecipientNames($recipient, $lead);
                $stepMessages = $this->buildStepMessages($campaign, $recipient, $action === 'connect' ? 0 : $taskStep);
                $outboundCount = $this->countOutboundMessages($recipient);

                return [
                    'recipient_external_id' => $recipient->external_id,
                    'lease_token' => $leaseToken,
                    'action' => $action,
                    'step' => $taskStep,
                    'linkedin_url' => $recipient->linkedin_url,
                    'message_body' => $messageBody,
                    'person_name' => $personName,
                    'company_name' => $companyName,
                    'lead_stage' => $lead?->pipelineStage?->name,
                    'outbound_messages_count' => $outboundCount,
                    'step_messages' => $stepMessages,
                ];
            }

            return null;
        });
    }

    /**
     * Mark a leased task as completed, record the outreach event, update counters,
     * schedule the next step (or wait for acceptance / complete), and log CRM Note + Activity.
     *
     * @param  array<string, mixed>  $result
     */
    public function completeTask(LinkedinCampaignRecipient $recipient, string $leaseToken, array $result = []): void
    {
        $this->assertValidLease($recipient, $leaseToken);

        DB::transaction(function () use ($recipient, $result): void {
            $now = now();
            $campaign = $this->resolveCampaign($recipient);
            $executedStep = $this->getNextStep($campaign, (int) $recipient->current_step);

            $taskStep = (int) ($result['step'] ?? $executedStep['step'] ?? ($recipient->current_step + 1));
            $action = (string) ($result['action'] ?? $executedStep['action'] ?? 'connect');
            $isConnect = $action === 'connect';

            if ($isConnect) {
                $stepNumber = isset($result['completed_step']) ? (int) $result['completed_step'] : $taskStep;
            } else {
                $completedStep = isset($result['completed_step']) ? (int) $result['completed_step'] : $taskStep;
                $fromPriorCount = isset($result['prior_outbound_count'])
                    ? $this->resolveStepForDmCount($campaign, ((int) $result['prior_outbound_count']) + 1)
                    : $taskStep;
                $stepNumber = max($completedStep, $fromPriorCount);
                $maxStep = $this->getMaxStep($campaign);
                if ($maxStep > 0) {
                    $stepNumber = min($stepNumber, $maxStep);
                }
            }

            $eventAction = $isConnect
                ? LinkedinOutreachEvent::ACTION_CONNECT_SENT
                : LinkedinOutreachEvent::ACTION_DM_SENT;

            $messagePreview = trim((string) ($result['sent_text'] ?? $result['message_body'] ?? $result['message'] ?? ''));

            if ($messagePreview !== '') {
                $this->recordMessageIfUnseen(
                    $recipient,
                    $campaign,
                    LinkedinOutreachMessage::DIRECTION_OUTBOUND,
                    LinkedinOutreachMessage::SOURCE_WORKER,
                    $messagePreview,
                    $isConnect ? 0 : $stepNumber,
                    $now
                );
            }

            LinkedinOutreachEvent::create([
                'team_id' => $recipient->team_id ?? $campaign->team_id,
                'linkedin_campaign_id' => $campaign->id,
                'linkedin_campaign_recipient_id' => $recipient->id,
                'action' => $eventAction,
                'step' => $stepNumber,
                'payload' => $result,
                'occurred_at' => $now,
            ]);

            if ($isConnect) {
                $campaign->increment('connects_sent_count');
            } else {
                $campaign->increment('dms_sent_count');
            }

            $nextStep = $this->getNextStep($campaign, $stepNumber);

            $updates = [
                'current_step' => $stepNumber,
                'lease_token' => null,
                'leased_at' => null,
                'lease_expires_at' => null,
                'worker_id' => null,
                'attempt_count' => 0,
                'last_error_code' => null,
                'last_error_message' => null,
            ];

            if ($isConnect) {
                $updates['status'] = LinkedinCampaignRecipient::STATUS_CONNECT_SENT;
                $updates['connect_sent_at'] = $now;
                $updates['next_action_at'] = null;
            } else {
                $updates['connected_at'] = $recipient->connected_at ?? $now;
                $updates['last_dm_sent_at'] = $now;

                if ($nextStep !== null) {
                    $delayHours = (int) ($nextStep['delay_hours'] ?? 24);
                    $jitterSeconds = $this->computeJitterSeconds($campaign);

                    $updates['status'] = LinkedinCampaignRecipient::STATUS_IN_SEQUENCE;
                    $updates['next_action_at'] = $now->copy()->addHours($delayHours)->addSeconds($jitterSeconds);
                } else {
                    $updates['status'] = LinkedinCampaignRecipient::STATUS_COMPLETED;
                    $updates['completed_at'] = $now;
                    $updates['next_action_at'] = null;
                }
            }

            $recipient->update($updates);

            if ($isConnect) {
                $this->syncLeadStageForOutreachEvent($recipient, 'connect_sent');
            } else {
                $dmNumber = $this->resolveDmNumberForStep($campaign, $stepNumber);
                $this->syncLeadStageForOutreachEvent(
                    $recipient,
                    $nextStep === null ? 'sequence_complete' : 'dm_sent',
                    $dmNumber
                );
            }

            $actionLabel = $isConnect ? 'LinkedIn connection request sent' : 'LinkedIn DM sent';
            $noteContent = "[{$campaign->name}] {$actionLabel} (Step {$stepNumber})";

            if ($messagePreview !== '') {
                $noteContent .= "\n\n{$messagePreview}";
            }

            $this->logCrmNoteAndActivity($recipient, $campaign, $noteContent, $now);
        });
    }

    /**
     * Handle a failed task execution according to its structured error code.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function failTask(
        LinkedinCampaignRecipient $recipient,
        string $leaseToken,
        string $errorCode,
        ?string $errorMessage = null,
        array $metadata = []
    ): void {
        $this->assertValidLease($recipient, $leaseToken);

        $code = strtolower(trim($errorCode));

        DB::transaction(function () use ($recipient, $code, $errorMessage, $metadata): void {
            $now = now();
            $campaign = $this->resolveCampaign($recipient);

            $clearLease = [
                'lease_token' => null,
                'leased_at' => null,
                'lease_expires_at' => null,
                'worker_id' => null,
            ];

            if ($code === 'step_already_sent') {
                $pendingStep = $this->getNextStep($campaign, (int) $recipient->current_step);
                $taskStep = (int) ($pendingStep['step'] ?? ((int) $recipient->current_step + 1));
                $priorCount = isset($metadata['prior_outbound_count']) && is_numeric($metadata['prior_outbound_count'])
                    ? (int) $metadata['prior_outbound_count']
                    : null;
                $stepFromPrior = ($priorCount !== null && $priorCount > 0)
                    ? $this->resolveStepForDmCount($campaign, $priorCount)
                    : $taskStep;

                $reconciledStep = max((int) $recipient->current_step, $taskStep, $stepFromPrior);
                $maxStep = $this->getMaxStep($campaign);
                if ($maxStep > 0) {
                    $reconciledStep = min($reconciledStep, $maxStep);
                }

                $lastOutboundSnippet = trim((string) ($metadata['last_outbound_snippet'] ?? ''));
                if ($lastOutboundSnippet !== '') {
                    $this->recordMessageIfUnseen(
                        $recipient,
                        $campaign,
                        LinkedinOutreachMessage::DIRECTION_OUTBOUND,
                        LinkedinOutreachMessage::SOURCE_THREAD_RECONCILE,
                        $lastOutboundSnippet,
                        $reconciledStep,
                        $now
                    );
                }

                $lastDmSentAt = $recipient->last_dm_sent_at ?? $now;
                $nextStep = $this->getNextStep($campaign, $reconciledStep);

                $updates = array_merge($clearLease, [
                    'connected_at' => $recipient->connected_at ?? $now,
                    'current_step' => $reconciledStep,
                    'last_dm_sent_at' => $lastDmSentAt,
                    'attempt_count' => 0,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);

                if ($nextStep !== null) {
                    $delayHours = (int) ($nextStep['delay_hours'] ?? 24);
                    $jitterSeconds = $this->computeJitterSeconds($campaign);

                    $updates['status'] = LinkedinCampaignRecipient::STATUS_IN_SEQUENCE;
                    $updates['next_action_at'] = $lastDmSentAt->copy()->addHours($delayHours)->addSeconds($jitterSeconds);
                } else {
                    $updates['status'] = LinkedinCampaignRecipient::STATUS_COMPLETED;
                    $updates['completed_at'] = $now;
                    $updates['next_action_at'] = null;
                }

                $recipient->update($updates);

                LinkedinOutreachEvent::create([
                    'team_id' => $recipient->team_id ?? $campaign->team_id,
                    'linkedin_campaign_id' => $campaign->id,
                    'linkedin_campaign_recipient_id' => $recipient->id,
                    'action' => LinkedinOutreachEvent::ACTION_STEP_RECONCILED,
                    'step' => $reconciledStep,
                    'payload' => array_merge($metadata, [
                        'error_code' => $code,
                        'message' => $errorMessage,
                    ]),
                    'occurred_at' => $now,
                ]);

                $dmNumber = $this->resolveDmNumberForStep($campaign, $reconciledStep);
                $this->syncLeadStageForOutreachEvent(
                    $recipient,
                    $nextStep === null ? 'sequence_complete' : 'dm_sent',
                    $dmNumber
                );

                return;
            }

            if ($code === 'reply_detected') {
                $snippet = trim((string) ($metadata['reply_snippet'] ?? $metadata['snippet'] ?? $errorMessage ?? ''));

                if ($snippet !== '') {
                    $this->recordMessageIfUnseen(
                        $recipient,
                        $campaign,
                        LinkedinOutreachMessage::DIRECTION_INBOUND,
                        LinkedinOutreachMessage::SOURCE_THREAD_RECONCILE,
                        $snippet,
                        (int) $recipient->current_step,
                        $now
                    );
                }

                $recipient->update(array_merge($clearLease, [
                    'status' => LinkedinCampaignRecipient::STATUS_REPLIED,
                    'replied_at' => $now,
                    'next_action_at' => null,
                    'attempt_count' => 0,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]));

                $campaign->increment('replied_count');

                LinkedinOutreachEvent::create([
                    'team_id' => $recipient->team_id ?? $campaign->team_id,
                    'linkedin_campaign_id' => $campaign->id,
                    'linkedin_campaign_recipient_id' => $recipient->id,
                    'action' => LinkedinOutreachEvent::ACTION_REPLY_RECEIVED,
                    'step' => (int) $recipient->current_step,
                    'payload' => array_merge($metadata, [
                        'error_code' => $code,
                        'snippet' => $snippet,
                    ]),
                    'occurred_at' => $now,
                ]);

                $lead = $this->resolveLead($recipient);
                if ($lead) {
                    $lead->update(['relay_status' => 'engaged']);
                }

                $this->syncLeadStageForOutreachEvent($recipient, 'replied');

                return;
            }

            if ($code === 'already_connected') {
                $firstDmStep = $this->getFirstDmStep($campaign);

                LinkedinOutreachEvent::create([
                    'team_id' => $recipient->team_id ?? $campaign->team_id,
                    'linkedin_campaign_id' => $campaign->id,
                    'linkedin_campaign_recipient_id' => $recipient->id,
                    'action' => LinkedinOutreachEvent::ACTION_ACCEPTED,
                    'step' => (int) $recipient->current_step + 1,
                    'payload' => array_merge($metadata, ['error_code' => $code, 'message' => $errorMessage]),
                    'occurred_at' => $now,
                ]);

                $campaign->increment('accepted_count');

                if ($firstDmStep !== null) {
                    $delayHours = (int) ($firstDmStep['delay_hours'] ?? 0);
                    $nextActionAt = $delayHours > 0 ? $now->copy()->addHours($delayHours) : $now;

                    $recipient->update(array_merge($clearLease, [
                        'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
                        'connected_at' => $recipient->connected_at ?? $now,
                        'current_step' => max(0, ((int) $firstDmStep['step']) - 1),
                        'next_action_at' => $nextActionAt,
                        'attempt_count' => 0,
                        'last_error_code' => null,
                        'last_error_message' => null,
                    ]));
                } else {
                    $recipient->update(array_merge($clearLease, [
                        'status' => LinkedinCampaignRecipient::STATUS_COMPLETED,
                        'connected_at' => $recipient->connected_at ?? $now,
                        'completed_at' => $now,
                        'next_action_at' => null,
                        'attempt_count' => 0,
                        'last_error_code' => null,
                        'last_error_message' => null,
                    ]));
                }

                $this->syncLeadStageForOutreachEvent($recipient, 'connected');

                return;
            }

            if ($code === 'pending_invite') {
                $recipient->update(array_merge($clearLease, [
                    'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
                    'connect_sent_at' => $recipient->connect_sent_at ?? $now,
                    'current_step' => max((int) $recipient->current_step, 1),
                    'next_action_at' => null,
                    'last_error_code' => $code,
                    'last_error_message' => $errorMessage,
                ]));

                $this->syncLeadStageForOutreachEvent($recipient, 'connect_sent');

                return;
            }

            if (in_array($code, self::SKIP_ERROR_CODES, true)) {
                $recipient->update(array_merge($clearLease, [
                    'status' => LinkedinCampaignRecipient::STATUS_SKIPPED,
                    'next_action_at' => null,
                    'last_error_code' => $code,
                    'last_error_message' => $errorMessage,
                ]));

                LinkedinOutreachEvent::create([
                    'team_id' => $recipient->team_id ?? $campaign->team_id,
                    'linkedin_campaign_id' => $campaign->id,
                    'linkedin_campaign_recipient_id' => $recipient->id,
                    'action' => LinkedinOutreachEvent::ACTION_SKIPPED,
                    'step' => (int) $recipient->current_step + 1,
                    'payload' => ['error_code' => $code, 'message' => $errorMessage],
                    'occurred_at' => $now,
                ]);

                return;
            }

            if (in_array($code, self::CIRCUIT_BREAKER_ERROR_CODES, true)) {
                $recipient->update(array_merge($clearLease, [
                    'status' => LinkedinCampaignRecipient::STATUS_QUEUED,
                    'last_error_code' => $code,
                    'last_error_message' => $errorMessage,
                ]));

                $activeCampaigns = $this->teamCampaignsQuery($campaign->team_id)
                    ->where('status', LinkedinCampaign::STATUS_ACTIVE)
                    ->get();

                if (! $activeCampaigns->contains('id', $campaign->id)) {
                    $activeCampaigns->push($campaign);
                }

                foreach ($activeCampaigns as $activeCampaign) {
                    $activeCampaign->update([
                        'status' => LinkedinCampaign::STATUS_CIRCUIT_BROKEN,
                        'circuit_broken_at' => $now,
                        'circuit_broken_reason' => $code,
                    ]);

                    LinkedinOutreachEvent::create([
                        'team_id' => $activeCampaign->team_id,
                        'linkedin_campaign_id' => $activeCampaign->id,
                        'linkedin_campaign_recipient_id' => $activeCampaign->id === $campaign->id ? $recipient->id : null,
                        'action' => LinkedinOutreachEvent::ACTION_CIRCUIT_BREAKER_TRIPPED,
                        'step' => $activeCampaign->id === $campaign->id ? ((int) $recipient->current_step + 1) : null,
                        'payload' => ['reason' => $code, 'message' => $errorMessage],
                        'occurred_at' => $now,
                    ]);
                }

                return;
            }

            $attempts = (int) $recipient->attempt_count + 1;

            if ($attempts < self::MAX_TRANSIENT_ATTEMPTS) {
                $backoffMinutes = 5 * (2 ** ($attempts - 1));

                $recipient->update(array_merge($clearLease, [
                    'status' => $this->resolveReclaimStatus($recipient),
                    'attempt_count' => $attempts,
                    'next_action_at' => $now->copy()->addMinutes($backoffMinutes),
                    'last_error_code' => $code,
                    'last_error_message' => $errorMessage,
                ]));
            } else {
                $recipient->update(array_merge($clearLease, [
                    'status' => LinkedinCampaignRecipient::STATUS_FAILED,
                    'attempt_count' => $attempts,
                    'next_action_at' => null,
                    'last_error_code' => $code,
                    'last_error_message' => $errorMessage,
                ]));

                $campaign->increment('failed_count');

                LinkedinOutreachEvent::create([
                    'team_id' => $recipient->team_id ?? $campaign->team_id,
                    'linkedin_campaign_id' => $campaign->id,
                    'linkedin_campaign_recipient_id' => $recipient->id,
                    'action' => LinkedinOutreachEvent::ACTION_FAILED,
                    'step' => (int) $recipient->current_step + 1,
                    'payload' => ['error_code' => $code, 'message' => $errorMessage, 'attempts' => $attempts],
                    'occurred_at' => $now,
                ]);
            }
        });
    }

    /**
     * Synchronize accepted connection invitations, inbound replies, and manual outbound DMs
     * from LinkedIn inbox polling.
     *
     * @param  array<int, string>  $acceptedSlugs
     * @param  array<int, array{slug?: string, snippet?: string, replied_at?: string|null}>  $replies
     * @param  array<int, array{slug?: string, snippet?: string, sent_at?: string|null}>  $outbound
     * @return array{accepted: int, replied: int, outbound_synced: int}
     */
    public function syncInboxEvents(?int $teamId, array $acceptedSlugs, array $replies, array $outbound = []): array
    {
        return DB::transaction(function () use ($teamId, $acceptedSlugs, $replies, $outbound): array {
            $now = now();
            $acceptedCount = 0;
            $repliedCount = 0;
            $outboundSyncedCount = 0;

            $normalizedAccepted = array_values(array_unique(array_filter(array_map(
                fn ($slug) => $this->normalizeSlug((string) $slug),
                $acceptedSlugs
            ))));

            if (! empty($normalizedAccepted)) {
                $connectSentRecipients = $this->teamRecipientsQuery($teamId)
                    ->where('status', LinkedinCampaignRecipient::STATUS_CONNECT_SENT)
                    ->whereIn('normalized_linkedin_slug', $normalizedAccepted)
                    ->get();

                foreach ($connectSentRecipients as $recipient) {
                    $campaign = $this->resolveCampaign($recipient);
                    $nextStep = $this->getNextStep($campaign, (int) $recipient->current_step);

                    if ($nextStep !== null) {
                        $delayHours = (int) ($nextStep['delay_hours'] ?? 0);
                        $jitterSeconds = $this->computeJitterSeconds($campaign);

                        $recipient->update([
                            'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
                            'connected_at' => $now,
                            'next_action_at' => $now->copy()->addHours($delayHours)->addSeconds($jitterSeconds),
                        ]);
                    } else {
                        $recipient->update([
                            'status' => LinkedinCampaignRecipient::STATUS_COMPLETED,
                            'connected_at' => $now,
                            'completed_at' => $now,
                            'next_action_at' => null,
                        ]);
                    }

                    $campaign->increment('accepted_count');

                    LinkedinOutreachEvent::create([
                        'team_id' => $recipient->team_id ?? $campaign->team_id,
                        'linkedin_campaign_id' => $campaign->id,
                        'linkedin_campaign_recipient_id' => $recipient->id,
                        'action' => LinkedinOutreachEvent::ACTION_ACCEPTED,
                        'step' => (int) $recipient->current_step,
                        'payload' => ['slug' => $recipient->normalized_linkedin_slug],
                        'occurred_at' => $now,
                    ]);

                    $this->syncLeadStageForOutreachEvent($recipient, 'connected');

                    $acceptedCount++;
                }
            }

            foreach ($replies as $reply) {
                $slug = $this->normalizeSlug((string) ($reply['slug'] ?? ''));

                if ($slug === null) {
                    continue;
                }

                $snippet = trim((string) ($reply['snippet'] ?? ''));
                $repliedAt = ! empty($reply['replied_at']) ? Carbon::parse($reply['replied_at']) : $now;

                $matchingRecipients = $this->teamRecipientsQuery($teamId)
                    ->where('normalized_linkedin_slug', $slug)
                    ->whereNotIn('status', [
                        LinkedinCampaignRecipient::STATUS_REPLIED,
                        LinkedinCampaignRecipient::STATUS_SKIPPED,
                        LinkedinCampaignRecipient::STATUS_FAILED,
                    ])
                    ->get();

                foreach ($matchingRecipients as $recipient) {
                    $campaign = $this->resolveCampaign($recipient);

                    if ($snippet !== '') {
                        $this->recordMessageIfUnseen(
                            $recipient,
                            $campaign,
                            LinkedinOutreachMessage::DIRECTION_INBOUND,
                            LinkedinOutreachMessage::SOURCE_INBOX_SYNC,
                            $snippet,
                            (int) $recipient->current_step,
                            $repliedAt
                        );
                    }

                    $recipient->update([
                        'status' => LinkedinCampaignRecipient::STATUS_REPLIED,
                        'replied_at' => $repliedAt,
                        'next_action_at' => null,
                        'lease_token' => null,
                        'leased_at' => null,
                        'lease_expires_at' => null,
                        'worker_id' => null,
                    ]);

                    $campaign->increment('replied_count');

                    LinkedinOutreachEvent::create([
                        'team_id' => $recipient->team_id ?? $campaign->team_id,
                        'linkedin_campaign_id' => $campaign->id,
                        'linkedin_campaign_recipient_id' => $recipient->id,
                        'action' => LinkedinOutreachEvent::ACTION_REPLY_RECEIVED,
                        'step' => (int) $recipient->current_step,
                        'payload' => [
                            'slug' => $slug,
                            'snippet' => $snippet,
                            'replied_at' => $repliedAt->toIso8601String(),
                        ],
                        'occurred_at' => $repliedAt,
                    ]);

                    $noteContent = "[{$campaign->name}] LinkedIn reply received";
                    if ($snippet !== '') {
                        $noteContent .= ":\n\n\"{$snippet}\"";
                    }

                    $this->logCrmNoteAndActivity($recipient, $campaign, $noteContent, $repliedAt);

                    $lead = $this->resolveLead($recipient);
                    if ($lead) {
                        $lead->update([
                            'relay_status' => 'engaged',
                        ]);
                    }

                    $this->syncLeadStageForOutreachEvent($recipient, 'replied');

                    $repliedCount++;
                }
            }

            foreach ($outbound as $item) {
                $slug = $this->normalizeSlug((string) ($item['slug'] ?? ''));
                $rawSnippet = (string) ($item['snippet'] ?? '');
                $cleanSnippet = LinkedinOutreachMessage::stripSenderPrefix($rawSnippet);

                if ($slug === null || $cleanSnippet === '') {
                    continue;
                }

                $sentAt = ! empty($item['sent_at']) ? Carbon::parse($item['sent_at']) : $now;

                $matchingRecipients = $this->teamRecipientsQuery($teamId)
                    ->where('normalized_linkedin_slug', $slug)
                    ->whereNotIn('status', [
                        LinkedinCampaignRecipient::STATUS_REPLIED,
                        LinkedinCampaignRecipient::STATUS_COMPLETED,
                        LinkedinCampaignRecipient::STATUS_SKIPPED,
                        LinkedinCampaignRecipient::STATUS_FAILED,
                    ])
                    ->get();

                foreach ($matchingRecipients as $recipient) {
                    $campaign = $this->resolveCampaign($recipient);
                    $nextDmStep = $this->getNextDmStep($campaign, (int) $recipient->current_step);
                    $maxStep = $this->getMaxStep($campaign);
                    $newStep = $nextDmStep !== null
                        ? (int) $nextDmStep['step']
                        : max((int) $recipient->current_step, $maxStep);

                    $recorded = $this->recordMessageIfUnseen(
                        $recipient,
                        $campaign,
                        LinkedinOutreachMessage::DIRECTION_OUTBOUND,
                        LinkedinOutreachMessage::SOURCE_INBOX_SYNC,
                        $cleanSnippet,
                        $newStep,
                        $sentAt
                    );

                    if (! $recorded) {
                        continue;
                    }

                    $wasUnconnected = $recipient->connected_at === null;
                    $lastDmSentAt = $recipient->last_dm_sent_at && $recipient->last_dm_sent_at->gt($sentAt)
                        ? $recipient->last_dm_sent_at
                        : $sentAt;

                    $afterStep = $this->getNextStep($campaign, $newStep);

                    $updates = [
                        'connected_at' => $recipient->connected_at ?? $sentAt,
                        'current_step' => $newStep,
                        'last_dm_sent_at' => $lastDmSentAt,
                    ];

                    if ($afterStep !== null) {
                        $delayHours = (int) ($afterStep['delay_hours'] ?? 24);
                        $jitterSeconds = $this->computeJitterSeconds($campaign);

                        $updates['status'] = LinkedinCampaignRecipient::STATUS_IN_SEQUENCE;
                        $updates['next_action_at'] = $lastDmSentAt->copy()->addHours($delayHours)->addSeconds($jitterSeconds);
                    } else {
                        $updates['status'] = LinkedinCampaignRecipient::STATUS_COMPLETED;
                        $updates['completed_at'] = $now;
                        $updates['next_action_at'] = null;
                    }

                    $recipient->update($updates);

                    if ($wasUnconnected) {
                        $campaign->increment('accepted_count');
                    }

                    $campaign->increment('dms_sent_count');

                    LinkedinOutreachEvent::create([
                        'team_id' => $recipient->team_id ?? $campaign->team_id,
                        'linkedin_campaign_id' => $campaign->id,
                        'linkedin_campaign_recipient_id' => $recipient->id,
                        'action' => LinkedinOutreachEvent::ACTION_DM_SENT,
                        'step' => $newStep,
                        'payload' => [
                            'slug' => $slug,
                            'snippet' => $cleanSnippet,
                            'sent_at' => $sentAt->toIso8601String(),
                            'source' => LinkedinOutreachMessage::SOURCE_INBOX_SYNC,
                        ],
                        'occurred_at' => $sentAt,
                    ]);

                    $noteContent = "[{$campaign->name}] LinkedIn DM synced from inbox (Step {$newStep})\n\n{$cleanSnippet}";
                    $this->logCrmNoteAndActivity($recipient, $campaign, $noteContent, $sentAt);

                    $dmNumber = $this->resolveDmNumberForStep($campaign, $newStep);
                    $this->syncLeadStageForOutreachEvent(
                        $recipient,
                        $afterStep === null ? 'sequence_complete' : 'dm_sent',
                        $dmNumber
                    );

                    $outboundSyncedCount++;
                }
            }

            return [
                'accepted' => $acceptedCount,
                'replied' => $repliedCount,
                'outbound_synced' => $outboundSyncedCount,
            ];
        });
    }

    protected function assertValidLease(LinkedinCampaignRecipient $recipient, string $leaseToken): void
    {
        if (
            $recipient->status !== LinkedinCampaignRecipient::STATUS_LEASED
            || $recipient->lease_token === null
            || ! hash_equals((string) $recipient->lease_token, $leaseToken)
        ) {
            throw new RuntimeException('Invalid or expired lease token.');
        }
    }

    protected function reclaimExpiredLeases(?int $teamId, Carbon $now): int
    {
        $expired = $this->teamRecipientsQuery($teamId)
            ->expiredLeases($now)
            ->lockForUpdate()
            ->get();

        foreach ($expired as $recipient) {
            $recipient->update([
                'status' => $this->resolveReclaimStatus($recipient),
                'lease_token' => null,
                'leased_at' => null,
                'lease_expires_at' => null,
                'worker_id' => null,
            ]);
        }

        return $expired->count();
    }

    protected function resolveReclaimStatus(LinkedinCampaignRecipient $recipient): string
    {
        if ($recipient->last_dm_sent_at !== null) {
            return LinkedinCampaignRecipient::STATUS_IN_SEQUENCE;
        }

        if ($recipient->connected_at !== null) {
            return LinkedinCampaignRecipient::STATUS_CONNECTED;
        }

        return LinkedinCampaignRecipient::STATUS_QUEUED;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getNextStep(LinkedinCampaign $campaign, int $currentStep): ?array
    {
        $steps = $this->sortedSteps($campaign);

        foreach ($steps as $step) {
            if ((int) ($step['step'] ?? 0) > $currentStep) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getDmSteps(LinkedinCampaign $campaign): array
    {
        $dmSteps = [];

        foreach ($this->sortedSteps($campaign) as $step) {
            $action = strtolower((string) ($step['action'] ?? ''));

            if (in_array($action, ['dm', 'follow_up', 'message'], true)) {
                $dmSteps[] = $step;
            }
        }

        return $dmSteps;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getNextDmStep(LinkedinCampaign $campaign, int $currentStep): ?array
    {
        foreach ($this->getDmSteps($campaign) as $step) {
            if ((int) ($step['step'] ?? 0) > $currentStep) {
                return $step;
            }
        }

        return null;
    }

    protected function getMaxStep(LinkedinCampaign $campaign): int
    {
        $steps = $this->sortedSteps($campaign);

        if (empty($steps)) {
            return 0;
        }

        $last = end($steps);

        return (int) ($last['step'] ?? 0);
    }

    protected function resolveStepForDmCount(LinkedinCampaign $campaign, int $dmCount): int
    {
        $dmSteps = $this->getDmSteps($campaign);

        if (empty($dmSteps)) {
            return max(1, $dmCount);
        }

        $index = max(0, min($dmCount - 1, count($dmSteps) - 1));

        return (int) ($dmSteps[$index]['step'] ?? $dmCount);
    }

    protected function resolveDmNumberForStep(LinkedinCampaign $campaign, int $stepNumber): int
    {
        $dmSteps = $this->getDmSteps($campaign);

        foreach ($dmSteps as $idx => $step) {
            if ((int) ($step['step'] ?? 0) === $stepNumber) {
                return $idx + 1;
            }
        }

        $count = 0;
        foreach ($dmSteps as $step) {
            if ((int) ($step['step'] ?? 0) <= $stepNumber) {
                $count++;
            }
        }

        return max(1, $count);
    }

    /**
     * Build rendered DM step templates for Co-Pilot variant selection.
     *
     * @return array<int, array{step: int, label: string, body: string}>
     */
    protected function buildStepMessages(LinkedinCampaign $campaign, LinkedinCampaignRecipient $recipient, int $minStep = 0): array
    {
        $messages = [];
        $dmIndex = 0;

        foreach ($this->getDmSteps($campaign) as $step) {
            $dmIndex++;
            $stepNumber = (int) ($step['step'] ?? $dmIndex);

            if ($minStep > 0 && $stepNumber < $minStep) {
                continue;
            }

            $body = $this->renderStepMessage($step, $recipient);
            if ($body === '') {
                continue;
            }

            $messages[] = [
                'step' => $stepNumber,
                'label' => trim((string) ($step['label'] ?? "DM {$dmIndex}")),
                'body' => $body,
            ];
        }

        return $messages;
    }

    protected function recordMessageIfUnseen(
        LinkedinCampaignRecipient $recipient,
        LinkedinCampaign $campaign,
        string $direction,
        string $source,
        string $bodySnippet,
        ?int $step,
        Carbon $occurredAt
    ): bool {
        $cleaned = LinkedinOutreachMessage::stripSenderPrefix($bodySnippet);
        if ($cleaned === '') {
            return false;
        }

        $contentHash = LinkedinOutreachMessage::computeContentHash($cleaned);

        $exists = LinkedinOutreachMessage::withoutGlobalScope(BelongsToTeamsScope::class)
            ->where('linkedin_campaign_recipient_id', $recipient->id)
            ->where('direction', $direction)
            ->where('content_hash', $contentHash)
            ->exists();

        if ($exists) {
            return false;
        }

        LinkedinOutreachMessage::create([
            'team_id' => $recipient->team_id ?? $campaign->team_id,
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_campaign_recipient_id' => $recipient->id,
            'direction' => $direction,
            'step' => $step,
            'source' => $source,
            'body_snippet' => mb_substr($cleaned, 0, 2000, 'UTF-8'),
            'content_hash' => $contentHash,
            'occurred_at' => $occurredAt,
        ]);

        return true;
    }

    protected function countOutboundMessages(LinkedinCampaignRecipient $recipient): int
    {
        return LinkedinOutreachMessage::withoutGlobalScope(BelongsToTeamsScope::class)
            ->where('linkedin_campaign_recipient_id', $recipient->id)
            ->where('direction', LinkedinOutreachMessage::DIRECTION_OUTBOUND)
            ->count();
    }

    protected function isLeadInStopOutreachStage(Lead $lead): bool
    {
        if ($lead->converted_at !== null) {
            return true;
        }

        $stageName = strtolower(trim((string) ($lead->pipelineStage?->name ?? '')));
        if ($stageName === '') {
            return false;
        }

        foreach (self::STOP_OUTREACH_STAGE_KEYWORDS as $keyword) {
            if (str_contains($stageName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function syncLeadStageForOutreachEvent(
        LinkedinCampaignRecipient $recipient,
        string $eventType,
        int $dmNumber = 0
    ): ?string {
        $lead = $this->resolveLead($recipient);

        if (! $lead) {
            return null;
        }

        $pipeline = $lead->pipeline_id
            ? Pipeline::withoutGlobalScope(BelongsToTeamsScope::class)->find($lead->pipeline_id)
            : Pipeline::withoutGlobalScope(BelongsToTeamsScope::class)->where('model', Lead::class)->first();

        if (! $pipeline) {
            return $lead->pipelineStage?->name;
        }

        $stages = PipelineStage::withoutGlobalScope(BelongsToTeamsScope::class)
            ->where('pipeline_id', $pipeline->id)
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($stages->isEmpty()) {
            return $lead->pipelineStage?->name;
        }

        $keywords = match ($eventType) {
            'connect_sent' => ['connect sent', 'connection request sent', 'connection request'],
            'connected' => ['connected', 'connected / dm sent'],
            'dm_sent' => array_filter([
                $dmNumber > 0 ? "dm {$dmNumber} sent" : null,
                'connected / dm sent',
                'dm sent',
            ]),
            'sequence_complete' => ['sequence complete', 'connected / dm sent', 'dm sent'],
            'replied' => ['replied', 'engaged / qualified', 'engaged', 'qualified'],
            default => [],
        };

        $targetStage = null;
        foreach ($keywords as $keyword) {
            $match = $stages->first(
                fn (PipelineStage $stage): bool => strtolower(trim((string) $stage->name)) === $keyword
            ) ?? $stages->first(
                fn (PipelineStage $stage): bool => str_contains(strtolower((string) $stage->name), $keyword)
            );

            if ($match) {
                $targetStage = $match;
                break;
            }
        }

        if (! $targetStage) {
            return $lead->pipelineStage?->name;
        }

        $currentRank = $stages->search(fn (PipelineStage $s): bool => (int) $s->id === (int) $lead->pipeline_stage_id);
        $targetRank = $stages->search(fn (PipelineStage $s): bool => (int) $s->id === (int) $targetStage->id);

        if ($currentRank === false || ($targetRank !== false && $targetRank > $currentRank)) {
            $lead->update([
                'pipeline_id' => $targetStage->pipeline_id,
                'pipeline_stage_id' => $targetStage->id,
                'pipeline_stage_order' => $targetStage->order,
            ]);

            return $targetStage->name;
        }

        return $lead->pipelineStage?->name;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getFirstDmStep(LinkedinCampaign $campaign): ?array
    {
        foreach ($this->sortedSteps($campaign) as $step) {
            $action = strtolower((string) ($step['action'] ?? ''));

            if (in_array($action, ['dm', 'follow_up', 'message'], true)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sortedSteps(LinkedinCampaign $campaign): array
    {
        $steps = is_array($campaign->sequence_steps) ? $campaign->sequence_steps : [];

        usort($steps, static fn (array $a, array $b): int => ((int) ($a['step'] ?? 0)) <=> ((int) ($b['step'] ?? 0)));

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function renderStepMessage(array $step, LinkedinCampaignRecipient $recipient): string
    {
        $templateId = $step['template_id'] ?? null;

        if ($templateId) {
            $template = LinkedinTemplate::withoutGlobalScope(BelongsToTeamsScope::class)->find($templateId);

            if ($template) {
                return $this->messageRenderer->render($template, $recipient);
            }
        }

        $inlineBody = (string) ($step['body'] ?? '');
        if ($inlineBody !== '') {
            $type = (($step['action'] ?? '') === 'connect')
                ? LinkedinTemplate::TYPE_CONNECT_NOTE
                : LinkedinTemplate::TYPE_DM;

            return $this->messageRenderer->renderBody(
                $inlineBody,
                $this->messageRenderer->extractVariables($recipient),
                $type
            )['body'];
        }

        return '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveRecipientNames(LinkedinCampaignRecipient $recipient, ?Lead $lead = null): array
    {
        $lead ??= $this->resolveLead($recipient);
        $person = $this->resolvePerson($recipient, $lead);
        $organization = $lead?->organization ?? $person?->organization;

        return [
            trim((string) ($person?->name ?? '')),
            trim((string) ($organization?->name ?? '')),
        ];
    }

    protected function computeJitterSeconds(LinkedinCampaign $campaign): int
    {
        $min = max(0, (int) $campaign->min_delay_seconds);
        $max = max($min, (int) $campaign->max_delay_seconds);

        return $min === $max ? $min : random_int($min, $max);
    }

    protected function logCrmNoteAndActivity(
        LinkedinCampaignRecipient $recipient,
        LinkedinCampaign $campaign,
        string $content,
        Carbon $notedAt
    ): void {
        $lead = $this->resolveLead($recipient);
        $person = $this->resolvePerson($recipient, $lead);
        $target = $lead ?? $person;

        if (! $target) {
            return;
        }

        $note = $target->notes()->create([
            'external_id' => Uuid::uuid4()->toString(),
            'content' => $content,
            'noted_at' => $notedAt,
            'team_id' => $recipient->team_id ?? $campaign->team_id,
        ]);

        $causeableType = auth()->user()
            ? auth()->user()->getMorphClass()
            : (config('auth.providers.users.model')
                ?? (class_exists('VentureDrake\LaravelCrm\Tests\Stubs\User')
                    ? 'VentureDrake\LaravelCrm\Tests\Stubs\User'
                    : 'App\Models\User'));

        $causeableId = auth()->id() ?: ($campaign->user_owner_id ?: ($target->user_owner_id ?? 1));

        $target->activities()->create([
            'causeable_type' => $causeableType,
            'causeable_id' => $causeableId,
            'timelineable_type' => $target->getMorphClass(),
            'timelineable_id' => $target->id,
            'recordable_type' => $note->getMorphClass(),
            'recordable_id' => $note->id,
            'team_id' => $recipient->team_id ?? $campaign->team_id,
        ]);
    }

    protected function normalizeSlug(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '/') || str_contains(strtolower($trimmed), 'linkedin.com')) {
            return LinkedinCampaignRecipient::normalizeLinkedinUrl($trimmed);
        }

        return strtolower(rawurldecode($trimmed));
    }

    protected function resolveCampaign(LinkedinCampaignRecipient $recipient): LinkedinCampaign
    {
        return LinkedinCampaign::withoutGlobalScope(BelongsToTeamsScope::class)
            ->findOrFail($recipient->linkedin_campaign_id);
    }

    protected function resolveLead(LinkedinCampaignRecipient $recipient): ?Lead
    {
        if (! $recipient->lead_id) {
            return null;
        }

        return Lead::withoutGlobalScope(BelongsToTeamsScope::class)
            ->with(['person', 'organization', 'pipelineStage'])
            ->find($recipient->lead_id);
    }

    protected function resolvePerson(LinkedinCampaignRecipient $recipient, ?Lead $lead = null): ?Person
    {
        if ($recipient->person_id) {
            return Person::withoutGlobalScope(BelongsToTeamsScope::class)
                ->with('organization')
                ->find($recipient->person_id);
        }

        return $lead?->person;
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
