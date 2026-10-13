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
     * Handle automated lead stage and status updates when a task is completed,
     * and auto-create and assign subsequent sales playbook tasks.
     */
    public function handleTaskCompleted(Task $task, bool $autoCreateTasks = true): ?Lead
    {
        $lead = $this->resolveLeadFromTask($task);

        if (! $lead) {
            return null;
        }

        $lead = $this->updateLeadStatusFromTask($lead, $task) ?: $lead;

        if ($autoCreateTasks) {
            app(SalesPlaybookService::class)->autoCreateNextTasksForLead($lead, $task);
        }

        return $lead->fresh();
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
            $targetStageName = strtolower($targetStage->name ?? '');
            if (! str_contains($targetStageName, 'lost') && ! str_contains($targetStageName, 'disconnect')) {
                // Lead is already at or past this stage
                return $lead;
            }
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
            'causeable_type' => auth()->user() ? auth()->user()->getMorphClass() : (config('auth.providers.users.model') ?? (class_exists('VentureDrake\LaravelCrm\Tests\Stubs\User') ? 'VentureDrake\LaravelCrm\Tests\Stubs\User' : 'App\Models\User')),
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
        $taskName = strtolower(trim($task->name ?? ''));

        // A. Match directly against Sales Playbook templates
        $playbookService = app(SalesPlaybookService::class);
        $templates = $playbookService->getTemplates();

        foreach ($templates as $tmpl) {
            $tmplName = strtolower(trim($tmpl['name'] ?? ''));
            if ($tmplName && ($taskName === $tmplName || str_contains($taskName, $tmplName))) {
                $targetStageName = $tmpl['target_stage'] ?? null;
                if ($targetStageName) {
                    $matched = $this->findStageByTargetName($stages, $targetStageName);
                    if ($matched) {
                        return $matched;
                    }
                }
            }
        }

        // B. Keyword-based matching mapped to Sales Motions

        // 1. Stage 7: Closed Lost
        if ($this->matchesKeywords($taskName, [
            'graceful disconnect', 'disconnect', 'scenario 5', 'closed lost', 'lost',
            'not interested', 'unsubscribed', 'unresponsive', 'disqualified',
        ])) {
            return $this->findStageMatching($stages, ['closed lost', 'lost'], 7)
                ?: $stages->last();
        }

        // 2. Stage 6: Closed Won
        if ($this->matchesKeywords($taskName, [
            'closed won', 'deal won', 'close deal', 'annual enterprise production contract',
            'production contract', 'sprint handover', 'core conversion', 'core pipeline conversion',
            'delivery warranty', 'final approval', 'sign contract', 'contract signed',
            'license activation', 'payment received', 'closed',
        ])) {
            return $this->findStageMatching($stages, ['closed won', 'won'], 6)
                ?: $stages->last();
        }

        // 3. Stage 5: Proposal Sent
        if ($this->matchesKeywords($taskName, [
            'proposal', 'send proposal', 'prepare & send', 'send formal proposal',
            'pilot sprint sow', 'sandbox sow', 'paid sandbox', 'paid pilot', 'sow',
            'cro clearance', 'hrx terminal', 'quote', 'send quote', 'contract terms',
            'review contract', 'send proposal document', 'pricing discussion', 'call to discuss pricing',
        ])) {
            return $this->findStageMatching($stages, ['proposal sent', 'proposal', 'contract sent', 'quote'], 5);
        }

        // 4. Stage 4: Call Scheduled
        if ($this->matchesKeywords($taskName, [
            'call transition', 'technical sync', 'architecture whiteboard', 'whiteboard session',
            'schedule call', 'schedule discovery', 'schedule pitch', 'schedule demo',
            'schedule meeting', 'book call', 'book calendar', 'presentation scheduled',
            'schedule product demo', 'calendar invite', 'meeting debrief', 'canary demo',
        ])) {
            return $this->findStageMatching($stages, ['call scheduled', 'call', 'presentation', 'scheduled'], 4);
        }

        // 5. Stage 3: Engaged / Qualified / Outreach Follow-up
        if ($this->matchesKeywords($taskName, [
            'scenario 1', 'accept in silence', 'audit paper', 'case study',
            'scenario 2', 'kit offer', 'data engineering', 'data plumbing', 'loom teardown',
            'scenario 3', 'evaluation kit', 'quant_evaluation_kit', 'deliver evaluation kit',
            'architecture blueprint', 'canary paper-trader', 'deletion gain', 'paid pilot sprint',
            'scenario 4', 'nudge', 'mid-cadence',
            'objection', 'in-house', 'sybil clustering', 'zero lookahead', 'zapier', 'hallucination', 'popia',
            'conduct discovery', 'discovery meeting', 'discovery call', 'qualif',
            'engage', 'review meeting', 'collect feedback', 'gather requirements', 'technical requirements',
            'onboarding call', 'quarterly review',
        ])) {
            return $this->findStageMatching($stages, ['engaged / qualified', 'engaged', 'qualified', 'decision maker', 'meeting'], 3);
        }

        // 6. Stage 2: Connected / DM Sent
        // Initial connection request or connection note
        if ($this->matchesKeywords($taskName, [
            'connection request', 'connection note', 'connect with prospect', 'send a connection',
            'angle a', 'angle b', 'angle c', 'angle d', 'linkedin note', 'outreach', 'first contact',
            'contacted', 'contact this man', 'send intro', 'intro note', 'initial connection', 'connect',
        ])) {
            return $this->findStageMatching($stages, ['connected / dm sent', 'connected', 'dm sent', 'contacted'], 2);
        }

        // 7. Introductory DM / Offer DM task handling:
        if ($this->matchesKeywords($taskName, [
            'introductory dm', 'send an introductory dm', 'intro dm', 'offer dm', 'send an offer dm', 'send a dm',
        ])) {
            // If lead is already at Connected / DM Sent or higher, completing the DM moves/keeps them in Engaged / Qualified
            $currentRank = $stages->search(fn ($s) => $s->id == $lead->pipeline_stage_id);
            if ($currentRank !== false && $currentRank >= 1) {
                return $this->findStageMatching($stages, ['engaged / qualified', 'engaged', 'qualified'], 3);
            }

            // If still cold, initial DM completes initial outreach -> Connected / DM Sent
            return $this->findStageMatching($stages, ['connected / dm sent', 'connected', 'dm sent', 'contacted'], 2);
        }

        // 8. Generic Task completion fallback:
        $currentRank = $stages->search(fn ($s) => $s->id == $lead->pipeline_stage_id);
        if ($currentRank === 0 || $currentRank === false) {
            // Cold Prospect -> Connected / DM Sent
            return $this->findStageMatching($stages, ['connected / dm sent', 'connected', 'dm sent', 'contacted'], 2);
        }

        if ($currentRank === 1) {
            // Connected / DM Sent -> Engaged / Qualified
            return $this->findStageMatching($stages, ['engaged / qualified', 'engaged', 'qualified'], 3);
        }

        return null;
    }

    /**
     * Resolve target stage from named playbook stage.
     *
     * @param  Collection<int, PipelineStage>  $stages
     */
    protected function findStageByTargetName($stages, string $targetStageName): ?PipelineStage
    {
        $normalized = strtolower(trim($targetStageName));

        if (str_contains($normalized, 'lost') || str_contains($normalized, 'disconnect')) {
            return $this->findStageMatching($stages, ['closed lost', 'lost'], 7) ?: $stages->last();
        }

        if (str_contains($normalized, 'won')) {
            return $this->findStageMatching($stages, ['closed won', 'won'], 6);
        }

        if (str_contains($normalized, 'proposal')) {
            return $this->findStageMatching($stages, ['proposal sent', 'proposal', 'contract sent', 'quote'], 5);
        }

        if (str_contains($normalized, 'call') || str_contains($normalized, 'scheduled')) {
            return $this->findStageMatching($stages, ['call scheduled', 'call', 'presentation', 'scheduled'], 4);
        }

        if (str_contains($normalized, 'engaged') || str_contains($normalized, 'qualif')) {
            return $this->findStageMatching($stages, ['engaged / qualified', 'engaged', 'qualified'], 3);
        }

        if (str_contains($normalized, 'connected') || str_contains($normalized, 'dm sent')) {
            return $this->findStageMatching($stages, ['connected / dm sent', 'connected', 'dm sent', 'contacted'], 2);
        }

        if (str_contains($normalized, 'cold') || str_contains($normalized, 'new')) {
            return $this->findStageMatching($stages, ['cold prospect', 'cold', 'new'], 1);
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
        foreach ($nameKeywords as $kw) {
            foreach ($stages as $stage) {
                $stageName = strtolower($stage->name ?? '');
                if (str_contains($stageName, strtolower($kw))) {
                    return $stage;
                }
            }
        }

        // Fallback to order
        return $stages->firstWhere('order', $targetOrder)
            ?: $stages->get($targetOrder - 1);
    }
}
