<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\LeadStatusAutomationService;

class LaravelCrmSyncLeadStages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:sync-lead-stages 
                            {--lead= : Specific Lead ID or external_id to sync}
                            {--dry-run : Preview changes without persisting to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update Lead pipeline stages and status based on completed tasks';

    /**
     * Execute the console command.
     */
    public function handle(LeadStatusAutomationService $automationService): int
    {
        $this->info('Scanning completed tasks to sync Lead pipeline stages...');

        $leadFilter = $this->option('lead');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No database changes will be saved.');
        }

        $query = Task::whereNotNull('completed_at')
            ->where(function ($q) {
                $q->where('taskable_type', Lead::class)
                    ->orWhere('taskable_type', (new Lead)->getMorphClass());
            })
            ->orderBy('completed_at', 'asc');

        if ($leadFilter) {
            $lead = is_numeric($leadFilter)
                ? Lead::find($leadFilter)
                : Lead::where('external_id', $leadFilter)->first();

            if (! $lead) {
                $this->error("Lead not found: {$leadFilter}");

                return self::FAILURE;
            }

            $query->where('taskable_id', $lead->id);
        }

        $completedTasks = $query->get();
        $this->info("Found {$completedTasks->count()} completed tasks for leads.");

        $tasksByLead = $completedTasks->groupBy('taskable_id');
        $updatedLeadsCount = 0;
        $unchangedLeadsCount = 0;
        $summaryRows = [];

        foreach ($tasksByLead as $leadId => $tasks) {
            $lead = Lead::find($leadId);
            if (! $lead) {
                continue;
            }

            $initialStageName = $lead->pipelineStage?->name ?? 'Unassigned';
            $initialStageId = $lead->pipeline_stage_id;
            $currentLead = clone $lead;

            foreach ($tasks as $task) {
                if ($isDryRun) {
                    $pipeline = $currentLead->pipeline ?: Pipeline::where('model', Lead::class)->first();
                    $stages = $pipeline?->pipelineStages()->orderBy('order', 'asc')->orderBy('id', 'asc')->get();
                    if ($stages) {
                        $targetStage = $automationService->determineTargetStage($task, $stages, $currentLead);
                        if ($targetStage) {
                            $currentRank = $stages->search(fn ($s) => $s->id == $currentLead->pipeline_stage_id);
                            $targetRank = $stages->search(fn ($s) => $s->id == $targetStage->id);
                            if ($currentRank === false || $targetRank > $currentRank) {
                                $currentLead->pipeline_stage_id = $targetStage->id;
                                $currentLead->setRelation('pipelineStage', $targetStage);
                            }
                        }
                    }
                } else {
                    $automationService->handleTaskCompleted($task);
                    $lead->refresh();
                }
            }

            $finalStage = $isDryRun ? $currentLead->pipelineStage : $lead->pipelineStage;
            $finalStageId = $isDryRun ? $currentLead->pipeline_stage_id : $lead->pipeline_stage_id;
            $finalStageName = $finalStage?->name ?? 'Unassigned';

            if ($initialStageId !== $finalStageId) {
                $updatedLeadsCount++;
                $summaryRows[] = [
                    $lead->id,
                    Str::limit($lead->title, 40),
                    $initialStageName,
                    $finalStageName,
                    $tasks->count(),
                ];
            } else {
                $unchangedLeadsCount++;
            }
        }

        if (! empty($summaryRows)) {
            $this->table(
                ['Lead ID', 'Title', 'Initial Stage', 'Updated Stage', 'Completed Tasks'],
                array_slice($summaryRows, 0, 25)
            );

            if (count($summaryRows) > 25) {
                $this->comment('... and '.(count($summaryRows) - 25).' more leads updated.');
            }
        }

        $this->newLine();
        $this->info("Sync completed: {$updatedLeadsCount} leads advanced to newer stages, {$unchangedLeadsCount} leads remained unchanged.");

        return self::SUCCESS;
    }
}
