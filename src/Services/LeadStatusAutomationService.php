<?php

namespace VentureDrake\LaravelCrm\Services;

use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Task;

class LeadStatusAutomationService
{
    /**
     * Handle automated lead stage and status updates when a task is completed.
     */
    public function handleTaskCompleted(Task $task): ?Lead
    {
        $lead = $this->resolveLeadFromTask($task);

        if (! $lead) {
            return null;
        }

        return $this->updateLeadStatusFromTask($lead, $task);
    }

    /**
     * Resolve the Lead instance associated with the given task.
     */
    public function resolveLeadFromTask(Task $task): ?Lead
    {
        if ($task->taskable instanceof Lead) {
            return $task->taskable;
        }

        if ($task->taskable_type === Lead::class || is_a($task->taskable_type, Lead::class, true)) {
            return Lead::find($task->taskable_id);
        }

        if ($task->taskable instanceof Person) {
            return $task->taskable->leads()->whereNull('converted_at')->latest()->first();
        }

        if ($task->taskable instanceof Organization) {
            return $task->taskable->leads()->whereNull('converted_at')->latest()->first();
        }

        return null;
    }

    /**
     * Advance the lead stage and status according to the completed task.
     */
    public function updateLeadStatusFromTask(Lead $lead, Task $task): ?Lead
    {
        $pipeline = $lead->pipeline ?: Pipeline::where('model', Lead::class)->first();

        if (! $pipeline) {
            return null;
        }

        $stages = $pipeline->pipelineStages()
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($stages->isEmpty()) {
            return null;
        }

        $targetStage = $this->determineTargetStage($task, $stages, $lead);

        if (! $targetStage) {
            return null;
        }

        // Rank comparison to ensure we only advance forward (no regression)
        $currentStageId = $lead->pipeline_stage_id;
        $currentRank = $stages->search(fn ($s) => $s->id == $currentStageId);
        $targetRank = $stages->search(fn ($s) => $s->id == $targetStage->id);

        if ($currentRank !== false && $targetRank !== false && $targetRank <= $currentRank) {
            // Lead is already at or past this stage
            return $lead;
        }

        $oldStageName = $lead->pipelineStage?->name ?? 'None';

        $lead->update([
            'pipeline_id' => $targetStage->pipeline_id,
            'pipeline_stage_id' => $targetStage->id,
            'pipeline_stage_order' => $targetStage->order,
            'lead_status_id' => ($lead->lead_status_id == 1 ? 2 : ($lead->lead_status_id ?: 2)),
        ]);

        // Record stage progression activity on lead timeline
        Activity::create([
            'causeable_type' => auth()->user() ? auth()->user()->getMorphClass() : null,
            'causeable_id' => auth()->id() ?: ($lead->user_owner_id ?: 1),
            'timelineable_type' => $lead->getMorphClass(),
            'timelineable_id' => $lead->id,
            'recordable_type' => $task->getMorphClass(),
            'recordable_id' => $task->id,
        ]);

        return $lead->fresh();
    }

    /**
     * Determine the appropriate pipeline stage based on task keywords and lead state.
     *
     * @param  Collection<int, PipelineStage>  $stages
     */
    public function determineTargetStage(Task $task, $stages, Lead $lead): ?PipelineStage
    {
        $taskName = strtolower($task->name ?? '');

        // 1. Stage 6: Closed Won
        if ($this->matchesKeywords($taskName, [
            'closed won', 'deal won', 'close deal', 'final approval',
            'sign contract', 'contract signed', 'license activation',
            'payment received', 'closed',
        ])) {
            return $this->findStageMatching($stages, ['closed won', 'won'], 6)
                ?: $stages->last();
        }

        // 2. Stage 5: Proposal Sent
        if ($this->matchesKeywords($taskName, [
            'proposal', 'send proposal', 'prepare & send', 'send formal proposal',
            'quote', 'send quote', 'contract terms', 'review contract', 'send proposal document',
        ])) {
            return $this->findStageMatching($stages, ['proposal', 'contract sent', 'quote'], 5);
        }

        // 3. Stage 4: Call Scheduled / Presentation
        if ($this->matchesKeywords($taskName, [
            'schedule call', 'schedule discovery', 'schedule pitch', 'schedule demo',
            'schedule meeting', 'book call', 'book calendar', 'presentation scheduled',
            'schedule product demo',
        ])) {
            return $this->findStageMatching($stages, ['call scheduled', 'presentation', 'appointment scheduled', 'scheduled'], 4);
        }

        // 4. Stage 3: Engaged / Qualified / Meeting Conducted
        if ($this->matchesKeywords($taskName, [
            'conduct discovery', 'discovery meeting', 'discovery call', 'qualif',
            'engage', 'review meeting', 'collect feedback', 'onboarding call',
            'quarterly review', 'pricing discussion',
        ])) {
            return $this->findStageMatching($stages, ['engaged', 'qualified', 'decision maker', 'meeting'], 3);
        }

        // 5. Stage 2: Connected / DM Sent / Outreach
        if ($this->matchesKeywords($taskName, [
            'connection request', 'connect', 'introductory dm', 'send a dm',
            'send an offer dm', ' dm', 'outreach', 'first contact',
            'contacted', 'contact this man', 'send intro',
        ])) {
            return $this->findStageMatching($stages, ['connected', 'dm sent', 'contacted', 'appointment scheduled'], 2);
        }

        // 6. Generic Task completion fallback:
        // If lead is in initial cold stage (Stage 1 / order 1 / rank 0) or unassigned, advance to Stage 2
        $currentRank = $stages->search(fn ($s) => $s->id == $lead->pipeline_stage_id);
        if ($currentRank === 0 || $currentRank === false) {
            return $stages->get(1) ?: $stages->firstWhere('order', 2);
        }

        return null;
    }

    /**
     * Check if string contains any of the given keywords.
     */
    protected function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find a stage in collection matching stage name patterns or target order.
     *
     * @param  Collection<int, PipelineStage>  $stages
     */
    protected function findStageMatching($stages, array $nameKeywords, int $targetOrder): ?PipelineStage
    {
        // First try to match by name
        foreach ($stages as $stage) {
            $stageName = strtolower($stage->name ?? '');
            foreach ($nameKeywords as $kw) {
                if (str_contains($stageName, $kw)) {
                    return $stage;
                }
            }
        }

        // Fallback to order
        return $stages->firstWhere('order', $targetOrder)
            ?: $stages->get($targetOrder - 1);
    }
}
