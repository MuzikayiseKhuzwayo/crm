<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits\GeneratesExtensionApiToken;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits\HasSequenceAndSafetyForm;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;
use VentureDrake\LaravelCrm\Services\LinkedinMessageRenderer;
use VentureDrake\LaravelCrm\Services\LinkedinOutreachService;

class LinkedinCampaignShow extends Component
{
    use AuthorizesRequests;
    use GeneratesExtensionApiToken;
    use HasSequenceAndSafetyForm;
    use Toast;
    use WithPagination;

    public LinkedinCampaign $campaign;

    #[Url]
    public string $activeTab = 'recipients';

    #[Url]
    public string $recipientStatus = '';

    #[Url]
    public string $recipientSearch = '';

    // Inline personalized opener editing
    public ?int $editingRecipientId = null;

    public string $editingOpener = '';

    // Message preview modal
    public bool $showMessagePreviewModal = false;

    public string $previewRecipientName = '';

    public string $previewStepLabel = '';

    public string $previewRenderedBody = '';

    public bool $previewTruncated = false;

    public int $previewMaxLength = 300;

    // Bulk Enroll Leads modal
    public bool $showEnrollModal = false;

    public ?int $enrollLeadSourceId = null;

    public ?int $enrollLabelId = null;

    public string $enrollRelayStatus = '';

    public string $enrollSearch = '';

    public function mount(LinkedinCampaign $campaign): void
    {
        $this->campaign = $campaign;
        $this->fillFormFromCampaign($campaign);
    }

    public function updatedRecipientStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRecipientSearch(): void
    {
        $this->resetPage();
    }

    public function recipientStatuses(): array
    {
        return [
            ['id' => LinkedinCampaignRecipient::STATUS_QUEUED, 'name' => 'Queued'],
            ['id' => LinkedinCampaignRecipient::STATUS_LEASED, 'name' => 'Leased'],
            ['id' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT, 'name' => 'Connect Sent'],
            ['id' => LinkedinCampaignRecipient::STATUS_CONNECTED, 'name' => 'Connected'],
            ['id' => LinkedinCampaignRecipient::STATUS_IN_SEQUENCE, 'name' => 'In Sequence'],
            ['id' => LinkedinCampaignRecipient::STATUS_REPLIED, 'name' => 'Replied'],
            ['id' => LinkedinCampaignRecipient::STATUS_COMPLETED, 'name' => 'Completed'],
            ['id' => LinkedinCampaignRecipient::STATUS_SKIPPED, 'name' => 'Skipped'],
            ['id' => LinkedinCampaignRecipient::STATUS_FAILED, 'name' => 'Failed'],
        ];
    }

    public function relayStatusOptions(): array
    {
        return [
            ['id' => 'active', 'name' => 'Relay Active'],
            ['id' => 'standby', 'name' => 'Standby'],
            ['id' => 'engaged', 'name' => 'Engaged'],
            ['id' => 'fallen_off', 'name' => 'Fallen Off'],
            ['id' => 'disqualified', 'name' => 'Disqualified'],
        ];
    }

    public function recipients(): LengthAwarePaginator
    {
        return $this->campaign->recipients()
            ->with(['lead.organization', 'lead.person', 'person.organization'])
            ->when($this->recipientStatus !== '', fn (Builder $q) => $q->where('status', $this->recipientStatus))
            ->when($this->recipientSearch !== '', function (Builder $q) {
                $term = "%{$this->recipientSearch}%";
                $q->where(function (Builder $sub) use ($term) {
                    $sub->where('linkedin_url', 'like', $term)
                        ->orWhere('normalized_linkedin_slug', 'like', $term)
                        ->orWhere('personalized_opener', 'like', $term)
                        ->orWhereHas('lead', fn (Builder $lq) => $lq->where('title', 'like', $term));
                });
            })
            ->orderByDesc('id')
            ->paginate(25);
    }

    public function toggleStatus(): void
    {
        $this->authorize('update', $this->campaign);

        if ($this->campaign->isCircuitBroken()) {
            $this->warning('Reset the circuit breaker before activating this campaign.');

            return;
        }

        $newStatus = $this->campaign->isActive()
            ? LinkedinCampaign::STATUS_PAUSED
            : LinkedinCampaign::STATUS_ACTIVE;

        $this->campaign->update(['status' => $newStatus]);
        $this->campaign->refresh();
        $this->status = $this->campaign->status;

        $this->success(
            $newStatus === LinkedinCampaign::STATUS_ACTIVE
                ? 'Campaign activated.'
                : 'Campaign paused.'
        );
    }

    public function resetCircuitBreaker(bool $resume = true): void
    {
        $this->authorize('update', $this->campaign);

        $targetStatus = $resume
            ? LinkedinCampaign::STATUS_ACTIVE
            : LinkedinCampaign::STATUS_PAUSED;

        $this->campaign->update([
            'status' => $targetStatus,
            'circuit_broken_at' => null,
            'circuit_broken_reason' => null,
        ]);

        $this->campaign->refresh();
        $this->status = $this->campaign->status;

        $this->success($resume ? 'Circuit breaker reset and campaign resumed.' : 'Circuit breaker reset (campaign paused).');
    }

    public function saveSequence(): void
    {
        $this->authorize('update', $this->campaign);

        $this->validate($this->sequenceValidationRules());

        $this->campaign->update([
            'sequence_steps' => $this->normalizedSequenceSteps(),
        ]);

        $this->campaign->refresh();
        $this->fillFormFromCampaign($this->campaign);

        $this->success('Sequence steps saved.');
    }

    public function saveSettings(): void
    {
        $this->authorize('update', $this->campaign);

        $this->validate($this->safetyValidationRules());

        $this->campaign->update([
            'name' => $this->name,
            'daily_connect_limit' => $this->daily_connect_limit,
            'daily_dm_limit' => $this->daily_dm_limit,
            'weekly_connect_limit' => $this->weekly_connect_limit,
            'min_delay_seconds' => $this->min_delay_seconds,
            'max_delay_seconds' => $this->max_delay_seconds,
            'active_days' => array_values(array_unique(array_map('intval', $this->active_days))),
            'active_start_hour' => $this->active_start_hour,
            'active_end_hour' => $this->active_end_hour,
            'timezone' => $this->timezone,
        ]);

        $this->campaign->refresh();

        $this->success('Safety caps and schedule updated.');
    }

    // --- Inline Opener Editing ---

    public function startEditingOpener(int $recipientId): void
    {
        $recipient = $this->campaign->recipients()->find($recipientId);

        if (! $recipient) {
            return;
        }

        $this->editingRecipientId = $recipient->id;
        $this->editingOpener = (string) ($recipient->personalized_opener ?? '');
    }

    public function cancelEditingOpener(): void
    {
        $this->editingRecipientId = null;
        $this->editingOpener = '';
    }

    public function saveOpener(): void
    {
        $this->authorize('update', $this->campaign);

        if (! $this->editingRecipientId) {
            return;
        }

        $recipient = $this->campaign->recipients()->find($this->editingRecipientId);

        if (! $recipient) {
            $this->cancelEditingOpener();

            return;
        }

        $recipient->update([
            'personalized_opener' => trim($this->editingOpener) !== '' ? trim($this->editingOpener) : null,
        ]);

        $this->cancelEditingOpener();
        $this->success('Personalized opener updated.');
    }

    // --- Recipient Actions (Preview / Skip / Retry) ---

    public function openMessagePreview(int $recipientId, LinkedinMessageRenderer $renderer): void
    {
        $recipient = $this->campaign->recipients()
            ->with(['lead.organization', 'lead.person', 'person.organization'])
            ->find($recipientId);

        if (! $recipient) {
            return;
        }

        $steps = is_array($this->campaign->sequence_steps) ? $this->campaign->sequence_steps : [];
        usort($steps, static fn (array $a, array $b): int => ((int) ($a['step'] ?? 0)) <=> ((int) ($b['step'] ?? 0)));

        $targetStep = null;
        foreach ($steps as $step) {
            if ((int) ($step['step'] ?? 0) > (int) $recipient->current_step) {
                $targetStep = $step;
                break;
            }
        }
        $targetStep ??= $steps[0] ?? null;

        $person = $recipient->person ?? $recipient->lead?->person;
        $this->previewRecipientName = trim((string) ($person?->name ?? $recipient->lead?->title ?? $recipient->normalized_linkedin_slug));

        if (! $targetStep) {
            $this->previewStepLabel = 'No sequence steps configured';
            $this->previewRenderedBody = '';
            $this->previewTruncated = false;
            $this->previewMaxLength = 300;
            $this->showMessagePreviewModal = true;

            return;
        }

        $stepNum = (int) ($targetStep['step'] ?? 1);
        $action = (string) ($targetStep['action'] ?? 'connect');
        $this->previewStepLabel = "Step {$stepNum} (".ucfirst(str_replace('_', ' ', $action)).')';

        $templateId = $targetStep['template_id'] ?? null;
        $template = $templateId ? LinkedinTemplate::find($templateId) : null;

        if ($template) {
            $meta = $renderer->renderWithMetadata($template, $recipient);
        } else {
            $type = $action === 'connect' ? LinkedinTemplate::TYPE_CONNECT_NOTE : LinkedinTemplate::TYPE_DM;
            $meta = $renderer->renderBody(
                (string) ($targetStep['body'] ?? ''),
                $renderer->extractVariables($recipient),
                $type
            );
        }

        $this->previewRenderedBody = $meta['body'];
        $this->previewTruncated = $meta['truncated'];
        $this->previewMaxLength = $meta['max_length'];
        $this->showMessagePreviewModal = true;
    }

    public function skipRecipient(int $recipientId): void
    {
        $this->authorize('update', $this->campaign);

        $recipient = $this->campaign->recipients()->find($recipientId);

        if (! $recipient) {
            return;
        }

        $recipient->update([
            'status' => LinkedinCampaignRecipient::STATUS_SKIPPED,
            'next_action_at' => null,
            'lease_token' => null,
            'leased_at' => null,
            'lease_expires_at' => null,
            'worker_id' => null,
        ]);

        $this->success('Recipient skipped.');
    }

    public function retryRecipient(int $recipientId): void
    {
        $this->authorize('update', $this->campaign);

        $recipient = $this->campaign->recipients()->find($recipientId);

        if (! $recipient) {
            return;
        }

        $retryStatus = $recipient->last_dm_sent_at !== null
            ? LinkedinCampaignRecipient::STATUS_IN_SEQUENCE
            : ($recipient->connected_at !== null
                ? LinkedinCampaignRecipient::STATUS_CONNECTED
                : LinkedinCampaignRecipient::STATUS_QUEUED);

        $recipient->update([
            'status' => $retryStatus,
            'attempt_count' => 0,
            'next_action_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'lease_token' => null,
            'leased_at' => null,
            'lease_expires_at' => null,
            'worker_id' => null,
        ]);

        $this->success('Recipient re-queued for retry.');
    }

    // --- Bulk Enroll Leads Modal ---

    public function openEnrollModal(): void
    {
        $this->showEnrollModal = true;
    }

    /**
     * Query leads matching current bulk-enroll filters that have a non-empty LinkedIn field
     * on either crm_leads.linkedin or related crm_people.linkedin.
     *
     * @return Collection<int, Lead>
     */
    protected function filteredEnrollableLeads(): Collection
    {
        return Lead::query()
            ->with(['person', 'organization', 'leadSource', 'labels'])
            ->where(function (Builder $q) {
                $q->where(function (Builder $lq) {
                    $lq->whereNotNull('linkedin')
                        ->where('linkedin', '!=', '');
                })->orWhereHas('person', function (Builder $pq) {
                    $pq->whereNotNull('linkedin')
                        ->where('linkedin', '!=', '');
                });
            })
            ->when($this->enrollLeadSourceId, fn (Builder $q) => $q->where('lead_source_id', $this->enrollLeadSourceId))
            ->when($this->enrollLabelId, fn (Builder $q) => $q->whereHas('labels', fn (Builder $lq) => $lq->where($lq->getModel()->getTable().'.id', $this->enrollLabelId)))
            ->when($this->enrollRelayStatus !== '', fn (Builder $q) => $q->where('relay_status', $this->enrollRelayStatus))
            ->when(trim($this->enrollSearch) !== '', function (Builder $q) {
                $term = '%'.trim($this->enrollSearch).'%';
                $q->where(function (Builder $sq) use ($term) {
                    $sq->where('title', 'like', $term)
                        ->orWhere('linkedin', 'like', $term)
                        ->orWhereHas('organization', fn (Builder $oq) => $oq->where('name', 'like', $term))
                        ->orWhereHas('person', fn (Builder $pq) => $pq->where('linkedin', 'like', $term));
                });
            })
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Preview breakdown for the Bulk Enroll Leads modal.
     *
     * @return array{
     *     total_matched: int,
     *     enrollable_count: int,
     *     invalid_url_count: int,
     *     duplicate_count: int,
     *     sample_leads: array<int, array{id: int, title: string, linkedin_url: ?string, status: string}>
     * }
     */
    public function getEnrollmentPreviewProperty(): array
    {
        $leads = $this->filteredEnrollableLeads();

        $blockingStatuses = array_merge(
            LinkedinCampaignRecipient::ACTIVE_STATUSES,
            [
                LinkedinCampaignRecipient::STATUS_COMPLETED,
                LinkedinCampaignRecipient::STATUS_REPLIED,
            ]
        );

        $existingQuery = LinkedinCampaignRecipient::withoutGlobalScope(BelongsToTeamsScope::class)
            ->whereIn('status', $blockingStatuses)
            ->whereNotNull('normalized_linkedin_slug');

        if ($this->campaign->team_id === null) {
            $existingQuery->whereNull('team_id');
        } else {
            $existingQuery->where('team_id', $this->campaign->team_id);
        }

        $existingSlugs = $existingQuery->pluck('normalized_linkedin_slug')->flip()->all();
        $seenSlugs = [];

        $enrollable = 0;
        $invalidUrl = 0;
        $duplicate = 0;
        $samples = [];

        foreach ($leads as $lead) {
            $url = $lead->linkedin_url;
            $slug = $url ? LinkedinCampaignRecipient::normalizeLinkedinUrl($url) : null;

            if (! $slug) {
                $invalidUrl++;
                $rowStatus = 'invalid_url';
            } elseif (isset($existingSlugs[$slug]) || isset($seenSlugs[$slug])) {
                $duplicate++;
                $rowStatus = 'duplicate';
            } else {
                $enrollable++;
                $seenSlugs[$slug] = true;
                $rowStatus = 'ready';
            }

            if (count($samples) < 8) {
                $samples[] = [
                    'id' => $lead->id,
                    'title' => (string) ($lead->title ?: $lead->person?->name ?: "Lead #{$lead->id}"),
                    'linkedin_url' => $url,
                    'status' => $rowStatus,
                ];
            }
        }

        return [
            'total_matched' => $leads->count(),
            'enrollable_count' => $enrollable,
            'invalid_url_count' => $invalidUrl,
            'duplicate_count' => $duplicate,
            'sample_leads' => $samples,
        ];
    }

    public function enrollFilteredLeads(LinkedinOutreachService $outreachService): void
    {
        $this->authorize('update', $this->campaign);

        $leads = $this->filteredEnrollableLeads();
        $result = $outreachService->enrollLeads($this->campaign, $leads);

        $this->campaign->refresh();
        $this->showEnrollModal = false;
        $this->resetPage();

        if ($result['enrolled'] > 0) {
            $summary = "Enrolled {$result['enrolled']} lead(s)";
            $skippedParts = [];
            if ($result['skipped_duplicate'] > 0) {
                $skippedParts[] = "{$result['skipped_duplicate']} duplicate(s)";
            }
            if ($result['skipped_no_url'] > 0) {
                $skippedParts[] = "{$result['skipped_no_url']} invalid URL(s)";
            }
            if (! empty($skippedParts)) {
                $summary .= ' (skipped '.implode(', ', $skippedParts).')';
            }
            $this->success($summary.'.');
        } else {
            $this->warning("No new leads enrolled ({$result['skipped_duplicate']} duplicate(s), {$result['skipped_no_url']} invalid URL(s)).");
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.linkedin-campaigns.linkedin-campaign-show', [
            'recipients' => $this->recipients(),
            'recipientStatuses' => $this->recipientStatuses(),
            'templates' => $this->templates(),
            'actionOptions' => $this->stepActionOptions(),
            'weekdays' => $this->weekdayOptions(),
            'timezones' => $this->timezoneOptions(),
            'leadSources' => LeadSource::orderBy('name')->get(['id', 'name']),
            'labels' => Label::orderBy('name')->get(['id', 'name']),
            'relayStatuses' => $this->relayStatusOptions(),
            'enrollmentPreview' => $this->showEnrollModal ? $this->enrollmentPreview : null,
        ]);
    }
}
